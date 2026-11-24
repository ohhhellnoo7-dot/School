<?php

namespace App\Services;

use App\Models\EmployeeSalaryProfile;
use App\Models\EmployeeSalaryPayment;
use App\Models\Schedule;
use App\Models\User;
use App\Support\AttendanceSession;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EmployeeSalaryCalculator
{
    private array $afternoonOnly = [];

    public function assignedMinutes(array $teacherIds): Collection
    {
        return Schedule::where('entry_type', Schedule::TYPE_ACADEMIC)
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

        $afternoonOnly = $this->afternoonOnly[$teacherId] ??= AttendanceSession::teachers(User::whereKey($teacherId), 'Afternoon')->exists()
            && ! AttendanceSession::teachers(User::whereKey($teacherId), 'Morning')->exists();

        return $afternoonOnly ? $rates['afternoon'] : $rates['morning'];
    }

    public function calculate(EmployeeSalaryProfile $profile, Carbon $asOf, int $assignedMinutes): array
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
        $eligible = ($profile->day_sessions + $profile->evening_sessions) > 0 && $started->lte($asOf);

        return ['base_cents' => $eligible ? $base : 0, 'full_salary_cents' => $fullSalary, 'raise_cents' => $eligible ? $quarters * 500 : 0,
            'total_cents' => $eligible ? $base + $quarters * 500 : 0, 'completed_quarters' => $quarters,
            'next_raise_on' => $started->copy()->addMonthsNoOverflow(($quarters + 1) * 3), 'eligible' => $eligible];
    }
}
