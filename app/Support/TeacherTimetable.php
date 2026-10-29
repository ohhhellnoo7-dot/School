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
            if (($subject->teacher?->program === 'Chinese' || $class->program === 'Chinese') && $session !== 'Evening') continue;
            if (($class->program === 'Khmer' || ($subject->teacher && ($subject->teacher->program ?: 'Khmer') === 'Khmer')) && $session === 'Evening') continue;
            if (substr($start, 0, 5) >= $from && substr($end, 0, 5) <= $to && $start < $end) return true;
        }
        return false;
    }

    /** Rebuild the weekly timetable atomically; callers back up existing lessons first. */
    public static function generate(): int
    {
        return DB::transaction(function () {
            Schedule::query()->delete();
            $teachers = User::where('role', 'teacher')->orderBy('id')->get();
            foreach ($teachers as $index => $teacher) {
                $program = $teacher->program ?: 'Khmer';
                $names = match ($program) {
                    'Chinese' => ['Chinese language', 'Chinese reading', 'Chinese writing'],
                    'English' => ['English language', 'English reading', 'English writing'],
                    default => array_slice(array_merge($pool = ['Mathematics', 'Khmer', 'Physics', 'Biology', 'History', 'Geography'], $pool), $index % 6, 3),
                };
                foreach (self::DAYS as $dayIndex => $day) {
                    $previous = null;
                    foreach (self::BLOCKS as $blockIndex => [$session, $start, $end]) {
                        if ($program === 'Chinese' && $session !== 'Evening') continue;
                        if ($program === 'Khmer' && $session === 'Evening') continue;
                        $classes = SchoolClass::where('program', $program)
                            ->where(fn ($q) => $q->whereNull('session')->orWhere('session', $session))
                            ->orderBy('name')->orderBy('section')->orderBy('id')->get();
                        $placed = false;
                        for ($attempt = 0; $attempt < $classes->count(); $attempt++) {
                            $class = $classes[($index + $dayIndex + $blockIndex + $attempt) % $classes->count()];
                            if ($class->id === $previous) continue;
                            $candidate = new Subject(['teacher_id' => $teacher->id, 'school_class_id' => $class->id]);
                            $room = $class->room ?: $program.'-'.$class->id;
                            if (TimetableConflicts::exists($candidate, $day, $start, $end, $room)) continue;
                            $name = $names[($dayIndex + $blockIndex) % 3];
                            $subject = Subject::firstOrCreate(['code' => 'TT-'.$teacher->id.'-'.$class->id.'-'.array_search($name, $names)], [
                                'name' => $name, 'school_class_id' => $class->id, 'teacher_id' => $teacher->id,
                            ]);
                            Schedule::create(['subject_id' => $subject->id, 'day' => $day, 'starts_at' => $start, 'ends_at' => $end, 'room' => $room]);
                            $previous = $class->id;
                            $placed = true;
                            break;
                        }
                        if (! $placed) throw new \RuntimeException("Not enough available {$program} classes for {$teacher->name}, {$day} {$start}. Changes rolled back.");
                    }
                }
            }
            return Schedule::count();
        });
    }
}
