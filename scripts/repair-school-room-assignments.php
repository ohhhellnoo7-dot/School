<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;

$service = app(\App\Services\SchoolRoomAssignmentRepair::class);
$apply = in_array('--apply',$argv,true);
$report = $apply ? $service->repairUnambiguous(\App\Models\User::where('role','administrator')->firstOrFail()->id) : ['repaired'=>0,'after'=>$service->scan()];
$path = 'room-repairs/school-room-'.($apply ? 'repaired-' : 'diagnostic-').now()->format('Ymd-His').'.json';
if (! Storage::disk('local')->put($path,json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))) throw new RuntimeException('Could not save room repair report.');
$after = $report['after'];
echo json_encode(['classes_checked'=>$after['total_classes'],'cohorts_checked'=>$after['total_cohorts'],'lessons_checked'=>$after['total_lessons'],'active_lessons'=>$after['active_lessons'],'classroom_assignments_repaired'=>$report['repaired'],'remaining_room_issues'=>$after['affected_lessons'],'safe_corrections'=>count($after['verified']),'manual_review'=>count($after['manual_review']),'conflicts'=>count($after['conflicts']),'issue_categories'=>collect($after['manual_review'])->flatMap(fn ($r)=>$r['issues'])->map(fn ($message)=>preg_replace('/#\d+/','#ID',$message))->countBy()->all(),'backup'=>$report['backup']??null,'report'=>Storage::disk('local')->path($path)],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
