<?php

namespace App\Services;

use App\Models\EmployeeSalaryProfile;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EmployeeSalaryCalculator
{
    public function assignedMinutes(array $teacherIds): Collection
    {
        return Schedule::where('entry_type', Schedule::TYPE_ACADEMIC)
            ->whereIn('teacher_id', $teacherIds)->get(['teacher_id', 'starts_at', 'ends_at'])
            ->groupBy('teacher_id')
            ->map(fn ($lessons) => $lessons->sum(fn ($lesson) => TeacherManagementService::duration($lesson->starts_at, $lesson->ends_at)));
    }

    public function baseCents(int $weeklyMinutes): int
    {
        return (int) round($weeklyMinutes * 25000 / (40 * 60));
    }

    public function calculate(EmployeeSalaryProfile $profile, Carbon $asOf, int $assignedMinutes): array
    {
        $started = $profile->service_started_on;
        $months = max(0, ($asOf->year - $started->year) * 12 + $asOf->month - $started->month);
        if ($started->copy()->addMonthsNoOverflow($months)->gt($asOf)) {
            $months = max(0, $months - 1);
        }
        $quarters = intdiv($months, 3);
        $base = $this->baseCents($assignedMinutes);
        $eligible = $started->lte($asOf);

        return ['base_cents' => $eligible ? $base : 0, 'raise_cents' => $eligible ? $quarters * 500 : 0,
            'total_cents' => $eligible ? $base + $quarters * 500 : 0, 'completed_quarters' => $quarters,
            'next_raise_on' => $started->copy()->addMonthsNoOverflow(($quarters + 1) * 3), 'eligible' => $eligible];
    }
}
