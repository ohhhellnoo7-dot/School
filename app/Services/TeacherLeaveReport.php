<?php

namespace App\Services;

use App\Models\{EmployeeSalaryAttendance, EmployeeSalaryPayment, EmployeeSalaryProfile, TeacherAttendance, TeacherSessionAttendance, User};
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TeacherLeaveReport
{
    public function forTeacher(User $teacher, Carbon $month, ?EmployeeSalaryProfile $profile): array
    {
        $range = [$month->copy()->startOfYear()->toDateString(), $month->copy()->endOfYear()->toDateString()];
        $byMonth = fn ($record) => $record->date->month;
        $daily = TeacherAttendance::where('teacher_id', $teacher->id)->whereBetween('date', $range)->get();
        $sessions = TeacherSessionAttendance::where('teacher_id', $teacher->id)->whereBetween('date', $range)->get();
        $overrides = EmployeeSalaryAttendance::where('teacher_id', $teacher->id)->whereBetween('date', $range)->get();
        $deductions = app(\App\Services\EmployeeSalaryDeductions::class);
        $bonus = app(EmployeeAttendanceBonus::class);
        $payments = EmployeeSalaryPayment::where('teacher_id', $teacher->id)->whereBetween('salary_month', $range)
            ->get()->keyBy(fn ($payment) => $payment->salary_month->month);

        $calculator = app(EmployeeSalaryCalculator::class);
        $minutes = $calculator->assignedMinutes([$teacher->id])->get($teacher->id, 0);
        $payment = $payments->get($month->month);
        $salary = $payment?->only(['base_cents', 'raise_cents']) ?? ($profile ? $calculator->calculate($profile, $month->copy()->endOfMonth(), $minutes) : null);

        return [
            'salary' => $salary ? array_intersect_key($salary, array_flip(['base_cents', 'raise_cents'])) : null,
            'leaveMonths' => $this->countLeaveMonths($daily->groupBy($byMonth), $sessions->groupBy($byMonth), $overrides->groupBy($byMonth), $deductions),
            'attendanceBonus' => $bonus->forYear(
                $teacher->id, $month, $profile, $payments, $bonus->timetables([$teacher->id], $month->year)->get($teacher->id, collect()),
                $daily, $sessions, $overrides, $bonus->closedDates(app(SchoolHolidayClosure::class)),
            ),
        ];
    }

    public function countLeaveMonths(
        \Illuminate\Support\Collection $daily,
        \Illuminate\Support\Collection $sessions,
        \Illuminate\Support\Collection $overrides,
        \App\Services\EmployeeSalaryDeductions $deductions,
    ): \Illuminate\Support\Collection {
        return collect(range(1, 12))->mapWithKeys(fn ($number) => [
            $number => (int) $deductions->usesLeaveAllowance(
                $daily->get($number, collect()),
                $sessions->get($number, collect()),
                $overrides->get($number, collect()),
            ),
        ]);
    }

    public function segments(array $report, Collection $history, Carbon $month): Collection
    {
        $stops = $history->filter(fn ($stop) => $stop->effective_on->copy()->subMonth()->year === $month->year);
        if ($stops->isEmpty()) {
            return collect([$report]);
        }

        $start = 1;
        $used = 0;
        $segments = collect();
        foreach ($stops as $stop) {
            $snapshot = $stop->snapshot;
            $end = $stop->effective_on->copy()->subMonth()->month;
            $started = $snapshot['profile'] ? Carbon::parse($snapshot['profile']['service_started_on']) : null;
            $requiredMonths = $snapshot['requiredBonusMonths'] ?? $snapshot['profile']['bonus_service_months'] ?? EmployeeAttendanceBonus::DEFAULT_SERVICE_MONTHS;
            $segment = $this->segment(array_replace($report, [
                'payment' => null,
                'salary' => $snapshot['salary'],
                'leaveMonths' => collect($snapshot['leaveMonths']),
                'attendanceBonus' => $snapshot['attendanceBonus'],
                'work' => [
                    'sessions' => $snapshot['profile'] ? $snapshot['profile']['day_sessions'] + $snapshot['profile']['evening_sessions'] : null,
                    'serviceStartedOn' => $started,
                    'requiredBonusMonths' => $requiredMonths,
                    'belowBonusServiceMinimum' => $started?->copy()->addMonthsNoOverflow($requiredMonths)->gt($stop->effective_on) ?? false,
                ],
                'closedBy' => $stop,
            ]), $start, $end);
            $used += $segment['leaveMonths']->sum();
            $segments->push($segment + ['remainingLeave' => 12 - $used]);
            $start = $end + 1;
        }

        if ($start <= 12) {
            $segment = $this->segment($report + ['openedBy' => $stops->last()], $start, 12);
            $segments->push($segment + ['remainingLeave' => 12 - $used - $segment['leaveMonths']->sum()]);
        }

        return $segments;
    }

    private function segment(array $report, int $start, int $end): array
    {
        $inside = fn ($number) => $number >= $start && $number <= $end;
        $report['leaveMonths'] = $report['leaveMonths']->map(fn ($days, $number) => $inside($number) ? $days : 0);
        $bonus = $report['attendanceBonus'];
        $bonus['qualifyingMonths'] = array_values(array_filter($bonus['qualifyingMonths'], $inside));
        $bonus['year'] = array_sum(array_filter($bonus['monthly'], $inside, ARRAY_FILTER_USE_KEY));
        if ($bonus['annualMaximum'] !== null) {
            $bonus['annualMaximum'] = intdiv($bonus['annualMaximum'], 12) * ($end - $start + 1);
        }

        return array_replace($report, ['attendanceBonus' => $bonus, 'segmentStart' => $start, 'segmentEnd' => $end]);
    }
}
