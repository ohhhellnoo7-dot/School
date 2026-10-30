<?php

namespace App\Support;

use App\Models\Schedule;
use App\Models\Subject;

class TimetableConflicts
{
    public static function exists(Subject $subject, string $day, string $start, string $end, string $room, ?int $ignore = null): bool
    {
        if ($subject->teacher_id && app(\App\Services\TeacherManagementService::class)->breaks(\App\Models\User::findOrFail($subject->teacher_id))->contains(fn ($b) => $b->weekday === $day && \App\Services\TeacherManagementService::overlaps($start, $end, $b->start_time, $b->end_time))) return true;
        return Schedule::where('day', $day)->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))
            ->where(function ($q) use ($subject, $room) {
                $q->where('room', $room)
                    ->orWhere('school_class_id', $subject->school_class_id)
                    ->orWhereHas('subject', fn ($related) => $related->where('school_class_id', $subject->school_class_id));

                if ($subject->teacher_id) {
                    $q->orWhere('teacher_id', $subject->teacher_id)
                        ->orWhereHas('subject', fn ($related) => $related->where('teacher_id', $subject->teacher_id));
                }
            })->exists();
    }
}
