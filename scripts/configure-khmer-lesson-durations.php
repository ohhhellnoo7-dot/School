<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Subject;
use App\Services\{TeacherManagementService, TimetableBackup};
use App\Support\KhmerLessonDuration;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

$report = app(TeacherManagementService::class)->locked(function () use ($argv) {
    $subjects = Subject::with('schoolClass')->whereHas('schoolClass', fn ($q) => $q->where('program', 'Khmer'))->orderBy('id')->get();
    $rows = [];
    foreach ($subjects as $subject) {
        $minutes = KhmerLessonDuration::minutes($subject);
        if ((int) $subject->lesson_minutes !== $minutes) $rows[] = ['subject_id' => $subject->id, 'cohort_id' => $subject->school_class_id, 'subject' => $subject->name, 'previous_lesson_minutes' => $subject->lesson_minutes, 'lesson_minutes' => $minutes];
    }
    $report = ['subjects_checked' => $subjects->count(), 'cohorts_checked' => $subjects->pluck('school_class_id')->unique()->count(), 'changes' => $rows, 'applied' => false];
    if (! in_array('--apply', $argv, true)) return $report;
    $report['backup'] = app(TimetableBackup::class)->create();
    foreach ($rows as $row) DB::table('subjects')->where('id', $row['subject_id'])->update(['lesson_minutes' => $row['lesson_minutes'], 'updated_at' => now()]);
    $report['applied'] = true;
    $path = 'timetable-drafts/khmer-duration-settings-'.Str::uuid().'.json';
    if (! Storage::disk('local')->put($path, json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) throw new RuntimeException('Could not save duration settings audit. Changes rolled back.');
    return $report + ['report' => $path];
});
echo json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
