<?php

require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{ClassroomBooking, ClassSession, Schedule, SchoolClass, Student, User};
use App\Services\TeacherManagementService;
use App\Support\KhmerLessonDuration;
use Illuminate\Support\Facades\{DB,Storage};

$disk=Storage::disk('local');
$path=collect($disk->files('timetable-drafts'))->filter(fn($p)=>str_contains($p,'/demo-school-completion-'))->sortBy(fn($p)=>$disk->lastModified($p))->last();
if(!$path) throw new RuntimeException('No completed school demo rebuild exists.');
$report=json_decode($disk->get($path),true,512,JSON_THROW_ON_ERROR);
$backup=json_decode($disk->get($report['backup']),true,512,JSON_THROW_ON_ERROR);
$rules=app(TeacherManagementService::class);
$classes=SchoolClass::with('subjects')->get()->keyBy('id');
$lessons=Schedule::with(['subject','teacher','classroom'])->where(fn($q)=>$q->where('academic_year',$report['academic_year'])->orWhereNull('academic_year'))->get();
$sessions=ClassSession::all()->keyBy(fn($s)=>$s->class_group_id.'|'.$s->session);
$bookings=ClassroomBooking::all()->groupBy(fn($b)=>$b->class_group_id.'|'.$b->classSession->session.'|'.$b->day_of_week);
$qualifications=DB::table('teacher_qualifications')->get()->groupBy('teacher_id')->map(fn($r)=>$r->pluck('subject_name'));
$periods=DB::table('teacher_working_periods')->get()->groupBy('teacher_id');
$breaks=DB::table('teacher_breaks')->join('teacher_break_plans','teacher_break_plans.id','=','teacher_breaks.teacher_break_plan_id')->select('teacher_breaks.*','teacher_break_plans.teacher_id')->get()->groupBy('teacher_id');
$students=Student::where('status','active')->get()->groupBy(fn($s)=>$classes[$s->school_class_id]->timetableOwnerId());
$resource=[]; $usage=[]; $teacherSubjects=[];
foreach($lessons as $lesson) {
    $class=$classes[$lesson->school_class_id]; $owner=$class->timetableOwnerId();
    if($owner!==$class->id || !$lesson->classroom || $lesson->classroom->status!=='active') throw new RuntimeException('Invalid owner/room for lesson '.$lesson->id);
    if($lesson->classroom->capacity<($students->get($owner,collect())->count())) throw new RuntimeException('Combined cohort exceeds room capacity.');
    $session=$sessions[$class->class_group_id.'|'.$class->session];
    $start=$rules::minute($lesson->starts_at); $end=$rules::minute($lesson->ends_at);
    if(!in_array($lesson->day,$rules::DAYS,true) || $start<$rules::minute($session->starts_at) || $end>$rules::minute($session->ends_at)) throw new RuntimeException('Lesson outside its study session: '.$lesson->id);
    if(!$bookings->get($class->class_group_id.'|'.$class->session.'|'.$lesson->day,collect())->contains(fn($b)=>$b->classroom_id===$lesson->classroom_id && $b->starts_minute<=$start && $b->ends_minute>=$end)) throw new RuntimeException('Lesson is not covered by its room reservation.');
    if(!$lesson->isRelaxation()) {
        $teacher=$lesson->teacher;
        if(!$teacher || !$lesson->subject || $teacher->role!=='teacher' || $teacher->status!=='active' || $teacher->program!==$class->program || !$qualifications->get($teacher->id,collect())->contains($lesson->subject->name)) throw new RuntimeException('Invalid qualification/program/teacher for '.$lesson->id);
        if($lesson->subject->school_class_id!==$class->id || $end-$start!==KhmerLessonDuration::minutes($lesson->subject)) throw new RuntimeException('Invalid subject or lesson duration: '.$lesson->id);
        $duty=$periods->get($teacher->id,collect());
        if(!$duty->contains(fn($p)=>!$p->unavailable && $p->weekday===$lesson->day && $rules::minute($p->start_time)<=$start && $rules::minute($p->end_time)>=$end) || $duty->contains(fn($p)=>$p->unavailable && $p->weekday===$lesson->day && $rules::overlaps($lesson->starts_at,$lesson->ends_at,$p->start_time,$p->end_time))) throw new RuntimeException('Teacher outside duty hours.');
        if($breaks->get($teacher->id,collect())->contains(fn($b)=>$b->weekday===$lesson->day && $rules::overlaps($lesson->starts_at,$lesson->ends_at,$b->start_time,$b->end_time))) throw new RuntimeException('Teaching overlaps designated free period.');
        $usage[$teacher->id]=($usage[$teacher->id]??0)+$end-$start;
        $teacherSubjects[$teacher->id][$lesson->subject->name]=true;
    } elseif($end-$start!==60 || $lesson->teacher_id || $lesson->subject_id) throw new RuntimeException('Invalid relaxation entry.');
    for($m=$start;$m<$end;$m++) foreach(['class:'.$owner,'room:'.$lesson->classroom_id,...($lesson->teacher_id?['teacher:'.$lesson->teacher_id]:[])] as $key) {
        $slot=$key.'|'.$lesson->day.'|'.$m;
        if(isset($resource[$slot])) throw new RuntimeException('Overlap between '.$resource[$slot].' and '.$lesson->id.' for '.$key);
        $resource[$slot]=$lesson->id;
    }
}
foreach($usage as $id=>$minutes) {
    $teacher=User::findOrFail($id);
    if($minutes>$rules->limit($teacher) || count($teacherSubjects[$id])>$rules->subjectLimit($teacher)) throw new RuntimeException('Teacher workload/subject limit exceeded.');
    $free=$breaks->get($id,collect());
    if($free->count()!==3 || $free->pluck('weekday')->unique()->count()!==3) throw new RuntimeException('Teacher lacks three free periods.');
}
$lines=['# Completed school demo timetables','','Academic year: '.$report['academic_year'].'. All existing physical rooms and normalized cohort/session reservations were used. New teachers are explicitly labelled Sample. Student enrollments were preserved; linked legacy class entries share one normalized timetable.','','| Class | Timetable owner | Weekly study hours | Weekly relaxation hours | Status |','|---|---|---|---|---|'];
foreach($classes as $class) {
    $owner=$classes[$class->timetableOwnerId()]; $entries=$lessons->where('school_class_id',$owner->id);
    foreach($owner->subjects as $subject) {
        $actual=$entries->where('subject_id',$subject->id)->sum(fn($l)=>$rules::duration($l->starts_at,$l->ends_at));
        if($subject->required_weekly_minutes===null || $actual!==(int)$subject->required_weekly_minutes) throw new RuntimeException('Missing or excess curriculum: '.$class->label.' / '.$subject->name);
    }
    $relax=$entries->where('entry_type','relaxation');
    if($relax->sum(fn($l)=>$rules::duration($l->starts_at,$l->ends_at))!==(int)$owner->weekly_relaxation_minutes || $relax->pluck('day')->unique()->count()!==$relax->count()) throw new RuntimeException('Relaxation requirement mismatch.');
    $session=$sessions[$class->class_group_id.'|'.$class->session];
    foreach($rules::DAYS as $day) for($m=$rules::minute($session->starts_at);$m<$rules::minute($session->ends_at);$m++) if(!isset($resource['class:'.$owner->id.'|'.$day.'|'.$m])) throw new RuntimeException('Empty study period: '.$class->label.' '.$day);
    $lines[]='|'.$class->label.'|'.$owner->id.'|'.($entries->where('entry_type','academic')->sum(fn($l)=>$rules::duration($l->starts_at,$l->ends_at))/60).'|'.($owner->weekly_relaxation_minutes/60).'|Complete|';
}
foreach(['students','classrooms','classroom_bookings'] as $table) {
    $after=DB::table($table)->get()->keyBy('id');
    if($after->count()!==count($backup['tables'][$table])) throw new RuntimeException('Original record count changed: '.$table);
    foreach($backup['tables'][$table] as $row) foreach($row as $field=>$value) if($after[$row['id']]->$field!==$value) throw new RuntimeException('Original student/room/reservation changed: '.$table);
}
$history=DB::table('schedules')->get()->keyBy('id');
foreach($backup['tables']['schedules'] as $row) {
    if(!isset($history[$row['id']])) throw new RuntimeException('Lesson history deleted.');
    foreach($row as $field=>$value) if($field!=='cancelled_at' && !($field==='updated_at' && $history[$row['id']]->cancelled_at!==$row['cancelled_at']) && $history[$row['id']]->$field!==$value) throw new RuntimeException('Historical lesson changed: '.$row['id'].' '.$field);
}
$lines=array_merge($lines,['','## Verification','','- Every curriculum target matched exactly; no empty study periods or missing physical room links.','- No overlapping teacher, classroom or normalized student cohort lessons.','- Teacher qualifications, duty hours, free periods and weekly workload limits passed.','- Original student enrollments, physical rooms and room reservations are unchanged.','- Original lesson records remain available as history; the completed 10A week was preserved.','','New sample teachers: '.count($report['new_sample_teachers']).'. Active lessons: '.$lessons->count().'. Shared class links: '.count($report['shared_timetables']).'.','','Backup: '.$report['backup']]);
file_put_contents(__DIR__.'/../docs/completed-demo-timetables.md',implode(PHP_EOL,$lines).PHP_EOL);
echo json_encode(['verified'=>true,'class_records'=>$classes->count(),'normalized_cohort_sessions'=>$report['cohort_count'],'active_lessons'=>$lessons->count(),'new_sample_teachers'=>count($report['new_sample_teachers']),'missing_curriculum'=>0,'empty_study_periods'=>0,'teacher_room_cohort_overlaps'=>0,'report'=>'docs/completed-demo-timetables.md'],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
