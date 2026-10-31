<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!in_array('--apply',$argv,true)) throw new RuntimeException('Use --apply only following an explicit instruction to confirm the Khmer teacher records as employees.');
echo json_encode(app(\App\Services\TeacherEmployeeConfirmation::class)->confirmKhmerRecords(),JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
