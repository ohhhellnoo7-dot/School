<?php

// Read-only simulation: retain the current timetable until a reviewed replacement is approved.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule, SchoolClass};
use App\Services\{KhmerClassTimetableReport, RotationTimetableGenerator, TeacherManagementService};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

$report = app(TeacherManagementService::class)->locked(function () {
    $class = SchoolClass::with('subjects')->where('program', 'Khmer')->where('name', 'Grade 10')->where('section', 'B')->where('session', 'Morning')->sole();
    $template = SchoolClass::with('subjects')->where('program', 'Khmer')->where('name', 'Grade 10')->where('section', 'A')->where('session', 'Morning')->sole();
    $targets = $template->subjects->groupBy('name')->map->sum('required_weekly_minutes');
    $rules = app(TeacherManagementService::class);
    $existing = Schedule::with('subject')->where('school_class_id', $class->id)->get();
    $gaps = [];
    foreach ($rules::DAYS as $day) foreach ([7, 8, 9, 10] as $hour) {
        if (! $existing->contains(fn ($lesson) => $lesson->day === $day && ! $lesson->isRelaxation() && $rules::minute($lesson->starts_at) <= $hour * 60 && $rules::minute($lesson->ends_at) >= ($hour + 1) * 60)) {
            $gaps[] = ['day' => $day, 'starts_at' => sprintf('%02d:00', $hour), 'ends_at' => sprintf('%02d:00', $hour + 1)];
        }
    }
    $result = ['class_id' => $class->id, 'class' => $class->label, 'status' => 'blocked', 'current_academic_hours' => $existing->where('entry_type', 'academic')->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at)) / 60,
        'current_approved_hours' => $class->subjects->sum('required_weekly_minutes') / 60, 'gaps' => $gaps,
        'proposed_curriculum_source' => $template->label, 'proposed_weekly_hours' => $targets->map(fn ($minutes) => $minutes / 60)->all(), 'requires_curriculum_approval' => true];
    DB::beginTransaction();
    try {
        if ($targets->sum() !== 1200) throw new \RuntimeException('The existing Grade 10A curriculum does not cover a full study week.');
        foreach ($targets as $name => $minutes) if ($minutes > 0 && ! $class->subjects->contains('name', $name)) throw new \RuntimeException('Grade 10B has no approved catalog entry for '.$name.'.');
        DB::table('subjects')->where('school_class_id', $class->id)->update(['required_weekly_minutes' => 0, 'teacher_id' => null]);
        foreach ($targets as $name => $minutes) {
            $subject = $class->subjects->firstWhere('name', $name);
            if ($subject) DB::table('subjects')->where('id', $subject->id)->update(['required_weekly_minutes' => $minutes]);
        }
        $generator = app(RotationTimetableGenerator::class);
        $result['plan'] = $generator->preview($class, true);
        $result['lessons'] = collect($result['plan']['rows'])->map(function ($row) {
            return $row + ['subject' => \App\Models\Subject::findOrFail($row['subject_id'])->name, 'teacher' => \App\Models\User::findOrFail($row['teacher_id'])->name];
        })->all();
        $generator->confirm($result['plan']);
        $validation = app(KhmerClassTimetableReport::class)->report();
        $result['validation'] = collect($validation['identities'])->flatMap(fn ($identity) => $identity['cohorts'])->firstWhere('cohort_id', $class->id);
        if (($result['validation']['status'] ?? null) !== 'valid') throw new \RuntimeException('Replacement failed final validation: '.implode('; ', $result['validation']['issues'] ?? []));
        $result['status'] = 'draft';
    } catch (ValidationException $e) {
        $result['blockers'] = array_merge(...array_values($e->errors()));
    } catch (\RuntimeException $e) {
        $result['blockers'] = [$e->getMessage()];
    } finally {
        DB::rollBack();
    }
    return $result;
});
$path = 'timetable-drafts/khmer-10b-repair-preview.json';
Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$lines = ['# Grade 10B morning — proposed full study timetable', '', 'Status: '.$report['status'].'. Draft only; no live records changed.', '', 'Proposed curriculum: use the existing Grade 10A allocation. This changes Grade 10B from 17 approved subject hours to 20 and requires administrator approval.', '', 'Khmer 4h; Mathematics 4h; Physics 3h; Biology 3h; History 3h; Geography 3h. Existing real teachers only. Teacher breaks remain unchanged.', '', '| Time | Monday | Tuesday | Wednesday | Thursday | Friday |', '| --- | --- | --- | --- | --- | --- |'];
foreach ([7,8,9,10] as $hour) {
    $cells = [sprintf('%02d:00–%02d:00', $hour, $hour + 1)];
    foreach (TeacherManagementService::DAYS as $day) {
        $lesson = collect($report['lessons'] ?? [])->first(fn ($row) => $row['day'] === $day && TeacherManagementService::minute($row['starts_at']) <= $hour * 60 && TeacherManagementService::minute($row['ends_at']) >= ($hour + 1) * 60);
        $cells[] = $lesson ? $lesson['subject'].' — '.$lesson['teacher'].' — Room '.$lesson['room'].' ('.$lesson['starts_at'].'–'.$lesson['ends_at'].')' : 'INCOMPLETE';
    }
    $lines[] = '| '.implode(' | ', $cells).' |';
}
$lines[] = '';
$lines[] = 'Validation: '.($report['validation']['status'] ?? 'not passed').'.';
foreach ($report['blockers'] ?? [] as $blocker) $lines[] = '- '.$blocker;
Storage::disk('local')->put('timetable-drafts/khmer-10b-repair-preview.md', implode(PHP_EOL, $lines).PHP_EOL);
echo json_encode(collect($report)->except(['plan','lessons'])->all(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
echo 'No live lessons, curriculum or teachers were changed. Report: '.Storage::disk('local')->path($path).PHP_EOL;
