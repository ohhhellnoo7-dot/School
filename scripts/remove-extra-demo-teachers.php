<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!in_array('--apply',$argv,true)) throw new RuntimeException('Use --apply to remove only the extra demo teachers while preserving the original roster.');
$report=app(\App\Services\DemoTeacherRemovalService::class)->removeExtras();
unset($report['archived_teachers'],$report['archived_teacher_ids'],$report['reassigned'],$report['unfilled']);
echo json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
