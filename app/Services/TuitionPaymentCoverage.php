<?php

namespace App\Services;

use App\Models\Fee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TuitionPaymentCoverage
{
    public function forChildren(Collection $childIds, CarbonImmutable $today): Collection
    {
        $fees = Fee::whereIn('student_id', $childIds)->where('paid', '>', 0)->with(['adjustments', 'payments'])->get()
            ->filter(fn (Fee $fee) => $fee->netCents() > 0 && $fee->balanceCents() <= 0);
        $feeIds = $fees->modelKeys();
        $periods = DB::table('invoice_periods')->whereIn('fee_id', $feeIds)
            ->selectRaw('fee_id, COUNT(DISTINCT month_index) AS months')->groupBy('fee_id')->pluck('months', 'fee_id');
        $runs = DB::table('bulk_payment_allocations as allocations')
            ->join('bulk_payment_runs as runs', 'runs.id', '=', 'allocations.bulk_payment_run_id')
            ->whereIn('allocations.fee_id', $feeIds)->whereDate('runs.starts_on', '<=', $today->toDateString())
            ->orderByDesc('runs.starts_on')
            ->get(['allocations.fee_id', 'runs.starts_on', 'runs.next_on', 'runs.months'])
            ->groupBy('fee_id')->map->first();

        return $fees->groupBy('student_id')->map(function ($invoices) use ($periods, $runs, $today) {
            $windows = $invoices->map(function (Fee $fee) use ($periods, $runs, $today) {
                $run = $runs->get($fee->id);
                $paidOn = $fee->payments->max('paid_on') ?? $fee->paid_at;
                $months = (int) ($run->months ?? $periods->get($fee->id) ?? match ($fee->payment_plan) {
                    'monthly' => 1,
                    'quarterly' => 3,
                    'package' => 6,
                    default => 0,
                });
                if (! $months || (! $run && ! $paidOn)) {
                    return null;
                }

                $start = CarbonImmutable::parse($run->starts_on ?? $paidOn)->startOfDay();
                $end = $run ? CarbonImmutable::parse($run->next_on)->startOfDay() : $start->addMonthsNoOverflow($months);
                if ($start->gt($today) || $end->lte($start)) {
                    return null;
                }

                $daysLeft = max(0, (int) $today->diffInDays($end, false));
                $percent = round(100 * $daysLeft / $start->diffInDays($end), 2);

                return compact('start', 'end', 'months', 'daysLeft', 'percent') + ['invoice' => $fee];
            })->filter();

            return $windows->sortByDesc(fn ($window) => $window['end']->toDateString())->first();
        })->filter();
    }
}
