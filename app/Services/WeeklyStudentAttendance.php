<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\AttendanceSession;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WeeklyStudentAttendance
{
    public function dashboard(Request $request): array
    {
        $filters = $request->validate([
            'attendance_week' => ['nullable', 'date_format:Y-m-d'],
            'weekly_program' => ['nullable', 'string', 'max:80'],
            'weekly_grade' => ['nullable', 'integer', 'min:1', 'max:99'],
            'weekly_class' => ['nullable', 'integer', 'min:1'],
            'weekly_session' => ['nullable', Rule::in(AttendanceSession::OPTIONS)],
        ]);
        /** @var User $user */
        $user = $request->user();
        $thisWeek = CarbonImmutable::today()->startOfWeek(CarbonInterface::MONDAY);
        $monday = isset($filters['attendance_week'])
            ? CarbonImmutable::parse($filters['attendance_week'])->startOfWeek(CarbonInterface::MONDAY)
            : $thisWeek;
        $friday = $monday->addDays(4);

        $students = Student::visibleForAttendance($user);
        $classes = SchoolClass::with('classGroup')
            ->when($user->role === 'teacher', fn ($classes) => $classes->scheduledForTeacher($user))
            ->when($user->role !== 'administrator', fn ($classes) => $classes
                ->whereHas('students', fn ($enrolled) => $enrolled->whereIn('students.id', (clone $students)->select('students.id'))))
            ->orderBy('program')->orderBy('name')->orderBy('section')->orderBy('session')->orderBy('id')->get();
        $classGrades = $classes->mapWithKeys(fn (SchoolClass $class) => [$class->id => $this->grade($class)]);
        $programs = $classes->pluck('program')->filter()->unique()->values();
        $grades = $classGrades->filter()->unique()->sort()->values();

        if (array_filter(array_diff_key($filters, ['attendance_week' => true]))) {
            $matched = $classes->filter(fn (SchoolClass $class) =>
                (! isset($filters['weekly_program']) || $class->program === $filters['weekly_program'])
                && (! isset($filters['weekly_grade']) || $classGrades[$class->id] === (int) $filters['weekly_grade'])
                && (! isset($filters['weekly_class']) || $class->id === (int) $filters['weekly_class'])
                && (! isset($filters['weekly_session']) || $class->session === $filters['weekly_session']));
            $students->whereHas('studyClasses', fn ($classes) => $classes->whereKey($matched->modelKeys()));
        }

        $counts = Attendance::query()
            ->whereIn('student_id', $students->select('students.id'))
            ->whereDate('date', '>=', $monday->toDateString())
            ->whereDate('date', '<=', $friday->toDateString())
            ->whereIn('status', ['present', 'late', 'absent', 'excused'])
            ->selectRaw("DATE(date) AS day, COUNT(*) AS recorded, SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) AS attended")
            ->groupByRaw('DATE(date)')->toBase()->get()->keyBy('day');

        $days = collect(range(0, 4))->map(function (int $offset) use ($monday, $counts) {
            $day = $monday->addDays($offset);
            $totals = $counts->get($day->toDateString());
            $recorded = (int) ($totals->recorded ?? 0);
            $attended = (int) ($totals->attended ?? 0);

            return [
                'label' => $day->translatedFormat('l'),
                'shortLabel' => $day->translatedFormat('D'),
                'date' => $day->toDateString(),
                'recorded' => $recorded,
                'attended' => $attended,
                'rate' => $recorded ? round(100 * $attended / $recorded, 2) : null,
            ];
        });

        return compact('filters', 'classes', 'programs', 'grades', 'monday', 'friday', 'thisWeek', 'days');
    }

    private function grade(SchoolClass $class): ?int
    {
        return $class->classGroup?->grade
            ?? (preg_match('/^(?:(?:grade|level)\s*)?(\d{1,2})(?=\b|[A-Za-z]$)/i', trim($class->name), $match) === 1 ? (int) $match[1] : null);
    }
}
