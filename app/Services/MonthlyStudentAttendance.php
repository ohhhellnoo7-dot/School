<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\AttendanceSession;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonthlyStudentAttendance
{
    public const STATUSES = ['present', 'late', 'absent', 'excused'];

    public function dashboard(Request $request): array
    {
        $filters = $request->validate([
            'weekly_program' => ['nullable', 'string', 'max:80'],
            'weekly_grade' => ['nullable', 'integer', 'min:1', 'max:99'],
            'weekly_class' => ['nullable', 'integer', 'min:1'],
            'weekly_session' => ['nullable', Rule::in(AttendanceSession::OPTIONS)],
        ]);
        /** @var User $user */
        $user = $request->user();
        $today = CarbonImmutable::today();
        $monthStart = $today->startOfMonth();

        $students = Student::visibleForAttendance($user);
        $classes = SchoolClass::with('classGroup')
            ->when($user->role === 'teacher', fn ($classes) => $classes->scheduledForTeacher($user))
            ->when($user->role !== 'administrator', fn ($classes) => $classes
                ->whereHas('students', fn ($enrolled) => $enrolled->whereIn('students.id', (clone $students)->select('students.id'))))
            ->orderBy('program')->orderBy('name')->orderBy('section')->orderBy('session')->orderBy('id')->get();
        $classGrades = $classes->mapWithKeys(fn (SchoolClass $class) => [$class->id => $this->grade($class)]);
        $programs = $classes->pluck('program')->filter()->unique()->values();
        $grades = $classGrades->filter()->unique()->sort()->values();

        if (array_filter($filters)) {
            $matched = $classes->filter(fn (SchoolClass $class) =>
                (! isset($filters['weekly_program']) || $class->program === $filters['weekly_program'])
                && (! isset($filters['weekly_grade']) || $classGrades[$class->id] === (int) $filters['weekly_grade'])
                && (! isset($filters['weekly_class']) || $class->id === (int) $filters['weekly_class'])
                && (! isset($filters['weekly_session']) || $class->session === $filters['weekly_session']));
            $students->whereHas('studyClasses', fn ($classes) => $classes->whereKey($matched->modelKeys()));
        }

        $counts = Attendance::query()
            ->whereIn('student_id', $students->select('students.id'))
            ->whereDate('date', '>=', $monthStart->toDateString())
            ->whereDate('date', '<=', $today->toDateString())
            ->whereIn('status', self::STATUSES)
            ->selectRaw('DATE(date) AS day, status, COUNT(*) AS total')
            ->groupByRaw('DATE(date), status')->toBase()->get()->groupBy('day');

        $emptyCounts = array_fill_keys(self::STATUSES, 0);
        $days = collect(range(0, $monthStart->daysInMonth - 1))->map(function (int $offset) use ($monthStart, $counts, $emptyCounts) {
            $day = $monthStart->addDays($offset);
            $statusCounts = $emptyCounts;
            foreach ($counts->get($day->toDateString(), []) as $row) {
                $statusCounts[$row->status] = (int) $row->total;
            }
            $recorded = array_sum($statusCounts);
            $attended = $statusCounts['present'] + $statusCounts['late'];

            return [
                'label' => $day->format('j'),
                'date' => $day->toDateString(),
                'counts' => $statusCounts,
                'recorded' => $recorded,
                'attended' => $attended,
                'rate' => $recorded ? round(100 * $attended / $recorded, 2) : null,
            ];
        });

        return compact('filters', 'classes', 'programs', 'grades', 'monthStart', 'days');
    }

    private function grade(SchoolClass $class): ?int
    {
        return $class->classGroup?->grade
            ?? (preg_match('/^(?:(?:grade|level)\s*)?(\d{1,2})(?=\b|[A-Za-z]$)/i', trim($class->name), $match) === 1 ? (int) $match[1] : null);
    }
}
