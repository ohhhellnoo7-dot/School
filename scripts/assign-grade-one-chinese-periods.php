<?php

// Split the confirmed Grade 1 Chinese evening cohorts without changing enrollments or attendance.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Classroom;
use App\Models\SchoolClass;
use App\Models\Schedule;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    $room = Classroom::where('room_number', '7A')->sole();
    $a = SchoolClass::where('program', 'Chinese')->where('session', 'Evening')->where('name', 'Grade 1')->where('section', 'A')->lockForUpdate()->sole();
    $b = SchoolClass::where('program', 'Chinese')->where('session', 'Evening')->where('name', 'Grade 1')->where('section', 'B')->lockForUpdate()->sole();
    $lessons = Schedule::where('classroom_id', $room->id)->where('academic_year', '2026-2027')->whereIn('school_class_id', [$a->id, $b->id])->lockForUpdate()->get();
    if (! $b->shared_timetable_class_id && $lessons->where('school_class_id', $a->id)->every(fn ($s) => substr($s->starts_at, 0, 5) === '17:30') && $lessons->where('school_class_id', $b->id)->count() === 5) {
        echo "Already assigned.\n";
        return;
    }
    throw_unless((int) $b->shared_timetable_class_id === $a->id && $lessons->count() === 10 && $lessons->where('school_class_id', $a->id)->count() === 10, new RuntimeException('Unexpected timetable: review before applying.'));
    $later = $lessons->filter(fn ($s) => substr($s->starts_at, 0, 5) === '18:30' && substr($s->ends_at, 0, 5) === '19:30');
    $earlier = $lessons->filter(fn ($s) => substr($s->starts_at, 0, 5) === '17:30' && substr($s->ends_at, 0, 5) === '18:30');
    throw_unless($later->count() === 5 && $earlier->count() === 5, new RuntimeException('Expected five lessons per period.'));
    $subjects = $later->mapWithKeys(function ($lesson) use ($b) {
        $source = Subject::findOrFail($lesson->subject_id);
        $target = Subject::where('school_class_id', $b->id)->where('name', $source->name)->sole();
        return [$lesson->id => $target->id];
    });
    $backup = storage_path('app/private/room-repairs/chinese-grade-one-periods-'.now()->format('Ymd-His').'.json');
    if (! is_dir(dirname($backup))) mkdir(dirname($backup), 0755, true);
    file_put_contents($backup, json_encode(['classes' => [$a->toArray(), $b->toArray()], 'schedules' => $lessons->toArray()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $b->update(['shared_timetable_class_id' => null]);
    foreach ($later as $lesson) {
        DB::table('schedules')->where('id', $lesson->id)->update(['school_class_id' => $b->id, 'subject_id' => $subjects[$lesson->id], 'updated_at' => now()]);
    }
    echo "Assigned A 17:30-18:30 and B 18:30-19:30 in Room 7A. Backup: ".$backup."\n";
});

foreach ([1 => 'A', 2 => 'B'] as $period => $section) {
    $entry = app(App\Services\ClassroomAvailabilityService::class)->forMoment('2026-11-10 17:30', ['academic_year' => '2026-2027', 'detail_program' => 'Chinese', 'detail_session' => 'Evening', 'detail_evening_period' => $period])->first(fn ($row) => $row['classroom']->room_number === '7A');
    $ids = $entry['programDetails']['Chinese']['class_ids'];
    $classes = SchoolClass::whereIn('id', $ids)->get();
    throw_unless($classes->count() === 1 && $classes->first()->section === $section, new RuntimeException('Period verification failed.'));
    echo "Verified period ".$period.": Grade 1 ".$section." only.\n";
}
