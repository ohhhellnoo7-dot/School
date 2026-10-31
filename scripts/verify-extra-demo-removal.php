<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule,User};
use App\Services\LessonManagementService;
use Illuminate\Support\Facades\{DB,Storage};

$disk=Storage::disk('local');
$allSamples=in_array('--all-samples',$argv,true);
$prefix=$allSamples?'/removed-all-sample-teachers-':'/removed-extra-demo-teachers-';
$path=collect($disk->files('timetable-drafts'))->filter(fn($p)=>str_contains($p,$prefix))->sortBy(fn($p)=>$disk->lastModified($p))->last();
if(!$path) throw new RuntimeException('No completed extra-demo removal report.');
$report=json_decode($disk->get($path),true,512,JSON_THROW_ON_ERROR);
$backup=json_decode($disk->get($report['backup']),true,512,JSON_THROW_ON_ERROR);
$ids=$report['archived_teacher_ids'];
if(User::whereIn('id',$ids)->exists() || Schedule::whereIn('teacher_id',$ids)->exists()) throw new RuntimeException('Archived demo teachers are still visible or have active lessons.');
if($allSamples && User::where('role','teacher')->where(fn($q)=>$q->where('name','like','Sample %')->orWhere('name','like','Fictional %')->orWhere('profile_number','like','DEMO-%')->orWhere('email','like','demo-timetable-%'))->exists()) throw new RuntimeException('A sample teacher is still in the roster.');
foreach($backup['tables']['users'] as $user) if(!in_array($user['id'],$ids,true)) {
    $current=DB::table('users')->find($user['id']);
    if(!$current) throw new RuntimeException('An original user account was deleted.');
    foreach($user as $field=>$value) if($current->$field!==$value) throw new RuntimeException('An original user account changed.');
}
$students=DB::table('students')->get()->keyBy('id');
if($students->count()!==count($backup['tables']['students'])) throw new RuntimeException('Student count changed.');
foreach($backup['tables']['students'] as $row) foreach($row as $field=>$value) if($students[$row['id']]->$field!==$value) throw new RuntimeException('Student enrollment changed.');
foreach($report['reassigned'] as $row) app(LessonManagementService::class)->validate(Schedule::findOrFail($row['new_lesson_id']));
foreach($report['unfilled'] as $row) {
    $old=Schedule::withoutGlobalScope('active')->findOrFail($row['old_lesson_id']);
    if(!$old->cancelled_at || !$old->teacher || !in_array($old->teacher_id,$ids,true)) throw new RuntimeException('Cancelled demo lesson history is incomplete.');
}
$description=$allSamples?'All remaining identified sample teacher accounts, including the earlier sample-named accounts, have been archived and excluded from normal teacher lists and sign-in. No fictional replacement accounts were created. Remaining existing records have not been independently verified as employees.':'The extra demo teacher accounts have been archived and are excluded from normal teacher lists and sign-in. The original roster, including its earlier sample-named teachers, is preserved.';
$lines=['# '.($allSamples?'All sample teachers removed':'Extra demo teachers removed'),'',$description.' Old lessons remain as cancelled history with their original teacher names.','','| Result | Count |','|---|---|','|Sample accounts removed in this operation|'.$report['removed'].'|','|Lessons reassigned to qualified original teachers|'.$report['reassigned_lessons'].'|','|Lessons still needing staff from this removal|'.$report['unfilled_lessons'].'|'];
foreach($report['remaining_teachers'] as $program=>$count) $lines[]='|Remaining '.$program.' teachers|'.$count.'|';
$lines=array_merge($lines,['','## Lessons still needing staff','','These are genuine vacancies after demo staff removal. Existing curriculum targets, the 20-hour teaching limit, student enrollment and the Khmer two-subject rule were retained.','','| Class | Subject | Day | Time |','|---|---|---|---|']);
foreach($report['unfilled'] as $row) $lines[]='|'.$row['class'].'|'.$row['subject'].'|'.$row['day'].'|'.$row['start'].'–'.$row['end'].'|';
$lines=array_merge($lines,['','Backup: '.$report['backup']]);
$document=$allSamples?'docs/all-sample-teachers-removed.md':'docs/extra-demo-teachers-removed.md';
file_put_contents(__DIR__.'/../'.$document,implode(PHP_EOL,$lines).PHP_EOL);
echo json_encode(['verified'=>true,'removed'=>$report['removed'],'remaining_teachers'=>$report['remaining_teachers'],'reassigned'=>$report['reassigned_lessons'],'unfilled'=>$report['unfilled_lessons'],'student_enrollment'=>'preserved','history'=>'preserved','report'=>$document],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
