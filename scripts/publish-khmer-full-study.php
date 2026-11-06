<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{ClassroomBooking, Schedule, SchoolClass, Subject, User};
use App\Services\{KhmerClassTimetableReport, RotationTimetableGenerator, TeacherManagementService, TimetableBackup};
use Illuminate\Support\Facades\{DB, Storage};

$apply = in_array('--apply', $argv, true);
$disk = Storage::disk('local');
$proposal = json_decode($disk->get('timetable-drafts/khmer-full-study-solved.json'), true, 512, JSON_THROW_ON_ERROR);
if (! in_array($proposal['status'], ['FEASIBLE', 'OPTIMAL'], true) || empty($proposal['rows'])) throw new RuntimeException('No complete solved timetable is available.');
$report = app(TeacherManagementService::class)->locked(function () use ($proposal, $apply) {
    $rules = app(TeacherManagementService::class);
    if (! hash_equals($proposal['fingerprint'], app(RotationTimetableGenerator::class)->fingerprint())) throw new RuntimeException('Scheduling data changed. Export and solve again.');
    $classes = SchoolClass::with('subjects')->where('program', 'Khmer')->whereNull('shared_timetable_class_id')->get()->keyBy('id');
    $teachers = User::where('role','teacher')->where('program','Khmer')->where('status','active')->get()->keyBy('id');
    $outside = fn () => DB::table('schedules')->whereNotIn('school_class_id',$classes->modelKeys())->orderBy('id')->get()->toJson();
    $unchanged = $outside();
    $backup = $apply ? app(TimetableBackup::class)->create() : $proposal['backup'];
    DB::beginTransaction();
    try {
        DB::table('teacher_management_settings')->where('id',1)->update(['khmer_teacher_free_periods'=>0]);
        DB::table('teacher_break_plans')->whereIn('teacher_id',$teachers->modelKeys())->delete();
        Schedule::whereIn('school_class_id',$classes->modelKeys())->update(['cancelled_at'=>now()]);
        DB::table('subjects')->whereIn('school_class_id',$classes->modelKeys())->update(['teacher_id'=>null,'required_weekly_minutes'=>0,'updated_at'=>now()]);
        $subjectMap = []; $catalogChanges = [];
        foreach ($proposal['curriculum'] as $classId=>$subjects) {
            $class = $classes->get((int) $classId);
            if (! $class) throw new RuntimeException('Unknown Khmer cohort in proposal.');
            foreach ($subjects as $name=>$target) {
                if (! $target['hours']) continue;
                $subject = $class->subjects->firstWhere('name',$name);
                if (! $subject) {
                    $source = Subject::where('name',$name)->whereHas('schoolClass',fn ($q)=>$q->where('program','Khmer')->where('name',$class->name))->firstOrFail();
                    $subject = Subject::create(['school_class_id'=>$class->id,'name'=>$name,'code'=>'KH-FULL-'.$class->id.'-'.substr(sha1($name),0,12),'lesson_minutes'=>$source->lesson_minutes,'required_weekly_minutes'=>0]);
                    $catalogChanges[] = ['class_id'=>$class->id,'subject'=>$name,'source_subject_id'=>$source->id];
                }
                $duration = \App\Support\KhmerLessonDuration::minutes($subject);
                \App\Support\KhmerLessonDuration::validateWeeklyHours($subject,$target['hours']*60);
                DB::table('subjects')->where('id',$subject->id)->update(['required_weekly_minutes'=>$target['hours']*60,'lesson_minutes'=>$duration]);
                $subjectMap[$class->id][$name] = $subject->id;
            }
        }
        $records=[]; $classSlots=[]; $teacherSlots=[]; $roomSlots=[]; $workloads=[]; $subjectHours=[];
        $bookings = ClassroomBooking::with('classSession')->get();
        foreach ($proposal['rows'] as $row) {
            $class = $classes->get($row['school_class_id']);
            $teacher = $teachers->get($row['teacher_id']);
            if (! $class || ! $teacher || ! $rules->qualifications($teacher)->contains($row['subject'])) throw new RuntimeException('Invalid cohort or teacher qualification.');
            $row['subject_id'] = $subjectMap[$class->id][$row['subject']] ?? throw new RuntimeException('Missing approved subject.');
            $subject = Subject::findOrFail($row['subject_id']);
            $duration = $rules::duration($row['starts_at'],$row['ends_at']);
            if ($duration !== \App\Support\KhmerLessonDuration::minutes($subject) || ! $rules->onDuty($teacher,$row['day'],$row['starts_at'],$row['ends_at'])) throw new RuntimeException('Invalid duration or teacher availability.');
            if (! $bookings->contains(fn ($b)=>$b->class_group_id===$class->class_group_id && $b->classSession?->session===$class->session && $b->classroom_id===$row['classroom_id'] && $b->day_of_week===$row['day'] && $rules::minute($b->starts_at)<=$rules::minute($row['starts_at']) && $rules::minute($b->ends_at)>=$rules::minute($row['ends_at']))) throw new RuntimeException('A lesson is outside its physical classroom reservation.');
            for ($m=$rules::minute($row['starts_at']);$m<$rules::minute($row['ends_at']);$m+=60) {
                $key=$row['day'].'-'.$m;
                if (isset($classSlots[$class->id][$key]) || isset($teacherSlots[$teacher->id][$key]) || isset($roomSlots[$row['classroom_id']][$key])) throw new RuntimeException('Duplicate or overlapping lesson.');
                $classSlots[$class->id][$key]=true; $teacherSlots[$teacher->id][$key]=true; $roomSlots[$row['classroom_id']][$key]=true;
            }
            $workloads[$teacher->id]=($workloads[$teacher->id]??0)+$duration;
            $subjectHours[$row['subject_id']]=($subjectHours[$row['subject_id']]??0)+$duration;
            unset($row['subject'],$row['teacher']);
            $records[]=$row+['generated_at'=>now(),'created_at'=>now(),'updated_at'=>now()];
        }
        foreach ($classes as $class) if (count($classSlots[$class->id]??[])!==20) throw new RuntimeException('A cohort is incomplete.');
        foreach ($teachers as $teacher) if (($workloads[$teacher->id]??0)!==2400 || $workloads[$teacher->id]>$rules->limit($teacher)) throw new RuntimeException('A teacher does not have 40 valid teaching hours.');
        foreach (Subject::whereIn('school_class_id',$classes->modelKeys())->get() as $s) if (($subjectHours[$s->id]??0)!==(int)$s->required_weekly_minutes) throw new RuntimeException('Curriculum coverage mismatch.');
        foreach (array_chunk($records,100) as $chunk) DB::table('schedules')->insert($chunk);
        SchoolClass::whereIn('id',$classes->modelKeys())->update(['weekly_relaxation_minutes'=>0]);
        $validation=app(KhmerClassTimetableReport::class)->report();
        if ($validation['valid_identities']!==30 || $validation['cohort_count']!==60) {
            $issues=collect($validation['identities'])->flatMap(fn ($i)=>collect($i['cohorts'])->flatMap(fn ($c)=>$c['issues']))->unique()->take(12)->all();
            throw new RuntimeException('Final school validation failed: '.implode('; ',$issues));
        }
        if ($outside()!==$unchanged) throw new RuntimeException('A non-Khmer timetable changed.');
        $result=['status'=>$apply?'published':'validated_draft','backup'=>$backup,'class_identities'=>30,'cohorts'=>60,'teachers'=>$teachers->count(),'lesson_records'=>count($records),'student_weekly_hours'=>20,'teacher_weekly_hours'=>40,'teacher_free_periods'=>0,'english_and_chinese_unchanged'=>true,'catalog_changes'=>$catalogChanges,'validation'=>$validation];
        if ($apply) DB::commit(); else DB::rollBack();
        return $result;
    } catch (Throwable $e) {
        DB::rollBack();
        throw $e;
    }
});
$path='timetable-drafts/khmer-full-study-'.($apply?'published':'validated').'.json';
$disk->put($path,json_encode($report,JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
echo json_encode(collect($report)->except(['validation','catalog_changes'])->all(),JSON_PRETTY_PRINT).PHP_EOL;
echo $disk->path($path).PHP_EOL;
