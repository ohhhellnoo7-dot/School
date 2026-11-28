<?php

namespace App\Services;

use App\Models\EmployeeSalaryProfile;
use App\Models\EmployeeSalaryPayment;
use App\Models\Schedule;
use App\Models\User;
use App\Support\AttendanceSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeSalaryCalculator
{
    public const WEEKEND_DAYS = ['Saturday', 'Sunday'];

    private array $afternoonOnly = [];

    public function assignedMinutes(array $teacherIds): Collection
    {
        return Schedule::where('entry_type', Schedule::TYPE_ACADEMIC)
            ->whereNotIn('day', self::WEEKEND_DAYS)
            ->whereIn('teacher_id', $teacherIds)->get(['teacher_id', 'starts_at', 'ends_at'])
            ->groupBy('teacher_id')
            ->map(fn ($lessons) => $lessons->sum(fn ($lesson) => TeacherManagementService::duration($lesson->starts_at, $lesson->ends_at)));
    }

    public function weeklyWorkHours(int $daySessions, int $eveningSessions): int
    {
        return 5 * (4 * $daySessions + $eveningSessions);
    }

    public function baseCents(int $weeklyMinutes): int
    {
        return (int) round($weeklyMinutes * 25000 / (40 * 60));
    }

    public function fullSalaryCents(EmployeeSalaryProfile|EmployeeSalaryPayment $settings): int
    {
        if ($settings instanceof EmployeeSalaryPayment) {
            return $settings->full_salary_cents ?? $this->baseCents(
                $this->weeklyWorkHours($settings->day_sessions, $settings->evening_sessions) * 60
            );
        }

        $rates = app(EmployeeSalaryRates::class)->amounts();
        $daySalary = match ($settings->day_sessions) {
            2 => $rates['morning'] + $rates['afternoon'],
            1 => $this->singleDaySalary($settings->teacher_id, $rates),
            default => 0,
        };

        return $daySalary + $settings->evening_sessions * $rates['evening'];
    }

    private function singleDaySalary(?int $teacherId, array $rates): int
    {
        if ($teacherId === null || $rates['morning'] === $rates['afternoon']) {
            return $rates['morning'];
        }

        return $rates[strtolower($this->singleDaySession($teacherId))];
    }

    public function singleDaySession(?int $teacherId): string
    {
        if ($teacherId === null) {
            return 'Morning';
        }

        $afternoonOnly = $this->afternoonOnly[$teacherId] ??= AttendanceSession::teachers(User::whereKey($teacherId), 'Afternoon')->exists()
            && ! AttendanceSession::teachers(User::whereKey($teacherId), 'Morning')->exists();

        return $afternoonOnly ? 'Afternoon' : 'Morning';
    }

    public function calculate(EmployeeSalaryProfile $profile, Carbon $asOf, int $assignedMinutes, ?Collection $workPeriods = null): array
    {
        $started = $profile->service_started_on;
        $months = max(0, ($asOf->year - $started->year) * 12 + $asOf->month - $started->month);
        if ($started->copy()->addMonthsNoOverflow($months)->gt($asOf)) {
            $months = max(0, $months - 1);
        }
        $quarters = intdiv($months, 3);
        $weeklyMinutes = $this->weeklyWorkHours($profile->day_sessions, $profile->evening_sessions) * 60;
        $fullSalary = $this->fullSalaryCents($profile);
        $base = $weeklyMinutes > 0 ? (int) round($assignedMinutes * $fullSalary / $weeklyMinutes) : 0;
        $workPeriods ??= DB::table('teacher_working_periods')->where('teacher_id', $profile->teacher_id)
            ->whereIn('weekday', self::WEEKEND_DAYS)->get();
        $minutesByDay = $workPeriods->whereIn('weekday', self::WEEKEND_DAYS)->groupBy('weekday')
            ->map(fn ($periods) => TeacherManagementService::workingMinutes($periods));
        $weekendMinutes = 0;
        $end = $asOf->copy()->endOfMonth();
        for ($date = $asOf->copy()->startOfMonth(); $date->lte($end); $date->addDay()) {
            if ($date->gte($started)) {
                $weekendMinutes += $minutesByDay->get($date->format('l'), 0);
            }
        }
        $weekendRate = app(EmployeeSalaryRates::class)->amounts()['weekend_hourly'];
        $weekendCents = (int) round($weekendMinutes * $weekendRate / 60);
        $weekdayEligible = $weeklyMinutes > 0 && $started->lte($asOf);
        $eligible = $weekdayEligible || $weekendMinutes > 0;
        $base = ($weekdayEligible ? $base : 0) + $weekendCents;

        return ['base_cents' => $eligible ? $base : 0, 'weekend_cents' => $weekendCents, 'full_salary_cents' => $fullSalary + $weekendCents, 'raise_cents' => $eligible ? $quarters * 500 : 0,
            'total_cents' => $eligible ? $base + $quarters * 500 : 0, 'completed_quarters' => $quarters,
            'next_raise_on' => $started->copy()->addMonthsNoOverflow(($quarters + 1) * 3), 'eligible' => $eligible];
    }
}
