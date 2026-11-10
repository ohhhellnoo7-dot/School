<?php

// Read-only audit: identify separate enrolled classes sharing a classroom timetable.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{SchoolClass, Schedule, Classroom};

$classes = SchoolClass::withCount('students')->get()->keyBy('id');
$rooms = Classroom::all()->keyBy('id');
$lessons = Schedule::get()->groupBy('school_class_id');
$issues = $classes->filter(fn ($class) => $class->shared_timetable_class_id)->map(function ($class) use ($classes, $rooms, $lessons) {
    $owner = $classes->get($class->shared_timetable_class_id);
    $ownerLessons = $lessons->get($owner?->id, collect());
    return [
        'program' => $class->program,
        'rooms' => $ownerLessons->pluck('classroom_id')->unique()->map(fn ($id) => $rooms->get($id)?->room_number)->filter()->values()->all(),
        'owner' => ['id' => $owner?->id, 'label' => $owner?->label, 'students' => $owner?->students_count],
        'shared_class' => ['id' => $class->id, 'label' => $class->label, 'students' => $class->students_count],
        'session' => $class->session,
        'times' => $ownerLessons->map(fn ($lesson) => substr($lesson->starts_at, 0, 5).' - '.substr($lesson->ends_at, 0, 5))->unique()->values()->all(),
        'other_classes_in_group' => $classes->where('class_group_id', $class->class_group_id)->map(fn ($cohort) => [
            'id' => $cohort->id, 'section' => $cohort->section, 'session' => $cohort->session, 'students' => $cohort->students_count,
        ])->values()->all(),
    ];
})->values();
$summary = $issues->groupBy('program')->map(fn ($items) => [
    'shared_class_links' => $items->count(),
    'affected_rooms' => $items->flatMap(fn ($item) => $item['rooms'])->unique()->values()->all(),
]);
$path = storage_path('app/private/room-repairs/session-cohort-audit-'.now()->format('Ymd-His').'.json');
if (! is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
file_put_contents($path, json_encode(['summary' => $summary, 'issues' => $issues], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
echo json_encode($summary, JSON_PRETTY_PRINT).PHP_EOL;
echo 'Report: '.$path.PHP_EOL;
