<?php

namespace App\Support;

use App\Models\Subject;

class KhmerLessonDuration
{
    public static function minutes(Subject $subject): int
    {
        if ($subject->schoolClass?->program !== 'Khmer') return (int) $subject->lesson_minutes;
        $name = mb_strtolower(preg_replace('/\s+/u', ' ', trim($subject->name)));
        // The existing catalog calls the Khmer language/literature subject "Khmer".
        return in_array($name, ['mathematics', 'khmer literature', 'khmer', 'គណិតវិទ្យា', 'អក្សរសាស្ត្រខ្មែរ'], true) ? 120 : 60;
    }

    public static function validateWeeklyHours(Subject $subject, int $minutes): void
    {
        if ($subject->schoolClass?->program === 'Khmer' && ($minutes < 0 || $minutes % self::minutes($subject) !== 0)) {
            app(\App\Services\TeacherManagementService::class)->fail($subject->name.' requires complete '.(self::minutes($subject) / 60).'-hour lesson blocks; the configured weekly hours cannot be split into those blocks.');
        }
    }
}
