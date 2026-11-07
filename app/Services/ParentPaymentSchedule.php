<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class ParentPaymentSchedule
{
    public function forFamilies($parents): array
    {
        $studentIds = $parents->flatMap->children->pluck('id');
        $periods = DB::table('invoice_periods')->whereIn('student_id', $studentIds)->get()
            ->groupBy(fn ($p) => $p->student_id.'|'.$p->academic_year.'|'.$p->enrollment_key);
        $structures = FeeStructure::where('status', 'active')->whereNotNull('approved_at')->get();
        $reminders = DB::table('parent_payment_reminders')->whereIn('parent_id', $parents->pluck('id'))->pluck('next_payment_on', 'parent_id');
        $result = [];
        foreach ($parents as $parent) {
            $history = [];
            foreach ($parent->children as $child) {
                foreach ($child->fees as $fee) {
                    foreach ($fee->payments as $payment) {
                        $history[] = ['date' => $payment->paid_on->format('Y-m-d'), 'child' => $child->name, 'amount' => $payment->amount, 'currency' => $payment->currency, 'fee_id' => $fee->id, 'legacy' => false];
                    }
                    if (! $fee->invoice_number && $fee->paid_at && Money::cents($fee->opening_paid ?? $fee->paid) > 0) {
                        $history[] = ['date' => $fee->paid_at->format('Y-m-d'), 'child' => $child->name, 'amount' => $fee->opening_paid ?: $fee->paid, 'currency' => $fee->currency, 'fee_id' => $fee->id, 'legacy' => true];
                    }
                }
            }
            usort($history, fn ($a, $b) => strcmp($b['date'], $a['date']));
            $due = $parent->children->flatMap->fees->filter(fn ($fee) => $fee->balanceCents() > 0)
                ->map(fn ($fee) => $fee->due_date->format('Y-m-d'))->sort()->first();
            $dates = [];
            foreach ($parent->children as $child) {
                // Unknown legacy coverage cannot establish when tuition must be paid again.
                if ($child->fees->contains(fn ($fee) => ! $fee->invoice_number && ! $fee->legacy_reviewed)) {
                    continue;
                }
                foreach ($child->studyClasses->unique(fn ($class) => app(SchoolFinanceService::class)->enrollmentKey($class)) as $class) {
                    $grade = $class->classGroup?->grade;
                    if (! $grade && preg_match('/(?:Grade\s*)?(\d+)/i', $class->name, $match)) {
                        $grade = (int) $match[1];
                    }
                    foreach ($structures->where('program', $class->program)->where('grade', $grade) as $structure) {
                        if (Money::cents($structure->monthly_tuition) <= Money::cents($structure->discount)) {
                            continue;
                        }
                        $key = $child->id.'|'.$structure->academic_year.'|'.app(SchoolFinanceService::class)->enrollmentKey($class);
                        $billed = ($periods->get($key) ?? collect())->pluck('month_index')->all();
                        foreach (range(1, $structure->billable_months) as $index) {
                            if (in_array($index, $billed)) {
                                continue;
                            }
                            $date = $structure->starts_on->copy()->startOfMonth()->addMonthsNoOverflow($index - 1)->day($structure->due_day);
                            if ($date->lt(today()) || $date->lt($structure->effective_from) || ($structure->effective_until && $date->gt($structure->effective_until))) {
                                continue;
                            }
                            if ($child->enrollment_date && $date->lt($child->enrollment_date)) {
                                continue;
                            }
                            $dates[] = $date->format('Y-m-d');
                            break;
                        }
                    }
                }
            }
            sort($dates);
            $paidYears = $parent->children->flatMap->fees->filter(fn ($fee) => filled($fee->academic_year))
                ->groupBy('academic_year')->filter(fn ($fees) => $fees->every(fn ($fee) => $fee->balanceCents() <= 0)
                    && $fees->contains(fn ($fee) => Money::cents($fee->paid) > 0))->keys()->sort()->values()->all();
            $scheduled = $dates[0] ?? null;
            $manualDate = $reminders->get($parent->id);
            $source = 'tuition';
            if ($manualDate && (! $scheduled || $manualDate < $scheduled)) {
                $scheduled = $manualDate;
                $source = 'school';
            }
            $result[$parent->id] = ['due' => $due, 'scheduled' => $scheduled, 'scheduled_source' => $source, 'manual_date' => $manualDate, 'paid_years' => $paidYears, 'history' => $history];
        }

        return $result;
    }
}
