<?php

namespace App\Services;

use App\Models\{Schedule, Subject, User};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

/** Removes excess Khmer teaching assignments without deleting school records. */
class KhmerTeacherSubjectLimit
{
    public function preview(): array
    {
        $rules = app(TeacherManagementService::class);
        $changes = []; $blocked = [];
        foreach (User::where('role', 'teacher')->where('program', 'Khmer')->orderBy('id')->get() as $teacher) {
            $subjects = Subject::with('schoolClass')->where('teacher_id', $teacher->id)->orderBy('id')->get();
            $lessons = Schedule::with(['subject', 'schoolClass'])->where('teacher_id', $teacher->id)->get();
            $names = $subjects->pluck('name')->merge($lessons->pluck('subject.name'))->filter()->unique()->values();
            if ($names->count() <= 2) continue;
            // Other programs must retain both their assignments and their lessons.
            $protected = $subjects->filter(fn ($s) => $s->schoolClass?->program !== 'Khmer')->pluck('name')
                ->merge($lessons->filter(fn ($s) => $s->schoolClass?->program !== 'Khmer')->pluck('subject.name'))->filter()->unique()->values();
            if ($protected->count() > 2) {
                $blocked[] = ['teacher_id' => $teacher->id, 'teacher' => $teacher->name, 'reason' => 'More than two subjects belong to other programs; those assignments were preserved.'];
                continue;
            }
            $priority = $rules->qualifications($teacher)->filter(fn ($name) => $names->contains($name));
            $remaining = $names->sortByDesc(fn ($name) => $lessons->filter(fn ($s) => $s->subject?->name === $name)->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at)));
            $keep = $protected->merge($priority)->merge($remaining)->unique()->take(2)->values();
            $remove = $names->diff($keep)->values();
            $unassign = $subjects->filter(fn ($s) => $s->schoolClass?->program === 'Khmer' && $remove->contains($s->name))->modelKeys();
            $cancel = $lessons->filter(fn ($s) => $s->schoolClass?->program === 'Khmer' && $remove->contains($s->subject?->name))->modelKeys();
            $changes[] = ['teacher_id' => $teacher->id, 'teacher' => $teacher->name, 'keep' => $keep->all(), 'remove' => $remove->all(), 'subject_ids' => array_values($unassign), 'lesson_ids' => array_values($cancel)];
        }
        return ['fingerprint' => app(RotationTimetableGenerator::class)->fingerprint(), 'changes' => $changes, 'blocked' => $blocked];
    }

    public function apply(array $plan): array
    {
        return app(TeacherManagementService::class)->locked(function () use ($plan) {
            $current = $this->preview();
            if ($plan !== $current) app(TeacherManagementService::class)->fail('Teaching assignments changed. Preview the subject-limit correction again.');
            $backup = app(TimetableBackup::class)->create();
            $subjects = $lessons = 0;
            foreach ($plan['changes'] as $change) {
                // Cancellation preserves the original teacher and complete lesson history.
                $lessons += Schedule::whereIn('id', $change['lesson_ids'])->update(['cancelled_at' => now()]);
                // Do not propagate a null teacher into a qualified substitute's lessons.
                $subjects += DB::table('subjects')->whereIn('id', $change['subject_ids'])->where('teacher_id', $change['teacher_id'])->update(['teacher_id' => null, 'updated_at' => now()]);
            }
            $report = $plan + ['backup' => $backup, 'removed_assignments' => $subjects, 'cancelled_lessons' => $lessons, 'applied_at' => now()->toIso8601String()];
            $path = 'timetable-drafts/teacher-subject-limit-'.Str::uuid().'.json';
            if (! Storage::disk('local')->put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))) throw new \RuntimeException('Could not save assignment correction report. Changes rolled back.');
            return $report + ['report' => $path];
        });
    }
}
