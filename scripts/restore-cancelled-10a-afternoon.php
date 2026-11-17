<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule, SchoolClass};
use App\Services\{LessonManagementService, TeacherManagementService};
use Illuminate\Support\Facades\Storage;

$apply = in_array('--apply', $argv, true);
$report = app(TeacherManagementService::class)->locked(function () use ($apply) {
    $class = SchoolClass::where('program', 'Khmer')->where('name', 'Grade 10')->where('section', 'A')->where('session', 'Afternoon')->sole()->timetableOwner();
    $lessons = Schedule::withoutGlobalScope('active')->with('subject')->where('school_class_id', $class->id)->where('day', 'Monday')->orderBy('starts_at')->get();
    $result = ['class_id' => $class->id, 'lessons' => $lessons->map(fn ($lesson) => ['id' => $lesson->id, 'subject' => $lesson->subject?->name, 'starts_at' => $lesson->starts_at, 'ends_at' => $lesson->ends_at, 'academic_year' => $lesson->academic_year, 'cancelled_at' => $lesson->cancelled_at?->toIso8601String()])->all()];
    if (! $apply) return $result;
    $targets = $lessons->filter(fn ($lesson) => $lesson->cancelled_at && substr($lesson->starts_at, 0, 5) === '13:00' && substr($lesson->ends_at, 0, 5) === '15:00' && $lesson->academic_year === '2026-2027');
    if ($targets->count() !== 1) throw new RuntimeException('Expected exactly one cancelled Monday 13:00–15:00 lesson.');
    $lesson = $targets->sole();
    $path = 'timetable-backups/restore-10a-afternoon-'.now()->format('Ymd-His').'.json';
    if (! Storage::disk('local')->put($path, json_encode($lesson->getAttributes(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new RuntimeException('Backup failed.');
    $lesson->cancelled_at = null;
    app(LessonManagementService::class)->validate($lesson);
    Schedule::withoutGlobalScope('active')->whereKey($lesson->id)->update(['cancelled_at' => null, 'updated_at' => now()]);
    if (! Schedule::whereKey($lesson->id)->exists()) throw new RuntimeException('Restoration verification failed.');
    return $result + ['restored_id' => $lesson->id, 'backup' => $path];
});
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
