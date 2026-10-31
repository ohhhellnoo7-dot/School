<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule,User};
use App\Services\{LessonManagementService,TeacherManagementService};
use Illuminate\Support\Facades\{DB,Storage};

$rules=app(TeacherManagementService::class); $disk=Storage::disk('local');
$path=collect($disk->files('timetable-drafts'))->filter(fn($p)=>str_contains($p,'/khmer-eight-hour-teaching-'))->sort()->last();
if(!$path) throw new RuntimeException('No Khmer teaching-policy report exists.');
$report=json_decode($disk->get($path),true,512,JSON_THROW_ON_ERROR);
$backup=json_decode($disk->get($report['backup']),true,512,JSON_THROW_ON_ERROR);
$teachers=User::where('role','teacher')->where('program','Khmer')->get();
if($teachers->count()!==$report['khmer_teacher_count'] || $rules->defaultLimit('Khmer')!==2400 || $rules->defaultLimit()!==(int)$report['previous_settings']['weekly_minutes']) throw new RuntimeException('Teacher count or program limits are incorrect.');
$lessons=Schedule::whereIn('teacher_id',$teachers->modelKeys())->get();
foreach($teachers as $teacher) {
    if($rules->limit($teacher)!==2400) throw new RuntimeException('A Khmer teacher does not have the requested teaching limit.');
    $entries=$lessons->where('teacher_id',$teacher->id);
    if($entries->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at))>2400) throw new RuntimeException('Weekly teaching limit exceeded.');
    foreach($entries->groupBy('day') as $dayEntries) if($dayEntries->sum(fn($s)=>$rules::duration($s->starts_at,$s->ends_at))>480) throw new RuntimeException('Daily teaching limit exceeded.');
}
foreach($report['reassigned'] as $entry) app(LessonManagementService::class)->validate(Schedule::findOrFail($entry['new_lesson_id']));
foreach(['users','students'] as $table) {
    $current=DB::table($table)->get()->keyBy('id');
    if($current->count()!==count($backup['tables'][$table])) throw new RuntimeException('User or student record count changed.');
    foreach($backup['tables'][$table] as $row) foreach($row as $field=>$value) if($current[$row['id']]->$field!==$value) throw new RuntimeException('User account or student enrollment changed.');
}
$lines=['# Khmer eight-hour teaching policy','','Khmer teachers: '.$teachers->count().'. Maximum classroom teaching: 8 hours per weekday / 40 hours per week. Morning availability: 07:00–11:00. Afternoon availability: 13:00–17:00. Explicit unavailable windows and existing designated teacher free periods remain in effect. Actual lessons depend on qualifications, free time and classroom reservations.','','Preparation: 10 hours/week; marking: 5 hours/week; other duties: 5 hours/week. These are additional planning allowances, making the displayed Khmer work plan 60 hours in total. English and Chinese teaching defaults remain unchanged.','','Additional lessons restored: '.$report['reassigned_count'].'. Remaining Khmer lesson gaps at the attempted times: '.$report['unfilled_khmer_lessons'].'. Khmer teachers with confirmed subject qualifications: '.$report['khmer_teachers_with_confirmed_subjects'].'.','','Verification: teacher limits, daily/weekly assigned hours, all restored lesson constraints, original user accounts and student enrollments passed.','','| Class | Subject | Day | Time | Reasons |','|---|---|---|---|---|'];
foreach($report['unfilled'] as $entry) $lines[]='|'.$entry['class'].'|'.$entry['subject'].'|'.$entry['day'].'|'.$entry['start'].'–'.$entry['end'].'|'.implode('; ',$entry['reasons']).'|';
$lines=array_merge($lines,['','Backup: '.$report['backup']]);
file_put_contents(__DIR__.'/../docs/khmer-eight-hour-teaching.md',implode(PHP_EOL,$lines).PHP_EOL);
echo json_encode(['verified'=>true,'khmer_teachers'=>$teachers->count(),'weekly_limit'=>40,'daily_limit'=>8,'restored_lessons'=>$report['reassigned_count'],'remaining_khmer_lesson_gaps'=>$report['unfilled_khmer_lessons'],'work_plan'=>$rules->weeklyWorkPlan('Khmer'),'report'=>'docs/khmer-eight-hour-teaching.md'],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
