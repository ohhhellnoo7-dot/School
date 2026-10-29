<?php

namespace App\Support;

use App\Models\Schedule;
use App\Models\Subject;

class TimetableConflicts
{
    public static function exists(Subject $subject, string $day, string $start, string $end, string $room, ?int $ignore = null): bool
    {
        return Schedule::where('day', $day)->where('starts_at', '<', $end)->where('ends_at', '>', $start)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))
            ->where(function ($q) use ($subject, $room) {
                $q->where('room', $room)->orWhereHas('subject', function ($q) use ($subject) {
                    $q->where('school_class_id', $subject->school_class_id);
                    if ($subject->teacher_id) {
                        $q->orWhere('teacher_id', $subject->teacher_id);
                    }
                });
            })->exists();
    }
}
