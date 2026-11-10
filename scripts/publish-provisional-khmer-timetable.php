<?php
require __DIR__.'/../vendor/autoload.php'; $app=require __DIR__.'/../bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$plan=json_decode(Illuminate\Support\Facades\Storage::disk('local')->get('timetable-drafts/khmer-provisional-solved.json'),true,512,JSON_THROW_ON_ERROR);
$result=app(App\Services\KhmerCurriculumTimetablePublisher::class)->publish($plan);
Illuminate\Support\Facades\Storage::disk('local')->put('timetable-drafts/khmer-provisional-published-'.now()->format('Ymd-His').'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;
