<?php

// Apply the confirmed A-first/B-second Chinese evening policy to existing shared cohorts.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{SchoolClass, Schedule, Subject};
use Illuminate\Support\Facades\DB;

$result = app(App\Services\TeacherManagementService::class)->locked(function () {
    $aliases = SchoolClass::where('program', 'Chinese')->where('session', 'Evening')->whereNotNull('shared_timetable_class_id')->lockForUpdate()->get();
    $plan = [];
    foreach ($aliases as $b) {
        $a = SchoolClass::findOrFail($b->shared_timetable_class_id);
        throw_unless($a->section === 'A' && $b->section === 'B' && $a->session === 'Evening' && ! $a->shared_timetable_class_id, new RuntimeException('Unexpected shared cohort '.$b->id));
        throw_unless(SchoolClass::where('shared_timetable_class_id', $a->id)->count() === 1, new RuntimeException('Multiple aliases for '.$a->id));
        $lessons = Schedule::where('school_class_id', $a->id)->where('academic_year', '2026-2027')->lockForUpdate()->get();
        $early = $lessons->filter(fn ($s) => substr($s->starts_at, 0, 5) === '17:30' && substr($s->ends_at, 0, 5) === '18:30');
        $late = $lessons->filter(fn ($s) => substr($s->starts_at, 0, 5) === '18:30' && substr($s->ends_at, 0, 5) === '19:30');
        throw_unless($lessons->count() === 10 && $early->count() === 5 && $late->count() === 5 && $lessons->pluck('classroom_id')->unique()->count() === 1, new RuntimeException('Unexpected lesson pattern for '.$a->id));
        $subjectMap = $late->mapWithKeys(function ($lesson) use ($b) {
            $subject = Subject::findOrFail($lesson->subject_id);
            $target = Subject::where('school_class_id', $b->id)->where('name', $subject->name)->sole();
            return [$lesson->id => $target->id];
        });
        $plan[] = compact('a', 'b', 'lessons', 'late', 'subjectMap');
    }
    $backup = storage_path('app/private/room-repairs/chinese-all-periods-'.now()->format('Ymd-His').'.json');
    if (! is_dir(dirname($backup))) mkdir(dirname($backup), 0755, true);
    file_put_contents($backup, json_encode(array_map(fn ($item) => ['classes' => [$item['a']->toArray(), $item['b']->toArray()], 'lessons' => $item['lessons']->toArray()], $plan), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    foreach ($plan as $item) {
        $item['b']->update(['shared_timetable_class_id' => null]);
        foreach ($item['late'] as $lesson) {
            DB::table('schedules')->where('id', $lesson->id)->update(['school_class_id' => $item['b']->id, 'subject_id' => $item['subjectMap'][$lesson->id], 'updated_at' => now()]);
        }
    }
    echo 'Backup: '.$backup.PHP_EOL;
    return collect($plan)->map(fn ($item) => ['room_id' => $item['lessons']->first()->classroom_id, 'A' => $item['a']->id, 'B' => $item['b']->id]);
});

$service = app(App\Services\ClassroomAvailabilityService::class);
foreach ([1 => 'A', 2 => 'B'] as $period => $section) {
    $entries = $service->forMoment('2026-11-10 17:30', ['academic_year' => '2026-2027', 'detail_program' => 'Chinese', 'detail_session' => 'Evening', 'detail_evening_period' => $period]);
    foreach ($result as $item) {
        $entry = $entries->first(fn ($row) => $row['classroom']->id === $item['room_id']);
        $actual = collect(data_get($entry, 'programDetails.Chinese.class_ids', []))->all();
        throw_unless($actual === [$item[$section]], new RuntimeException('Verification failed for room '.$item['room_id'].' period '.$period));
    }
}
echo 'Updated and verified '.$result->count().' Chinese rooms: A first period, B second period.'.PHP_EOL;
