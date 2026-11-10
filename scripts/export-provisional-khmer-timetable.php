<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{ClassroomBooking, Schedule, SchoolClass, User};
use App\Services\{RotationTimetableGenerator, TeacherManagementService, TimetableBackup};
use Illuminate\Support\Facades\Storage;

$data = app(TeacherManagementService::class)->locked(function () {
    $rules = app(TeacherManagementService::class);
    $classes = SchoolClass::with('subjects')->where('program','Khmer')->whereNull('shared_timetable_class_id')->get();
    $teachers = User::where('role','teacher')->where('status','active')->where('program','Khmer')->get();
    $outside = Schedule::with('subject')->whereNotIn('school_class_id',$classes->modelKeys())->get();
    $bookings = ClassroomBooking::with(['classSession','classroom'])->get();
    return ['fingerprint'=>app(RotationTimetableGenerator::class)->fingerprint(), 'backup'=>app(TimetableBackup::class)->create(),
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
Storage::disk('local')->put('timetable-drafts/khmer-provisional-input.json',json_encode($data,JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo 'Exported '.count($data['classes']).' cohorts and '.count($data['teachers']).' staff records (including planned positions). Backup: '.$data['backup'].PHP_EOL;
