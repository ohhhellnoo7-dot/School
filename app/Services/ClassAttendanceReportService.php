<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ClassAttendanceReportService
{
    public function forMonth(Collection $classes, Collection $students, CarbonImmutable $month): Collection
    {
        $records = collect();
        $daily = Attendance::whereIn('student_id', $students->modelKeys())
            ->whereDate('date', '>=', $month->startOfMonth()->toDateString())
            ->whereDate('date', '<=', $month->endOfMonth()->toDateString())->get()->groupBy('student_id');

        // Daily attendance is the default for every enrolled class. Explicit class marks take priority.
        foreach ($students as $student) {
            foreach ($student->studyClasses->whereIn('id', $classes->modelKeys()) as $class) {
                foreach ($daily->get($student->id, collect()) as $attendance) {
                    $date = $attendance->date->toDateString();
                    $records->put($class->id.':'.$student->id.':'.$date, new ClassAttendance([
                        'school_class_id' => $class->id, 'student_id' => $student->id,
                        'date' => $date, 'status' => $attendance->status, 'source' => 'daily',
                    ]));
                }
            }
        }

        $overrides = ClassAttendance::whereIn('school_class_id', $classes->modelKeys())
            ->whereIn('student_id', $students->modelKeys())
            ->whereDate('date', '>=', $month->startOfMonth()->toDateString())
            ->whereDate('date', '<=', $month->endOfMonth()->toDateString())->get();
        foreach ($overrides as $record) {
            $records->put($record->school_class_id.':'.$record->student_id.':'.$record->date->toDateString(), $record);
        }

        return $records;
    }
}
