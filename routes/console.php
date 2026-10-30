<?php

use App\Support\SampleSchoolVerifier;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('school:khmer-timetable-draft {--year=}', function () {
    $draft = app(\App\Services\KhmerTimetableDraft::class)->prepare($this->option('year') ?: null);
    $path = 'timetable-drafts/khmer-'.now()->format('Ymd-His').'.json';
    if (! \Illuminate\Support\Facades\Storage::disk('local')->put($path, json_encode($draft, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) throw new \RuntimeException('Could not save draft report.');
    $this->info('Review artifact: '.$path.'; status: '.$draft['status']);
    $this->line(count($draft['blockers']).' blockers. No live timetable was changed.');
    foreach (array_slice($draft['blockers'], 0, 12) as $blocker) $this->warn($blocker['message']);
    return $draft['status'] === 'blocked' ? 1 : 0;
})->purpose('Prepare a read-only complete Khmer draft or a report of blocking prerequisites; never publishes');

Artisan::command('school:generate-teacher-schedules', function () {
    $backup = 'app/private/timetable-backup-'.now()->format('Ymd-His').'.json';
    \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname(storage_path($backup)));
    file_put_contents(storage_path($backup), \App\Models\Schedule::all()->toJson(JSON_PRETTY_PRINT));
    $count = \App\Support\TeacherTimetable::generate();
    $this->info("Created {$count} weekly lessons. Previous timetable: storage/{$backup}");
})->purpose('Back up and rebuild teacher schedules with class rotations and evening-only Chinese lessons');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('school:verify-sample', function () {
    $report = SampleSchoolVerifier::report();
    $this->table(['Dataset', 'Count'], collect($report['counts'])->map(fn ($count, $label) => [$label, $count])->values()->all());
    foreach ($report['checks'] as $label => $passed) {
        if ($passed) {
            $this->info('PASS: '.$label);
        } else {
            $this->error('FAIL: '.$label);
        }
    }

    return in_array(false, $report['checks'], true) ? 1 : 0;
})->purpose('Read-only verification of the fictional Cambodian school dataset');
