<?php

namespace App\Services;

use App\Models\{Schedule, SchoolClass, User};

/** An upper bound: passing this check still requires subject and lesson validation. */
class KhmerTeachingCapacity
{
    public function report(): array
    {
        $rules = app(TeacherManagementService::class);
        $classes = SchoolClass::where('program', 'Khmer')->whereNull('shared_timetable_class_id')->get();
        $teachers = User::where('role', 'teacher')->where('status', 'active')->get()
            ->filter(fn ($teacher) => ($teacher->program ?: 'Khmer') === 'Khmer');
        $outside = Schedule::with('subject')->whereNotIn('school_class_id', $classes->modelKeys())->get();
        $required = 0;
        foreach ($classes as $class) {
            [$from, $to] = $class->sessionHours();
            $required += $rules::duration($from, $to) * count($rules::DAYS);
        }
        $available = 0;
        foreach ($teachers as $teacher) {
            if ($rules->qualifications($teacher)->isEmpty()) continue;
            $breaks = $rules->breaks($teacher);
            $commitments = $outside->filter(fn ($lesson) => ($lesson->teacher_id ?? $lesson->subject?->teacher_id) === $teacher->id);
            $minutes = 0;
            foreach ($rules::DAYS as $day) foreach ([7, 8, 9, 10, 13, 14, 15, 16] as $hour) {
                $from = sprintf('%02d:00', $hour);
                $to = sprintf('%02d:00', $hour + 1);
                if (! $rules->onDuty($teacher, $day, $from, $to)) continue;
                if ($breaks->contains(fn ($b) => $b->weekday === $day && $rules::overlaps($from, $to, $b->start_time, $b->end_time))) continue;
                if ($commitments->contains(fn ($lesson) => $lesson->day === $day && $rules::overlaps($from, $to, $lesson->starts_at, $lesson->ends_at))) continue;
                $minutes += 60;
            }
            $used = $commitments->sum(fn ($lesson) => $rules::duration($lesson->starts_at, $lesson->ends_at));
            $available += min($minutes, max(0, $rules->limit($teacher) - $used));
        }

        return ['cohorts' => $classes->count(), 'teachers' => $teachers->count(), 'required_hours' => $required / 60,
            'available_hours' => $available / 60, 'shortage_hours' => max(0, $required - $available) / 60];
    }
}
