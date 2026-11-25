<?php

namespace App\Services;

use App\Models\SchoolExpense;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EmployeeSalaryAdvances
{
    public const PLAN_MINIMUM_CENTS = 10000;

    public const PLAN_MAX_MONTHS = 24;

    public static function installmentCents(int $amount, int $months): int
    {
        return intdiv($amount + $months - 1, $months);
    }

    public function outstanding(array $employeeIds, Carbon $through): Collection
    {
        return $this->outstandingQuery($through)
            ->whereIn('employee_id', $employeeIds)->get()->groupBy('employee_id');
    }

    public function outstandingForReport(Carbon $through): Collection
    {
        return $this->outstandingQuery($through)->with('employee:id,name,gender')->get();
    }

    private function outstandingQuery(Carbon $through): Builder
    {
        return SchoolExpense::whereNotNull('employee_id')
            ->whereDate('spent_on', '<=', $through)
            ->whereRaw('advance_recovered_cents < ROUND(amount * 100)')
            ->orderBy('spent_on')->orderBy('id');
    }

    public function apply(array $salary, Collection $advances): array
    {
        $salary['advance_cents'] = min($salary['total_cents'], $advances->sum($this->due(...)));
        $salary['deduction_cents'] += $salary['advance_cents'];
        $salary['total_cents'] -= $salary['advance_cents'];

        return $salary;
    }

    public function recover(Collection $advances, int $cents): void
    {
        foreach ($advances as $advance) {
            if ($cents === 0) {
                break;
            }
            $applied = min($cents, $this->due($advance));
            $advance->increment('advance_recovered_cents', $applied);
            $cents -= $applied;
        }
    }

    private function due(SchoolExpense $advance): int
    {
        $amount = Money::cents($advance->amount);
        $installment = self::installmentCents($amount, (int) $advance->repayment_months);

        return min($installment, $amount - $advance->advance_recovered_cents);
    }
}
