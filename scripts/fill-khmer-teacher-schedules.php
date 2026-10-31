<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{Schedule, User};
use App\Services\{LessonManagementService, TeacherManagementService, TimetableBackup};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

$apply = in_array('--apply', $argv, true);
$year = '2026-2027';
$report = app(TeacherManagementService::class)->locked(function () use ($apply, $year) {
    $rules = app(TeacherManagementService::class);
    $validation = app(LessonManagementService::class);
    $teachers = User::where('role', 'teacher')->where('program', 'Khmer')->where('status', 'active')->get();
    $lessons = Schedule::with(['subject', 'schoolClass'])->where('academic_year', $year)
        ->whereHas('schoolClass', fn ($q) => $q->where('program', 'Khmer'))->where('entry_type', 'academic')->get();
    $empty = $teachers->filter(fn ($t) => !$lessons->contains('teacher_id', $t->id));
    $backup = $apply ? app(TimetableBackup::class)->create() : null;
    $pairs = [];
    foreach ($teachers as $teacher) {
        $pairs[$teacher->id] = $rules->workload($teacher)['subjects']->all();
        if (count($pairs[$teacher->id]) !== 2) throw new RuntimeException('Expected two subjects for '.$teacher->name);
    }
    $changes = [];
    foreach ($empty as $teacher) {
        $periods = $rules->periods($teacher)->map(fn ($p) => collect((array) $p)->only(['weekday', 'start_time', 'end_time', 'unavailable'])->all())->all();
        if (!$periods) throw new RuntimeException('Working hours missing for '.$teacher->name);
        $rules->saveProfile($teacher, $pairs[$teacher->id], $rules->limit($teacher), $periods);
        foreach ($rules::DAYS as $day) {
            $mine = $lessons->where('teacher_id', $teacher->id);
            $counts = $mine->groupBy('subject.name')->map->count();
            $candidates = $lessons->where('day', $day)->filter(fn ($l) => $l->teacher_id !== $teacher->id
                && in_array($l->subject?->name, $pairs[$teacher->id], true)
                && $teachers->contains('id', $l->teacher_id))
                ->sortBy(fn ($l) => ($counts[$l->subject->name] ?? 0) * 1000 - $lessons->where('teacher_id', $l->teacher_id)->count());
            $saved = false;
            foreach ($candidates as $candidate) {
                $donor = $lessons->where('teacher_id', $candidate->teacher_id);
                // Retain each donor's daily teaching and both existing subjects.
                if ($donor->where('day', $day)->count() <= 1 || $donor->where('subject.name', $candidate->subject->name)->count() <= 1) continue;
                $lesson = clone $candidate;
                $previousTeacher = $lesson->teacher_id;
                $lesson->teacher_id = $teacher->id;
                try {
                    $validation->validate($lesson);
                    $lesson->save();
                } catch (ValidationException) { continue; }
                $candidate->teacher_id = $teacher->id;
                $changes[] = ['lesson_id' => $lesson->id, 'from_teacher' => $previousTeacher, 'to_teacher' => $teacher->id, 'subject' => $lesson->subject->name, 'day' => $day, 'starts_at' => $lesson->starts_at, 'ends_at' => $lesson->ends_at];
                $saved = true;
                break;
            }
            if (!$saved) throw new RuntimeException('No conflict-free existing lesson for '.$teacher->name.' on '.$day);
        }
        if ($lessons->where('teacher_id', $teacher->id)->pluck('subject.name')->unique()->count() !== 2) throw new RuntimeException('Both subjects could not be scheduled for '.$teacher->name);
        if ($rules->breaks($teacher)->isEmpty() && $rules->eligibleForBreaks($teacher)) $rules->saveBreaks($teacher, $rules->proposeBreaks($teacher));
    }
    $verified = [];
    foreach ($teachers as $teacher) {
        $mine = $lessons->where('teacher_id', $teacher->id);
        if ($mine->isEmpty()) throw new RuntimeException('Teacher still has no lessons: '.$teacher->name);
        if ($rules->workload($teacher)['subjects']->count() !== 2) throw new RuntimeException('Subject pair changed for '.$teacher->name);
        $verified[] = ['teacher' => $teacher->name, 'lessons' => $mine->count(), 'weekly_hours' => $mine->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at)) / 60];
    }
    foreach ($changes as $change) $validation->validate(Schedule::findOrFail($change['lesson_id']));
    $result = ['applied' => $apply, 'year' => $year, 'teachers_filled' => $empty->pluck('name')->all(), 'lessons_reassigned' => count($changes), 'teachers_verified' => count($verified), 'workloads' => $verified, 'changes' => $changes, 'backup' => $backup];
    if ($apply) {
        $path = 'timetable-drafts/khmer-missing-schedules-'.now()->format('Ymd-His').'.json';
        if (!Storage::disk('local')->put($path, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new RuntimeException('Report could not be saved.');
        $result['report'] = $path;
    } else DB::rollBack();
    return $result;
});
unset($report['changes']);
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
