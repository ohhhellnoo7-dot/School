<?php

namespace App\Services;

use App\Models\ClassAttendance;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentExcuse;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeachingAttendance
{
    public const STATUSES = ['present', 'absent', 'late', 'excused'];

    public function periods(User $teacher, Carbon $date)
    {
        return Schedule::with(['subject.schoolClass', 'schoolClass', 'classroom.buildingRecord'])
            ->where('teacher_id', $teacher->id)
            ->whereNotNull('subject_id')
            ->where('day', $date->format('l'))
            ->forAcademicYear($date->year.'-'.($date->year + 1))
            ->orderBy('starts_at')->orderBy('id');
    }

    public function roster(Schedule $period)
    {
        $class = $period->schoolClass ?? $period->subject?->schoolClass;

        return Student::where('status', 'active')->enrolledIn([$class?->id]);
    }

    public function records(Schedule $period, Collection $students, string $date)
    {
        $class = $period->schoolClass ?? $period->subject?->schoolClass;

        return app(ClassAttendanceReportService::class)
            ->forDates(new Collection([$class]), $students->load('studyClasses'), $date, $date)
            ->keyBy('student_id');
    }

    public function excuses(Schedule $period, string $date)
    {
        $class = $period->schoolClass ?? $period->subject?->schoolClass;

        return StudentExcuse::where('session', $class?->session)->overlapping($date, $date);
    }

    public function save(User $teacher, Schedule $period, Student $student, string $date, ?string $status): void
    {
        $class = $period->schoolClass ?? $period->subject?->schoolClass;
        abort_unless($class && $this->roster($period)->whereKey($student)->exists(), 403);

        DB::transaction(function () use ($teacher, $class, $period, $student, $date, $status) {
            Student::whereKey($student)->lockForUpdate()->firstOrFail(['id']);
            if (! Carbon::parse($date)->isWeekend() && $this->excuses($period, $date)->where('student_id', $student->id)->exists()) {
                throw ValidationException::withMessages(['status' => __('This student has an approved excuse for this session.')]);
            }

            $key = ['school_class_id' => $class->id, 'student_id' => $student->id, 'date' => $date];
            if ($status === null) {
                ClassAttendance::where($key)->delete();
            } else {
                ClassAttendance::updateOrCreate($key, ['status' => $status, 'recorded_by' => $teacher->id]);
            }
        });
    }
}
