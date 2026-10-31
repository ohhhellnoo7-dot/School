<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!in_array('--apply',$argv,true)) throw new RuntimeException('Use --apply to add twenty Khmer teacher records with generated, unverified names.');
$report=app(\App\Services\KhmerTeacherRosterAddition::class)->apply();
unset($report['created_teachers']);
echo json_encode($report,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
