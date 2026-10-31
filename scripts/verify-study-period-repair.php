<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Schedule;
use App\Services\{ClassroomAvailabilityService, LessonManagementService, TeacherManagementService};
use Illuminate\Support\Facades\{DB, Storage};

$disk = Storage::disk('local');
$path = collect($disk->files('timetable-drafts'))->filter(fn ($p) => str_contains($p, '/fill-study-periods-applied-'))->sort()->last();
if (! $path) throw new RuntimeException('No study-period repair report found.');
$report = json_decode($disk->get($path), true, 512, JSON_THROW_ON_ERROR);
$backup = json_decode($disk->get($report['backup']), true, 512, JSON_THROW_ON_ERROR);
foreach ($backup['tables'] as $table => $rows) {
    $key = $table === 'teacher_profiles' ? 'teacher_id' : 'id';
    $current = DB::table($table)->get()->keyBy($key);
    foreach ($rows as $row) {
        $record = $current->get($row[$key]);
        if (! $record) throw new RuntimeException('Original record removed: '.$table);
        foreach ($row as $column => $value) if ($record->$column !== $value) throw new RuntimeException('Original record changed: '.$table.' '.$row[$key].' '.$column);
    }
}
$service = app(LessonManagementService::class);
foreach ($report['added'] as $entry) {
    $lesson = Schedule::findOrFail($entry['id']);
    $service->validate($lesson);
    $teacherLessons = app(TeacherManagementService::class)->lessons($lesson->teacher);
    if (! $teacherLessons->contains('id', $lesson->id)) throw new RuntimeException('Teacher timetable link failed.');
    $date = \Carbon\CarbonImmutable::parse('2026-11-02')->addDays(array_search($entry['day'], TeacherManagementService::DAYS, true))->format('Y-m-d');
    $snapshot = app(ClassroomAvailabilityService::class)->forDateAndTime($date, $entry['start'])->firstWhere('classroom.id', $lesson->classroom_id);
    if ($snapshot['status'] !== 'busy' || $snapshot['currentLesson']?->id !== $lesson->id) throw new RuntimeException('Room timetable link failed.');
}
$lines = ['# Study timetable repair', '', 'Added '.$report['added_count'].' shared lessons for 2026–2027. Original school records preserved. Every added lesson passed teacher, student cohort, room, duration, availability and workload checks.', '', '## Khmer 10A Morning', '', '| Day | Time | Subject | Teacher |', '|---|---|---|---|'];
foreach (Schedule::with(['subject','teacher'])->where('school_class_id',4)->where('academic_year','2026-2027')->get()->sortBy(fn ($s) => [array_search($s->day, TeacherManagementService::DAYS, true),$s->starts_at]) as $lesson) $lines[] = '|'.$lesson->day.'|'.substr($lesson->starts_at,0,5).'–'.substr($lesson->ends_at,0,5).'|'.$lesson->subject->name.'|'.$lesson->teacher->name.'|';
$lines = array_merge($lines, ['', '## Remaining setup and conflicts', '', 'Unfilled hourly periods: '.$report['blocked_count'].'. Counts below are by class/session; some cohorts may share a physical reservation.', '', '| Class | Unfilled hours | Reasons |', '|---|---|---|']);
foreach (collect($report['blocked'])->groupBy('class_id') as $entries) $lines[] = '|'.$entries->first()['class'].'|'.$entries->count().'|'.implode('; ', $entries->pluck('reasons')->flatten()->unique()->all()).'|';
file_put_contents(__DIR__.'/../docs/study-timetable-repair.md', implode(PHP_EOL,$lines).PHP_EOL);
echo 'PASS: '.$report['added_count'].' new lessons validated; all original rows preserved; teacher/student/room links verified.'.PHP_EOL;
echo 'Report: docs/study-timetable-repair.md'.PHP_EOL;
