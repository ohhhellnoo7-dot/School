<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\Student;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TuitionPaymentCoverage
{
    public function feeTotals(Collection $children): Collection
    {
        return $children->mapWithKeys(fn (Student $child) => [
            $child->id => $child->fees->groupBy('currency')->map(fn ($invoices) => [
                'billed' => $invoices->sum(fn (Fee $invoice) => $invoice->netCents()),
                'paid' => $invoices->sum(fn (Fee $invoice) => Money::cents($invoice->paid)),
                'remaining' => $invoices->sum(fn (Fee $invoice) => max(0, $invoice->balanceCents())),
            ]),
        ]);
    }

    public function forChildren(Collection $children, CarbonImmutable $today): Collection
    {
        $fees = Fee::whereIn('student_id', $children->pluck('id'))->where('paid', '>', 0)->with(['adjustments', 'payments'])->get()
            ->filter(fn (Fee $fee) => $fee->netCents() > 0 && $fee->balanceCents() <= 0);
        $feeIds = $fees->modelKeys();
        $periods = DB::table('invoice_periods as periods')->whereIn('periods.fee_id', $feeIds)
            ->join('fees as invoices', 'invoices.id', '=', 'periods.fee_id')
            ->leftJoin('fee_structures as rates', 'rates.id', '=', DB::raw('COALESCE(periods.fee_structure_id, invoices.fee_structure_id)'))
            ->get(['periods.fee_id', 'periods.academic_year', 'periods.month_index', 'periods.enrollment_key', 'rates.program', 'rates.starts_on', 'rates.due_day'])
            ->groupBy('fee_id');
        $runs = DB::table('bulk_payment_allocations as allocations')
            ->join('bulk_payment_runs as runs', 'runs.id', '=', 'allocations.bulk_payment_run_id')
            ->whereIn('allocations.fee_id', $feeIds)->whereDate('runs.starts_on', '<=', $today->toDateString())
            ->orderByDesc('runs.starts_on')
            ->get(['allocations.fee_id', 'runs.starts_on', 'runs.next_on', 'runs.months'])
            ->groupBy('fee_id')->map->first();

        $byChild = $fees->groupBy('student_id');

        return $children->mapWithKeys(function (Student $child) use ($byChild, $periods, $runs, $today) {
            $finance = app(SchoolFinanceService::class);
            $enrollments = $child->studyClasses->mapWithKeys(fn ($class) => [$finance->enrollmentKey($class) => $class->program])->filter();
            $calendar = collect();
            $windows = $byChild->get($child->id, collect())->flatMap(function (Fee $fee) use ($periods, $runs, $today, $enrollments, $calendar) {
                $run = $runs->get($fee->id);
                $paidOn = $fee->payments->max('paid_on') ?? $fee->paid_at;
                if (! $run && ! $paidOn) {
                    return [];
                }
                $start = CarbonImmutable::parse($run->starts_on ?? $paidOn)->startOfDay();
                if ($start->gt($today)) {
                    return [];
                }
                $fallback = $fee->program ?: ($enrollments->unique()->count() === 1 ? $enrollments->first() : 'Unclassified');
                $programMonths = $periods->get($fee->id, collect())
                    ->groupBy(fn ($period) => $period->program ?: ($fee->program ?: $enrollments->get($period->enrollment_key, $fallback)));
                $datedPrograms = $programMonths->filter(fn ($rows) => $rows->every(fn ($row) => $row->starts_on && $row->due_day));
                foreach ($datedPrograms as $program => $rows) {
                    foreach ($rows as $row) {
                        $due = CarbonImmutable::parse($row->starts_on)->startOfMonth()->addMonths((int) $row->month_index - 1)->day((int) $row->due_day);
                        $calendar->push(['program' => $program, 'due' => $due, 'paidOn' => $start, 'bulk' => (bool) $run]);
                    }
                }
                $programMonths = $programMonths->map(fn ($rows) => $rows->unique(fn ($period) => $period->academic_year.'|'.$period->month_index)->count());
                if ($programMonths->isEmpty()) {
                    $programMonths = collect([$fallback => match ($fee->payment_plan) {
                        'monthly' => 1,
                        'quarterly' => 3,
                        'package' => 6,
                        default => 0,
                    }]);
                }
                if (! $run) {
                    $programMonths = $programMonths->except($datedPrograms->keys()->all());
                }

                return $programMonths->map(function ($months, $program) use ($run, $start, $today) {
                    $months = (int) ($run->months ?? $months);
                    $end = $run ? CarbonImmutable::parse($run->next_on)->startOfDay() : $start->addMonthsNoOverflow($months);
                    if (! $months || $end->lte($start)) {
                        return null;
                    }
                    return $this->window($program, $start, $end, $months, $today, $start);
                })->filter()->values();
            });

            $windows = $windows->concat($this->calendarWindows($calendar, $today))->groupBy('program')
                ->map(fn ($entries) => $entries->sortByDesc(fn ($window) => $window['end']->toDateString())->first());

            $programs = $enrollments->unique()->values()->merge($windows->keys())->unique();

            return [$child->id => $programs->isEmpty() ? collect(['Unclassified' => null]) : $programs->mapWithKeys(fn ($program) => [$program => $windows->get($program)])];
        });
    }

    private function calendarWindows(Collection $calendar, CarbonImmutable $today): Collection
    {
        return $calendar->groupBy('program')->flatMap(function (Collection $entries, string $program) use ($today) {
            $runs = [];
            $entries = $entries->sortBy('due')->groupBy(fn ($entry) => $entry['due']->format('Y-m'));
            foreach ($entries as $month => $payments) {
                $entry = $payments->firstWhere('bulk', false) ?? $payments->first();
                $last = array_key_last($runs);
                if ($last === null || $runs[$last]['last']->addMonthNoOverflow()->format('Y-m') !== $month) {
                    $runs[] = ['first' => $entry['due'], 'last' => $entry['due'], 'months' => 1, 'paidOn' => $entry['paidOn'], 'hasIndividualPayment' => ! $entry['bulk']];
                    continue;
                }
                $runs[$last]['last'] = $entry['due'];
                $runs[$last]['months']++;
                $runs[$last]['hasIndividualPayment'] = $runs[$last]['hasIndividualPayment'] || ! $entry['bulk'];
            }

            return collect($runs)->filter(fn ($run) => $run['hasIndividualPayment'] && $run['first']->startOfMonth()->lte($today))
                ->map(fn ($run) => $this->window(
                    $program,
                    $run['first'],
                    $run['last']->addMonthNoOverflow(),
                    $run['months'],
                    $today,
                    $run['paidOn'],
                ));
        });
    }

    private function window(string $program, CarbonImmutable $start, CarbonImmutable $end, int $months, CarbonImmutable $today, CarbonImmutable $paidOn): array
    {
        $daysLeft = max(0, (int) $today->diffInDays($end, false));
        $percent = min(100, round(100 * $daysLeft / $start->diffInDays($end), 2));

        return compact('program', 'start', 'end', 'months', 'daysLeft', 'percent', 'paidOn');
    }
}
