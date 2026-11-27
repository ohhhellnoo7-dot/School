<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassAttendance;
use App\Models\StudentExcuse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ClassAttendanceReportService
{
    public function forMonth(Collection $classes, Collection $students, CarbonImmutable $month): Collection
    {
        return $this->forDates($classes, $students, $month->startOfMonth()->toDateString(), $month->endOfMonth()->toDateString());
    }

    public function forDates(Collection $classes, Collection $students, string $start, string $end): Collection
    {
        $records = collect();
        $excuses = StudentExcuse::whereIn('student_id', $students->modelKeys())
            ->where('session', '!=', StudentExcuse::RANDOM_SESSION)
            ->overlapping($start, $end)
            ->get()->groupBy('student_id');
        $daily = Attendance::whereIn('student_id', $students->modelKeys())
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)->get()->groupBy('student_id');

        // Daily attendance is the default for every enrolled class. Explicit class marks take priority.
        foreach ($students as $student) {
            foreach ($student->studyClasses->whereIn('id', $classes->modelKeys()) as $class) {
                foreach ($daily->get($student->id, collect()) as $attendance) {
                    $date = $attendance->date->toDateString();
                    if ($attendance->status === 'excused' && $excuses->get($student->id, collect())
                        ->contains(fn ($excuse) => $excuse->starts_on->toDateString() <= $date && $excuse->ends_on->toDateString() >= $date)) {
                        // Guardian excuses have their own marks for the requested classes.
                        continue;
                    }
                    $records->put($class->id.':'.$student->id.':'.$date, new ClassAttendance([
                        'school_class_id' => $class->id, 'student_id' => $student->id,
                        'date' => $date, 'status' => $attendance->status, 'source' => 'daily',
                    ]));
                }
            }
        }

        $overrides = ClassAttendance::whereIn('school_class_id', $classes->modelKeys())
            ->whereIn('student_id', $students->modelKeys())
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end)->get();
        $sessions = $classes->pluck('session', 'id');
        foreach ($overrides as $record) {
            $date = $record->date->toDateString();
            if ($record->status === 'excused' && ! $record->date->isWeekend()
                && $excuses->get($record->student_id, collect())->contains(fn ($excuse) => $excuse->session === $sessions->get($record->school_class_id)
                    && $excuse->starts_on->toDateString() <= $date && $excuse->ends_on->toDateString() >= $date)) {
                $record->source = 'guardian_excuse';
            }
            $records->put($record->school_class_id.':'.$record->student_id.':'.$date, $record);
        }

        return $records;
    }
}
