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

            return $this->levelsMatch($lesson, $candidate)
                && $candidate->schoolClass->program === $lesson->schoolClass->program
                && $candidate->schoolClass->session === $lesson->schoolClass->session
                && mb_strtolower(trim($candidate->subject->name)) === mb_strtolower(trim($lesson->subject->name))
                && TeacherManagementService::minute($candidate->starts_at) <= TeacherManagementService::minute($lesson->starts_at)
                && TeacherManagementService::minute($candidate->ends_at) >= TeacherManagementService::minute($lesson->ends_at)
                && ! $entries->contains(fn ($other) => $other['classroom']->id !== $entry['classroom']->id
                    && ($other['currentLesson']?->teacher_id ?? null) === $candidate->teacher_id);
        })->values();
    }

    public function allowsAdjacentLevels(?string $program): bool
    {
        // Every newly added non-Khmer program automatically uses this policy.
        return mb_strtolower(trim((string) $program)) !== 'khmer';
    }

    public function levelDifference(Schedule $source, Schedule $candidate): ?int
    {
        $from = $this->level($source);
        $to = $this->level($candidate);
        return $from === null || $to === null ? null : $to - $from;
    }

    private function levelsMatch(Schedule $source, Schedule $candidate): bool
    {
        $difference = $this->levelDifference($source, $candidate);
        if ($difference !== null) {
            return abs($difference) <= ($this->allowsAdjacentLevels($source->schoolClass->program) ? 1 : 0);
        }
        // Without a numbered level, only an exact class-level name can match.
        return mb_strtolower(trim($source->schoolClass->name)) === mb_strtolower(trim($candidate->schoolClass->name));
    }

    private function level(Schedule $lesson): ?int
    {
        $grade = $lesson->schoolClass->classGroup?->grade;
        if ($grade !== null) return (int) $grade;
        if (preg_match('/^(?:(?:grade|level)\s*)?(\d+)$/i', trim($lesson->schoolClass->name), $match)) {
            return (int) $match[1];
        }
        return null;
    }
}
