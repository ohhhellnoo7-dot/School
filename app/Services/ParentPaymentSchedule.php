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
                    $meta = ['child' => $child->name, 'child_id' => $child->id, 'program' => $fee->program, 'academic_year' => $fee->academic_year, 'invoice' => $fee->display_number, 'fee_id' => $fee->id, 'status' => $fee->payment_status];
                    foreach ($fee->payments as $payment) {
                        $history[] = $meta + ['id' => 'payment-'.$payment->id, 'sequence' => $payment->id, 'sort_at' => $payment->created_at->format('Y-m-d H:i:s.u'), 'date' => $payment->paid_on->format('Y-m-d'), 'amount' => $payment->amount, 'currency' => $payment->currency, 'method' => $payment->method, 'type' => 'payment', 'legacy' => false, 'label' => 'Recorded Payment'];
                    }
                    $opening = Money::cents($fee->opening_paid ?? '0.00');
                    if (! $fee->invoice_number && $opening === 0 && $fee->payments->isEmpty()) {
                        $opening = Money::cents($fee->paid);
                    }
                    if ($opening > 0) {
                        $history[] = $meta + ['id' => 'opening-'.$fee->id, 'sequence' => $fee->id, 'sort_at' => '', 'date' => $fee->paid_at?->format('Y-m-d'), 'amount' => Money::decimal($opening), 'currency' => $fee->currency, 'method' => null, 'type' => 'opening', 'legacy' => true, 'label' => 'Historical Opening Balance'];
                    }
                    foreach ($fee->adjustments as $adjustment) {
                        $history[] = $meta + ['id' => 'adjustment-'.$adjustment->id, 'sequence' => $adjustment->id, 'sort_at' => $adjustment->created_at->format('Y-m-d H:i:s.u'), 'date' => $adjustment->created_at->format('Y-m-d'), 'amount' => $adjustment->amount, 'currency' => $fee->currency, 'method' => null, 'type' => $adjustment->kind === 'refund' ? 'refund' : 'adjustment', 'legacy' => false, 'label' => ucfirst($adjustment->kind), 'negative' => in_array($adjustment->kind, ['refund', 'credit', 'discount', 'waiver'])];
                    }
                }
            }
            usort($history, fn ($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? '') ?: strcmp($b['sort_at'], $a['sort_at']) ?: ($b['sequence'] <=> $a['sequence']));
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
            $result[$parent->id] = ['due' => $due, 'scheduled' => $scheduled, 'scheduled_source' => $source, 'manual_date' => $manualDate, 'paid_years' => $paidYears, 'history' => $history, 'tuition_date' => $dates[0] ?? null];
        }

        return $result;
    }
}
