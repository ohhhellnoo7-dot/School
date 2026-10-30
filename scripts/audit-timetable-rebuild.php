<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Schedule;
use App\Services\TeacherManagementService;
use Illuminate\Support\Facades\{DB, Schema};
$lessons = Schedule::with('schoolClass')->orderBy('id')->get();
$hashes = [];
foreach (['users', 'students', 'school_classes', 'class_groups', 'class_sessions', 'classrooms', 'classroom_bookings', 'subjects', 'schedules'] as $table) $hashes[$table] = hash('sha256', DB::table($table)->orderBy('id')->get()->toJson());
if (in_array('--verify', $argv, true)) {
    $baseline = json_decode(ltrim(file_get_contents(__DIR__.'/../storage/app/qa/timetable-rebuild-baseline.json'), "\xEF\xBB\xBF"), true, 512, JSON_THROW_ON_ERROR);
    foreach ($hashes as $table => $hash) if (!hash_equals($baseline['hashes'][$table], $hash)) throw new RuntimeException('Original school records changed: '.$table);
    echo 'PASS: every original column in all nine school tables is unchanged.'.PHP_EOL;
    exit;
}
$pairs = ['teacher' => [], 'cohort' => [], 'physical_room' => []];
foreach ($lessons as $index => $a) foreach ($lessons->slice($index + 1) as $b) {
    if ($a->day !== $b->day || !TeacherManagementService::overlaps($a->starts_at, $a->ends_at, $b->starts_at, $b->ends_at)) continue;
    if ($a->teacher_id && $a->teacher_id === $b->teacher_id) $pairs['teacher'][] = [$a->id, $b->id];
    if ($a->school_class_id && $a->school_class_id === $b->school_class_id) $pairs['cohort'][] = [$a->id, $b->id];
    if ($a->classroom_id && $a->classroom_id === $b->classroom_id) $pairs['physical_room'][] = [$a->id, $b->id];
}
echo json_encode([
    'driver' => DB::getDriverName(),
    'hashes' => $hashes,
    'lesson_columns' => Schema::getColumnListing('schedules'),
    'room_correction_columns' => Schema::hasTable('lesson_room_corrections') ? Schema::getColumnListing('lesson_room_corrections') : [],
    'active_lessons' => $lessons->count(),
    'missing_physical_room_ids' => $lessons->whereNull('classroom_id')->count(),
    'overlapping_pairs' => array_map('count', $pairs),
    'examples' => array_map(fn ($v) => array_slice($v, 0, 5), $pairs),
    'program_sessions' => DB::table('class_sessions')->join('class_groups', 'class_groups.id', '=', 'class_sessions.class_group_id')->select('program', 'session', 'starts_at', 'ends_at')->distinct()->orderBy('program')->orderBy('starts_at')->get(),
    'room_examples' => $lessons->map(fn ($s) => ['lesson' => $s->id, 'program' => $s->schoolClass?->program, 'cohort' => $s->school_class_id, 'recorded_room' => $s->room, 'classroom_id' => $s->classroom_id])->take(8),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
