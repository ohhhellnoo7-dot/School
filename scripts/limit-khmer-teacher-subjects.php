<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$service = app(\App\Services\KhmerTeacherSubjectLimit::class);
$plan = app(\App\Services\TeacherManagementService::class)->locked(fn () => $service->preview());
$report = in_array('--apply', $argv, true) ? $service->apply($plan) : $plan;
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).PHP_EOL;
