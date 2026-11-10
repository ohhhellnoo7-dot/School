<?php
namespace App\Services;
use App\Models\{ClassroomBooking,Schedule,SchoolClass,User};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use RuntimeException;
class KhmerCurriculumPlanner
{
    public function input(string $year): array
    {
return app(TeacherManagementService::class)->locked(function () use ($year) {
    $rules = app(TeacherManagementService::class);
    $classes = SchoolClass::with('subjects')->where('program','Khmer')->whereNull('shared_timetable_class_id')->get();
    $teachers = User::where('role','teacher')->where('status','active')->where('program','Khmer')->get();
    $outside = Schedule::with('subject')->whereNotIn('school_class_id',$classes->modelKeys())->where(fn($q)=>$q->where('academic_year',$year)->orWhereNull('academic_year'))->get();
    if ($outside->contains(fn ($lesson)=>!$lesson->classroom_id)) throw \Illuminate\Validation\ValidationException::withMessages(['generation'=>'Existing lessons outside Khmer lack physical classroom IDs. Correct these room assignments before generation.']);
    $bookings = ClassroomBooking::with(['classSession','classroom'])->get();
    return ['academic_year'=>$year, 'fingerprint'=>app(RotationTimetableGenerator::class)->fingerprint(), 'backup'=>app(TimetableBackup::class)->create(),
        'classes'=>$classes->map(function ($class) use ($rules,$bookings) {
            $reservations = $bookings->filter(fn ($b)=>$b->class_group_id === $class->class_group_id && $b->classSession?->session === $class->session);
            return ['id'=>$class->id,'grade'=>$class->name,'section'=>$class->section,'session'=>$class->session,
                'stream'=>$class->classGroup?->stream, 'subjects'=>$class->subjects->whereNotNull('curriculum_requirement_id')->where('required_weekly_minutes','>',0)->map(fn ($s)=>['id'=>$s->id,'name'=>$s->name,'hours'=>$s->required_weekly_minutes === null ? null : $s->required_weekly_minutes/60,'duration'=>\App\Support\KhmerLessonDuration::minutes($s)/60,'assigned_teacher_id'=>$s->teacher_id])->values()->all(),
                'reservations'=>$reservations->map(fn ($b)=>['day'=>$b->day_of_week,'classroom_id'=>$b->classroom_id,'room'=>$b->classroom->room_number,'starts_at'=>substr($b->starts_at,0,5),'ends_at'=>substr($b->ends_at,0,5)])->values()->all()];
        })->all(),
        'teachers'=>$teachers->map(function ($teacher) use ($rules,$outside) {
            $available=[];
            foreach ($rules::DAYS as $d=>$day) foreach ([7,8,9,10,13,14,15,16] as $h=>$hour) {
                $from=sprintf('%02d:00',$hour); $to=sprintf('%02d:00',$hour+1);
                if ($rules->onDuty($teacher,$day,$from,$to) && !$rules->breaks($teacher)->contains(fn($b)=>$b->weekday===$day && $rules::overlaps($from,$to,$b->start_time,$b->end_time)) && !$outside->contains(fn ($s)=>($s->teacher_id ?? $s->subject?->teacher_id)===$teacher->id && $s->day===$day && $rules::overlaps($from,$to,$s->starts_at,$s->ends_at))) $available[]=$d*8+$h;
            }
            return ['id'=>$teacher->id,'name'=>$teacher->name,'subjects'=>$rules->qualifications($teacher)->all(),'limit'=>max(0,($rules->limit($teacher)-$outside->where('teacher_id',$teacher->id)->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at)))/60),'available'=>$available,'planned'=>$teacher->requiresTeacherConfirmation(),'subject_limit'=>$rules->subjectLimit($teacher)];
        })->all(), 'outside'=>$outside->map(fn ($s)=>$s->getAttributes())->all()];
});

    }
    public function preview(string $year): array
    {
        $report=app(KhmerCurriculumService::class)->readiness($year);
        if ($report['blockers']) throw \Illuminate\Validation\ValidationException::withMessages(['generation'=>$report['blockers']]);
        $input=$this->input($year); $token=(string) Str::uuid();
        $path=storage_path('app/private/timetable-drafts/curriculum-input-'.$token.'.json');
        $output=storage_path('app/private/timetable-drafts/curriculum-solved-'.$token.'.json');
        if (!is_dir(dirname($path))) mkdir(dirname($path),0755,true);
        if (file_put_contents($path,json_encode($input,JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Could not write timetable draft.');
        if (function_exists('set_time_limit')) set_time_limit(180);
        $process=new Process([config('curriculum.python','python'),base_path('scripts/solve-provisional-khmer-timetable.py'),$path,$output]);
        $process->setTimeout(160); $process->run();
        if (! $process->isSuccessful() || !is_file($output)) throw \Illuminate\Validation\ValidationException::withMessages(['generation'=>'No complete conflict-free solution was found. Review staff qualifications, availability, subject hours and workload before retrying. The current timetable was preserved.']);
        return json_decode(file_get_contents($output),true,512,JSON_THROW_ON_ERROR);
    }
}
