<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Storage;
$plan = json_decode(Storage::disk('local')->get('timetable-drafts/khmer-curriculum-repair-solved.json'), true, 512, JSON_THROW_ON_ERROR);
$plan['preserve_existing'] = true;
$result = app(App\Services\KhmerCurriculumTimetablePublisher::class)->publish($plan);
$path = 'timetable-drafts/khmer-curriculum-repair-published-'.now()->format('Ymd-His').'.json';
Storage::disk('local')->put($path, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result + ['report' => $path], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
