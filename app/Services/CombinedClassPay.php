<?php

namespace App\Services;

use App\Models\TemporaryClassStudy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CombinedClassPay
{
    public const HOURLY_CENTS = 125;

    public function unpaid(array $teacherIds, Carbon $salaryMonth): Builder
    {
        $end = app(EmployeeSalaryDeductions::class)->attendanceMonth($salaryMonth)->endOfMonth();
        $cutoff = $end->min(now());

        return TemporaryClassStudy::whereIn('covering_teacher_id', $teacherIds)
            ->whereNull('salary_payment_id')
            ->where('coverage_cents', '>', 0)
            ->where(function (Builder $studies) use ($cutoff) {
                $studies->where('date', '<', $cutoff->toDateString())
                    ->orWhere(fn (Builder $sameDay) => $sameDay->where('date', $cutoff->toDateString())
                        ->where('ends_at', '<=', $cutoff->format('H:i:s')));
            });
    }

    public function totals(array $teacherIds, Carbon $salaryMonth): Collection
    {
        return $this->unpaid($teacherIds, $salaryMonth)->groupBy('covering_teacher_id')
            ->selectRaw('covering_teacher_id, SUM(coverage_cents) AS total')
            ->pluck('total', 'covering_teacher_id');
    }

    public function addToSalary(array $salary, int $cents): array
    {
        $salary['combined_class_cents'] = $cents;
        $salary['gross_cents'] += $cents;
        $salary['total_cents'] += $cents;

        return $salary;
    }
}
