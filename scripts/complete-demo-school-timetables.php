<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!in_array('--apply',$argv,true)) throw new RuntimeException('Use --apply only when sample teachers and the school-wide timetable repair are authorized.');
$year=now(config('classrooms.timezone'))->year.'-'.(now(config('classrooms.timezone'))->year+1);
$report=app(\App\Services\DemoSchoolTimetableRepair::class)->apply($year,fn($class,$lessons,$teachers)=>print('Completed '.$class.'; lessons '.$lessons.'; new sample teachers '.$teachers.PHP_EOL));
echo json_encode(['cohorts'=>$report['cohort_count'],'shared_class_links'=>count($report['shared_timetables']),'new_class_sessions'=>count($report['new_class_sessions']),'new_sample_teachers'=>count($report['new_sample_teachers']),'new_lessons'=>count($report['saved_lesson_ids']),'preserved_lessons'=>count($report['preserved_lesson_ids']),'cancelled_legacy_lessons'=>$report['cancelled_legacy_lessons'],'report'=>$report['report'],'backup'=>$report['backup']],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
