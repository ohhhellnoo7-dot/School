<?php

namespace App\Services;

use App\Models\EmployeeSalaryProfile;
use Carbon\Carbon;

class EmployeeSalaryCalculator
{
    public function calculate(EmployeeSalaryProfile $profile, Carbon $asOf): array
    {
        $started = $profile->service_started_on;
        $months = max(0, ($asOf->year - $started->year) * 12 + $asOf->month - $started->month);
        if ($started->copy()->addMonthsNoOverflow($months)->gt($asOf)) {
            $months = max(0, $months - 1);
        }
        $quarters = intdiv($months, 3);
        $base = $profile->day_sessions * 12500 + $profile->evening_sessions * 3125;
        $eligible = $started->lte($asOf);

        return ['base_cents' => $eligible ? $base : 0, 'raise_cents' => $eligible ? $quarters * 500 : 0,
            'total_cents' => $eligible ? $base + $quarters * 500 : 0, 'completed_quarters' => $quarters,
            'next_raise_on' => $started->copy()->addMonthsNoOverflow(($quarters + 1) * 3), 'eligible' => $eligible];
    }
}
