<?php

namespace App\Support;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TeacherTimetable
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    public const BLOCKS = [
        ['Morning', '07:00', '09:00'], ['Morning', '09:00', '11:00'],
        ['Afternoon', '13:00', '15:00'], ['Afternoon', '15:00', '16:00'],
        ['Evening', '16:30', '18:30'],
    ];

    public static function withinHours(Subject $subject, string $start, string $end): bool
    {
        $class = $subject->schoolClass;
        foreach (SchoolClass::SESSION_HOURS as $session => [$from, $to]) {
            if ($class->session && $class->session !== $session) continue;
            if (($class->program === 'Chinese' || (! $class->program && $subject->teacher?->program === 'Chinese')) && $session !== 'Evening') continue;
            if (($class->program === 'Khmer' || (! $class->program && $subject->teacher && ($subject->teacher->program ?: 'Khmer') === 'Khmer')) && $session === 'Evening') continue;
            if (substr($start, 0, 5) >= $from && substr($end, 0, 5) <= $to && $start < $end) return true;
        }
        return false;
    }

    /** Preserve existing records and generate only from configured class subjects. */
    public static function generate(): int
    {
        return app(\App\Services\AutomaticTimetableGenerator::class)->generate()->count();
    }
}
