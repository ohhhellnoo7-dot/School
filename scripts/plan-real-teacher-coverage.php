<?php

// Read-only staffing plan. No teacher identities, workload limits or lessons are changed.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule, SchoolClass, User};
use App\Services\TeacherManagementService;

$rules = app(TeacherManagementService::class);
$year = now(config('classrooms.timezone'))->year.'-'.(now(config('classrooms.timezone'))->year+1);
$lessons = Schedule::with('subject')->where(fn ($q) => $q->where('academic_year',$year)->orWhereNull('academic_year'))->get();
$classes = SchoolClass::with('subjects')->get();
$teachers = User::where('role','teacher')->where('status','active')->get();
$shortages = []; $staff = [];
foreach ($classes as $class) foreach ($class->timetableOwner()->subjects as $subject) {
    if ($subject->required_weekly_minutes === null) continue;
    $actual = $lessons->where('subject_id',$subject->id)->sum(fn ($s) => $rules::duration($s->starts_at,$s->ends_at));
    $missing = max(0,(int)$subject->required_weekly_minutes-$actual);
    if ($missing) $shortages[] = ['class_id'=>$class->id,'class'=>$class->label,'program'=>$class->program,'subject'=>$subject->name,'missing_minutes'=>$missing];
}
foreach ($teachers as $teacher) {
    $used = $lessons->where('teacher_id',$teacher->id)->sum(fn ($s) => $rules::duration($s->starts_at,$s->ends_at));
    $limit = $rules->limit($teacher);
    $staff[] = ['teacher'=>$teacher->name,'program'=>$teacher->program,'qualifications'=>$rules->qualifications($teacher)->all(),'scheduled_hours'=>$used/60,'limit_hours'=>$limit/60,'remaining_hours'=>max(0,$limit-$used)/60,'overload_hours'=>max(0,$used-$limit)/60];
}
$lines = ['# Real teacher coverage required', '', 'Academic year: '.$year.'. This report preserves the current curriculum and the school workload limits. No sample teachers were created. Provide each additional real teacher’s name, program, verified subjects, working days/times and weekly teaching limit before assigning lessons.', '', '## Current teacher capacity', '', '| Program | Teachers | Scheduled hours | Weekly limit total | Remaining hours | Existing overload |', '|---|---|---|---|---|---|'];
foreach (collect($staff)->groupBy('program') as $program=>$rows) $lines[] = '|'.$program.'|'.$rows->count().'|'.$rows->sum('scheduled_hours').'|'.$rows->sum('limit_hours').'|'.$rows->sum('remaining_hours').'|'.$rows->sum('overload_hours').'|';
$lines = array_merge($lines,['','## Missing weekly subject hours','','These totals are per saved student class/session. Classes sharing a normalized group and session are listed below for review; demand must not be assumed to represent separate simultaneous room bookings.','','| Program | Subject | Missing hours | Affected class sessions |','|---|---|---|---|']);
foreach (collect($shortages)->groupBy(fn ($row)=>$row['program'].'|'.$row['subject'])->sortKeys() as $rows) $lines[] = '|'.$rows->first()['program'].'|'.$rows->first()['subject'].'|'.($rows->sum('missing_minutes')/60).'|'.$rows->count().'|';
$lines = array_merge($lines,['','## Shared class/session links needing review','','Multiple saved class records below use the same normalized class group/session. Their student enrollments have been preserved; they have not been merged or silently placed in the same lesson.','','| Group | Session | Class records |','|---|---|---|']);
foreach ($classes->filter(fn ($class)=>$class->class_group_id)->groupBy(fn ($class)=>$class->class_group_id.'|'.$class->session) as $rows) if ($rows->count()>1) $lines[] = '|'.$rows->first()->class_group_id.'|'.$rows->first()->session.'|'.$rows->map(fn ($class)=>'#'.$class->id.' '.$class->label)->join('; ').'|';
$lines = array_merge($lines,['','## Current teachers','','| Teacher | Program | Subjects | Scheduled / weekly limit | Available weekly hours |','|---|---|---|---|---|']);
foreach ($staff as $row) $lines[] = '|'.$row['teacher'].'|'.$row['program'].'|'.implode(', ',$row['qualifications']).'|'.$row['scheduled_hours'].' / '.$row['limit_hours'].'|'.$row['remaining_hours'].'|';
file_put_contents(__DIR__.'/../docs/real-teacher-coverage.md',implode(PHP_EOL,$lines).PHP_EOL);
echo json_encode(['academic_year'=>$year,'affected_class_sessions'=>collect($shortages)->pluck('class_id')->unique()->count(),'missing_subject_hours'=>collect($shortages)->sum('missing_minutes')/60,'english_writing_missing_hours'=>collect($shortages)->where('program','English')->where('subject','English writing')->sum('missing_minutes')/60,'current_english_capacity'=>collect($staff)->where('program','English')->sum('remaining_hours'),'report'=>'docs/real-teacher-coverage.md'],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
