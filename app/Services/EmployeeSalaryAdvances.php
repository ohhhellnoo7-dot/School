<?php

namespace App\Services;

use App\Models\SchoolExpense;
use App\Models\EmployeeSalaryPayment;
use App\Models\EmployeeSalaryProfile;
use App\Models\TeacherAdvanceRequest;
use App\Models\User;
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

    public function availableEarlySalary(int $employeeId, Carbon $month, ?int $approvingId = null): int
    {
        if ($month->lte(today()) || EmployeeSalaryPayment::where('teacher_id', $employeeId)->whereDate('salary_month', $month)->exists()) {
            return 0;
        }

        $calculator = app(EmployeeSalaryCalculator::class);
        $stops = app(TeacherSessionStops::class);
        [$profile, $minutes] = $stops->payrollAt(
            $stops->history([$employeeId])->get($employeeId, collect()), $month,
            EmployeeSalaryProfile::where('teacher_id', $employeeId)->first(),
            $calculator->assignedMinutes([$employeeId])->get($employeeId, 0),
        );
        $salary = $profile ? $calculator->calculate($profile, $month, $minutes)['base_cents'] : 0;
        $committed = TeacherAdvanceRequest::where('teacher_id', $employeeId)->whereDate('salary_month', $month)
            ->whereNull('cancelled_at')->whereNull('rejected_at')
            ->when($approvingId, fn ($query) => $query->where('id', '!=', $approvingId)->whereNotNull('approved_at'))
            ->sum('amount_cents');

        return max(0, $salary - $committed);
    }

    public function earlyPayoutsInMonth(array $employeeIds, Carbon $month): Collection
    {
        return SchoolExpense::whereIn('employee_id', $employeeIds)
            ->whereNotNull('recover_from_month')
            ->whereDate('spent_on', '>=', $month->copy()->startOfMonth())
            ->whereDate('spent_on', '<=', $month->copy()->endOfMonth())
            ->latest('spent_on')->orderByDesc('id')->get()->unique('employee_id')->keyBy('employee_id');
    }

    public function earlyPaymentsForPayroll(Carbon $month): Collection
    {
        return SchoolExpense::whereDate('recover_from_month', $month)
            ->whereIn('employee_id', User::where('role', 'teacher')->select('id'))
            ->get(['employee_id', 'amount'])->groupBy('employee_id')
            ->map(fn (Collection $payouts) => $payouts->sum(fn (SchoolExpense $payout) => Money::cents($payout->amount)));
    }

    public function approvedEarlyRequestsInMonth(array $employeeIds, Carbon $month): Collection
    {
        return TeacherAdvanceRequest::pending()->whereIn('teacher_id', $employeeIds)
            ->whereNotNull('salary_month')
            ->where('approved_at', '>=', $month->copy()->startOfMonth())
            ->where('approved_at', '<', $month->copy()->startOfMonth()->addMonth())
            ->distinct()->pluck('teacher_id')->flip();
    }

    public function outstanding(array $employeeIds, Carbon $through, Carbon $salaryMonth): Collection
    {
        $deferred = $this->earlyPayoutsInMonth($employeeIds, $salaryMonth)->keys()
            ->merge($this->approvedEarlyRequestsInMonth($employeeIds, $salaryMonth)->keys());

        return $this->outstandingQuery($through)
            ->where(fn (Builder $query) => $query->whereNull('recover_from_month')->orWhereDate('recover_from_month', '<=', $salaryMonth->toDateString()))
            ->whereIn('employee_id', array_diff($employeeIds, $deferred->all()))->get()->groupBy('employee_id');
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
