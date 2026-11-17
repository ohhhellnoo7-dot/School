<?php
// Reconcile the confirmed A-first/B-second policy applied by separate-chinese-evening-cohorts.php.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{Schedule, SchoolClass};
use App\Services\{TeacherManagementService, TimetableBackup};
use Illuminate\Support\Facades\{DB, Storage};
$report = app(TeacherManagementService::class)->locked(function () {
    $classes = SchoolClass::with('subjects')->where('program', 'Chinese')->where('session', 'Evening')->whereNull('shared_timetable_class_id')->get();
    $changes = [];
    $subjectsByDay = ['Monday' => 'Chinese Vocabulary', 'Tuesday' => 'Chinese Reading', 'Wednesday' => 'Chinese Writing', 'Thursday' => 'Chinese Listening', 'Friday' => 'Chinese Speaking'];
    foreach ($classes as $class) {
        if (! in_array($class->section, ['A', 'B'], true)) throw new RuntimeException('Unexpected Chinese cohort '.$class->id);
        [$start, $end] = $class->section === 'A' ? ['17:30', '18:30'] : ['18:30', '19:30'];
        $lessons = Schedule::with('subject')->where('school_class_id', $class->id)->where('academic_year', '2026-2027')->get();
        if ($lessons->count() !== 5) throw new RuntimeException('Expected five confirmed split-period lessons for '.$class->id);
        foreach ($subjectsByDay as $day => $name) {
            $lesson = $lessons->where('day', $day)->sole();
            if ($lesson->subject?->name !== $name || substr($lesson->starts_at, 0, 5) !== $start || substr($lesson->ends_at, 0, 5) !== $end) throw new RuntimeException('Unexpected split-period lesson for '.$class->id.' '.$day);
            $subject = $lesson->subject;
            if (! in_array((int) $subject->required_weekly_minutes, [60, 120], true)) throw new RuntimeException('Unexpected curriculum setting for '.$subject->id);
            if ((int) $subject->required_weekly_minutes !== 60) $changes[] = ['subject_id' => $subject->id, 'class_id' => $class->id, 'before' => $subject->required_weekly_minutes, 'after' => 60];
        }
        if ($class->subjects->whereNotIn('id', $lessons->pluck('subject_id'))->sum('required_weekly_minutes') !== 0) throw new RuntimeException('Additional Chinese curriculum needs review for '.$class->id);
    }
    $backup = app(TimetableBackup::class)->create();
    foreach ($changes as $change) DB::table('subjects')->where('id', $change['subject_id'])->update(['required_weekly_minutes' => $change['after'], 'updated_at' => now()]);
    return ['backup' => $backup, 'classes_checked' => $classes->count(), 'changed_subjects' => count($changes), 'changes' => $changes, 'policy' => 'A 17:30–18:30; B 18:30–19:30; one hour per weekday subject'];
});
$path = 'timetable-drafts/chinese-split-curriculum-repair-'.now()->format('Ymd-His').'.json';
Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
unset($report['changes']);
echo json_encode($report + ['report' => $path], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
