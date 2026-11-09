<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Support\Collection;

class MatchingClassService
{
    public function find(array $source, Collection $entries): Collection
    {
        $lesson = $source['currentLesson'] ?? null;
        if (! $lesson || $lesson->isRelaxation() || ! $lesson->subject || ! $lesson->schoolClass
            || ! in_array($source['currentTeacherAttendance'] ?? null, ['absent', 'excused'], true)
            || ($source['lessonConflict'] ?? false) || ($source['studyWith'] ?? null)
            || (isset($source['combinedStudies']) && $source['combinedStudies']->isNotEmpty())) return collect();

        return $entries->filter(function ($entry) use ($source, $lesson, $entries) {
            $candidate = $entry['currentLesson'] ?? null;
            if (! $candidate || $candidate->isRelaxation() || ! $candidate->subject || ! $candidate->schoolClass
                || ! $candidate->teacher || ! in_array($candidate->teacher->status, [null, 'active'], true)
                || $entry['classroom']->id === $source['classroom']->id
                || $entry['classroom']->status !== 'active'
                || ($entry['studyWith'] ?? null) || ($entry['lessonConflict'] ?? false) || ($entry['roomMismatch'] ?? false)
                || in_array($entry['currentTeacherAttendance'] ?? null, ['absent', 'excused'], true)
                || $candidate->teacher_id === $lesson->teacher_id
                || $candidate->school_class_id === $lesson->school_class_id) return false;

            return $this->grade($candidate) === $this->grade($lesson)
                && $candidate->schoolClass->program === $lesson->schoolClass->program
                && $candidate->schoolClass->session === $lesson->schoolClass->session
                && mb_strtolower(trim($candidate->subject->name)) === mb_strtolower(trim($lesson->subject->name))
                && TeacherManagementService::minute($candidate->starts_at) <= TeacherManagementService::minute($lesson->starts_at)
                && TeacherManagementService::minute($candidate->ends_at) >= TeacherManagementService::minute($lesson->ends_at)
                && ! $entries->contains(fn ($other) => $other['classroom']->id !== $entry['classroom']->id
                    && ($other['currentLesson']?->teacher_id ?? null) === $candidate->teacher_id);
        })->values();
    }

    private function grade(Schedule $lesson): string
    {
        return (string) ($lesson->schoolClass->classGroup?->grade
            ?? preg_replace('/^grade\s*/i', '', trim($lesson->schoolClass->name)));
    }
}
