<?php

// Read-only database export. These review sheets never change approved settings.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{ClassGroup, Schedule, SchoolClass, Subject, User};
use App\Services\{TeacherManagementService, TimetableRoomReconciliation};
use Illuminate\Support\Facades\Storage;

$rules = app(TeacherManagementService::class);
$directory = 'timetable-drafts/khmer-review-'.now()->format('Ymd-His');
$write = function (string $name, array $headers, array $rows) use ($directory): void {
    $stream = fopen('php://temp', 'w+');
    // UTF-8 BOM keeps Khmer subject and teacher names readable in spreadsheet apps.
    fwrite($stream, "\xEF\xBB\xBF");
    fputcsv($stream, $headers, ',', '"', '');
    foreach ($rows as $row) {
        // Preserve labels as spreadsheet text, never executable formulas.
        $row = array_map(fn ($cell) => is_string($cell) && preg_match('/^[=+@\-\t\r]/', $cell) ? "'".$cell : $cell, $row);
        fputcsv($stream, $row, ',', '"', '');
    }
    rewind($stream);
    if (! Storage::disk('local')->put($directory.'/'.$name, stream_get_contents($stream))) throw new RuntimeException('Could not save review sheet '.$name);
    fclose($stream);
};

$rules->locked(function () use ($write, $rules) {
    $classes = SchoolClass::with(['subjects.teacher', 'classGroup'])->where('program', 'Khmer')->orderBy('id')->get();
    $lessons = Schedule::whereIn('school_class_id', $classes->modelKeys())->get();
    $curriculum = [];
    foreach ($classes as $class) foreach ($class->subjects as $subject) {
        $scheduled = $lessons->where('subject_id', $subject->id)->sum(fn ($lesson) => $rules::duration($lesson->starts_at, $lesson->ends_at));
        $curriculum[] = [$class->id, $class->class_group_id, $class->classGroup?->grade, $class->section, $class->session, $subject->id, $subject->name, $subject->required_weekly_minutes === null ? '' : $subject->required_weekly_minutes / 60, $scheduled / 60, $subject->teacher_id, $subject->teacher?->name, ''];
    }
    $write('curriculum-review.csv', ['cohort_id', 'class_identity_id', 'grade', 'section', 'session', 'subject_id', 'subject', 'currently_approved_weekly_hours', 'existing_scheduled_hours_NOT_curriculum_approval', 'assigned_teacher_id', 'assigned_teacher', 'administrator_approved_weekly_hours_TO_COMPLETE'], $curriculum);

    $missing = [];
    foreach (ClassGroup::with('sessions')->where('program', 'Khmer')->where('status', 'active')->orderBy('grade')->orderBy('section')->get() as $group) foreach ($group->sessions as $session) {
        if ($classes->where('class_group_id', $group->id)->where('session', $session->session)->count() !== 1) $missing[] = [$group->id, $group->grade, $group->section, $session->id, $session->session, 'Confirm actual student group and enrollment link; do not duplicate the class identity'];
    }
    $write('cohort-links-review.csv', ['class_identity_id', 'grade', 'section', 'session_id', 'session', 'action_required'], $missing);

    $teachers = [];
    foreach (User::where('role', 'teacher')->where('program', 'Khmer')->orderBy('name')->get() as $teacher) {
        $workload = $rules->workload($teacher);
        $teachers[] = [$teacher->id, $teacher->name, Subject::where('teacher_id', $teacher->id)->pluck('name')->unique()->join(' | '), $rules->qualifications($teacher)->join(' | '), $workload['weekly'], $workload['limit'], $rules->breaks($teacher)->count(), $rules->periods($teacher)->count(), ''];
    }
    $write('teacher-assignments-review.csv', ['teacher_id', 'teacher', 'existing_subject_assignments_all_programs', 'approved_qualifications', 'scheduled_weekly_hours', 'maximum_weekly_hours', 'designated_free_periods', 'configured_availability_intervals', 'administrator_correction_notes'], $teachers);

    $reconciliation = app(TimetableRoomReconciliation::class);
    $rooms = [];
    foreach ($lessons->load('schoolClass') as $lesson) {
        $description = $reconciliation->describe($lesson);
        if (! $description['confirmed']) $rooms[] = [$lesson->id, $lesson->school_class_id, $lesson->day, $lesson->starts_at, $lesson->ends_at, $lesson->room, $description['proposed_id'], $description['reason'], ''];
    }
    $write('physical-rooms-review.csv', ['shared_lesson_id', 'cohort_id', 'weekday', 'start', 'end', 'legacy_room_label', 'reservation_proposed_room_id_NOT_confirmed', 'evidence', 'administrator_confirmed_room_id'], $rooms);
});

echo 'Review sheets saved: '.Storage::disk('local')->path($directory).PHP_EOL;
echo 'No schedules or approved settings were changed. Existing lesson hours are evidence only, not approved curriculum.'.PHP_EOL;
