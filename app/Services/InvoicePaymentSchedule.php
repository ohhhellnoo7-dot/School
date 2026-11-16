<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\FeeStructure;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoicePaymentSchedule
{
    public function nextDueDate(Fee $fee): ?Carbon
    {
        if ($fee->trashed() || ! $fee->fee_structure_id || ! $fee->enrollment_key) {
            return null;
        }

        $structure = FeeStructure::whereKey($fee->fee_structure_id)
            ->where('status', 'active')->whereNotNull('approved_at')->first();
        $lastMonth = DB::table('invoice_periods')->where('fee_id', $fee->id)->max('month_index');
        if (! $structure || ! $lastMonth) {
            return null;
        }

        $coverage = DB::table('invoice_periods')->where('student_id', $fee->student_id)
            ->where('academic_year', $fee->academic_year)->where('enrollment_key', $fee->enrollment_key)->get();
        $invoices = Fee::with(['adjustments', 'items'])->whereIn('id', $coverage->pluck('fee_id'))->get()->keyBy('id');

        for ($index = (int) $lastMonth + 1; $index <= $structure->billable_months; $index++) {
            $period = $coverage->firstWhere('month_index', $index);
            if ($period) {
                $invoice = $invoices->get($period->fee_id);
                if ($invoice && $invoice->balanceCents() > 0) {
                    return $invoice->due_date;
                }
                continue;
            }

            $date = $structure->starts_on->copy()->startOfMonth()->addMonthsNoOverflow($index - 1)->day($structure->due_day);
            if ($date->lt($structure->effective_from) || ($structure->effective_until && $date->gt($structure->effective_until))) {
                continue;
            }

            return $date;
        }

        return null;
    }
}
