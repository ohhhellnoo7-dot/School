<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FinancialAdjustment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SchoolFinanceService
{
    public function archiveLegacyFees(User $actor): int
    {
        return $this->locked(function () use ($actor) {
            $fees = Fee::whereNull('invoice_number')->whereNull('fee_structure_id')->get();
            foreach ($fees as $fee) {
                $this->audit($actor, 'archive_legacy_fee', 'fee', $fee->id, $fee->getAttributes());
                $fee->delete();
            }

            return $fees->count();
        });
    }

    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['finance' => $message]);
    }

    public function locked(callable $action): mixed
    {
        return DB::transaction(function () use ($action) {
            DB::table('finance_locks')->where('id', 1)->lockForUpdate()->firstOrFail();

            return $action();
        }, 3);
    }

    public function audit(User $actor, string $action, string $entity, int $id, array $details): void
    {
        DB::table('finance_audits')->insert(['actor_id' => $actor->id, 'action' => $action, 'entity' => $entity, 'entity_id' => $id, 'details' => json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }

    public function approve(FeeStructure $structure, User $actor): void
    {
        $this->locked(function () use ($structure, $actor) {
            $structure = $structure->fresh();
            if ($structure->status !== 'draft') {
                $this->fail('Only draft fee versions can be approved.');
            }
            $issuedVersions = FeeStructure::where('academic_year', $structure->academic_year)->where('program', $structure->program)->where('grade', $structure->grade)
                ->whereIn('id', Fee::whereNotNull('fee_structure_id')->select('fee_structure_id'))->get();
            foreach ($issuedVersions as $issued) {
                if ($issued->starts_on->format('Y-m-d') !== $structure->starts_on->format('Y-m-d') || $issued->billable_months !== $structure->billable_months || $issued->currency !== $structure->currency) {
                    $this->fail('Once invoices are issued, keep the year billing calendar, month count and currency unchanged. Create next-year settings to change them.');
                }
            }
            FeeStructure::where('academic_year', $structure->academic_year)->where('program', $structure->program)
                ->where('grade', $structure->grade)->where('status', 'active')->update(['status' => 'inactive']);
            $structure->update(['status' => 'active', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit($actor, 'approve', 'fee_structure', $structure->id, $structure->toArray());
        });
    }

    public function enrollmentKey(SchoolClass $class): string
    {
        return $class->class_group_id ? 'group:'.$class->class_group_id : 'class:'.$class->id;
    }

    /** Each issued fee is an immutable invoice snapshot. Month coverage prevents mixing plans from double billing. */
    public function issue(Student $student, SchoolClass $class, FeeStructure $structure, string $plan, int $start, int $count, User $actor): array
    {
        return $this->locked(function () use ($student, $class, $structure, $plan, $start, $count, $actor) {
            $student = $student->fresh();
            $class = $class->fresh();
            $structure = $structure->fresh();
            if ($student->fees()->whereNull('invoice_number')->where('legacy_reviewed', false)->exists()) {
                $this->fail('Review this student\'s historical fees first. Map previous tuition to billing months or mark non-tuition charges so old fees cannot be billed twice.');
            }
            if (! $student->studyClasses()->whereKey($class->id)->exists()) {
                $this->fail('The student is not enrolled in this class.');
            }
            $grade = $class->classGroup?->grade;
            if (! $grade && preg_match('/(?:Grade\s*)?(\d+)/i', $class->name, $m)) {
                $grade = (int) $m[1];
            }
            if ($structure->status !== 'active' || ! $structure->approved_at || $structure->program !== $class->program || (int) $structure->grade !== (int) $grade) {
                $this->fail('Choose an approved fee version matching the enrollment program and grade.');
            }
            if (! in_array($plan, ['monthly', 'quarterly', 'package', 'semester', 'annual', 'installments', 'bulk'])) {
                $this->fail('Choose a valid payment plan.');
            }
            if ($structure->effective_from->isFuture() || ($structure->effective_until && $structure->effective_until->lt(today()))) {
                $this->fail('This fee version is outside its effective dates.');
            }
            $key = $this->enrollmentKey($class);
            $coverage = DB::table('invoice_periods')->where('student_id', $student->id)->where('academic_year', $structure->academic_year)->where('enrollment_key', $key);
            $billed = (clone $coverage)->pluck('month_index')->all();
            $length = match ($plan) {
                'monthly' => 1, 'quarterly' => 3, 'package' => 6, 'semester' => $structure->semester_months, 'annual' => $structure->billable_months, default => $count
            };
            $end = $plan === 'annual' ? $structure->billable_months : $start + $length - 1;
            if ($start < 1 || $end > $structure->billable_months || $end < $start) {
                $this->fail('Billing months must fit within the configured academic year.');
            }
            $months = array_values(array_diff(range($start, $end), $billed));
            if (! $months) {
                return Fee::whereIn('id', (clone $coverage)->whereBetween('month_index', [$start, $end])->pluck('fee_id'))->get()->all();
            }
            if (in_array($plan, ['quarterly', 'package']) && count($months) !== $length) {
                $this->fail('A package requires its full number of unbilled consecutive months. Choose a new starting month.');
            }
            $groups = $plan === 'installments' ? array_map(fn ($month) => [$month], $months) : [$months];
            $result = [];
            $firstInvoice = ! $billed;
            foreach ($groups as $indices) {
                $items = $this->quoteItems($structure, $class->session, $indices, $firstInvoice, $plan);
                $firstInvoice = false;
                $due = $structure->starts_on->copy()->startOfMonth()->addMonthsNoOverflow(min($indices) - 1)->day($structure->due_day);
                $fee = Fee::create([
                    'student_id' => $student->id, 'description' => $class->program.' Grade '.$grade.' tuition',
                    'amount' => Money::decimal(array_sum(array_map(fn ($item) => Money::cents($item['amount']), $items))), 'paid' => '0.00', 'opening_paid' => '0.00',
                    'due_date' => $due, 'invoice_number' => 'INV-'.Str::upper((string) Str::uuid()), 'fee_structure_id' => $structure->id,
                    'program' => $class->program, 'grade' => $grade, 'session' => $class->session, 'academic_year' => $structure->academic_year, 'currency' => $structure->currency,
                    'enrollment_key' => $key, 'payment_plan' => $plan, 'issued_by' => $actor->id, 'legacy_reviewed' => true,
                ]);
                $fee->items()->createMany($items);
                foreach ($indices as $index) {
                    DB::table('invoice_periods')->insert(['fee_id' => $fee->id, 'student_id' => $student->id, 'academic_year' => $structure->academic_year, 'enrollment_key' => $key, 'month_index' => $index]);
                }
                $this->audit($actor, 'issue_invoice', 'fee', $fee->id, ['months' => $indices, 'structure' => $structure->id, 'amount' => $fee->amount]);
                $result[] = $fee;
            }

            return $result;
        });
    }

    public function quoteItems(FeeStructure $structure, ?string $session, array $indices, bool $firstInvoice, string $plan): array
    {
        $items = [];
        $prices = array_fill_keys($indices, Money::cents($structure->monthlyForSession($session)));
        $packageMonths = [];
        $duration = $structure->packageMonths();
        $quarterly = $structure->quarterlyForSession($session);
        if (! in_array($plan, ['monthly', 'installments']) && $quarterly !== null) {
            for ($position = 0; $position + $duration - 1 < count($indices); $position++) {
                if (array_slice($indices, $position, $duration) !== range($indices[$position], $indices[$position] + $duration - 1)) {
                    continue;
                }
                $total = Money::cents($quarterly);
                foreach (range(0, $duration - 1) as $offset) {
                    $index = $indices[$position + $offset];
                    $prices[$index] = intdiv($total, $duration) + ($offset < $total % $duration ? 1 : 0);
                    $packageMonths[$index] = true;
                }
                $position += $duration - 1;
            }
        }
        foreach ($indices as $index) {
            $month = $structure->starts_on->copy()->addMonthsNoOverflow($index - 1)->format('M Y');
            if ($prices[$index] < Money::cents($structure->discount)) {
                $this->fail('Scholarship exceeds this session or package tuition. Review the fee configuration.');
            }
            $items[] = ['description' => 'Tuition - '.$month.(isset($packageMonths[$index]) ? ' ('.$duration.'-month package)' : ''), 'amount' => Money::decimal($prices[$index])];
            if (Money::cents($structure->discount) > 0) {
                $items[] = ['description' => 'Scholarship / discount - '.$month.': '.$structure->discount_reason, 'amount' => Money::decimal(-Money::cents($structure->discount))];
            }
        }
        if ($firstInvoice) {
            foreach ($structure->charges as $name => $amount) {
                if (Money::cents($amount) > 0) {
                    $items[] = ['description' => ucfirst($name).' fee (once per enrollment/year)', 'amount' => $amount];
                }
            }
        }

        return $items;
    }

    public function reconcile(Fee $fee, array $data, User $actor): void
    {
        $this->locked(function () use ($fee, $data, $actor) {
            $fee = $fee->fresh();
            if ($fee->invoice_number || $fee->legacy_reviewed) {
                $this->fail('This invoice is already classified. Its historical coverage cannot be overwritten.');
            }
            if ($data['coverage_type'] === 'non_tuition') {
                $fee->update(['legacy_reviewed' => true]);
            } else {
                $class = SchoolClass::findOrFail($data['school_class_id'] ?? 0);
                $structure = FeeStructure::findOrFail($data['fee_structure_id'] ?? 0);
                if (! $fee->student->studyClasses()->whereKey($class->id)->exists() || ! $structure->approved_at || $class->program !== $structure->program || (int) $class->classGroup?->grade !== (int) $structure->grade || $fee->currency !== $structure->currency) {
                    $this->fail('Choose an enrolled class and approved matching fee calendar in the same currency.');
                }
                $start = (int) ($data['start_month'] ?? 0);
                $end = (int) ($data['end_month'] ?? 0);
                if ($start < 1 || $end < $start || $end > $structure->billable_months) {
                    $this->fail('Choose valid historical billing months.');
                }
                $key = $this->enrollmentKey($class);
                if (DB::table('invoice_periods')->where('student_id', $fee->student_id)->where('academic_year', $structure->academic_year)->where('enrollment_key', $key)->whereBetween('month_index', [$start, $end])->exists()) {
                    $this->fail('One or more of these months already belong to another invoice. Review the historical coverage.');
                }
                foreach (range($start, $end) as $index) {
                    DB::table('invoice_periods')->insert(['fee_id' => $fee->id, 'student_id' => $fee->student_id, 'academic_year' => $structure->academic_year, 'enrollment_key' => $key, 'month_index' => $index]);
                }
                $fee->update(['legacy_reviewed' => true, 'enrollment_key' => $key, 'academic_year' => $structure->academic_year, 'program' => $class->program, 'grade' => $structure->grade, 'fee_structure_id' => $structure->id]);
            }
            $this->audit($actor, 'reconcile_legacy_coverage', 'fee', $fee->id, $data);
        });
    }

    public function payment(Fee $fee, array $data, User $actor): FeePayment
    {
        return $this->locked(function () use ($fee, $data, $actor) {
            $existing = FeePayment::where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->fee_id !== $fee->id || $existing->amount !== Money::decimal(Money::cents($data['amount'])) || $existing->method !== $data['method'] || $existing->paid_on->format('Y-m-d') !== $data['paid_on'] || ($existing->reference ?? '') !== ($data['reference'] ?? '')) {
                    $this->fail('This submission key has already been used for a different payment.');
                }

                return $existing;
            }
            $fee = $fee->fresh();
            $amount = Money::cents($data['amount']);
            if ($amount <= 0 || $amount > $fee->balanceCents()) {
                $this->fail('Payment must be positive and cannot exceed the outstanding balance.');
            }
            $payment = FeePayment::create($data + ['fee_id' => $fee->id, 'currency' => $fee->currency, 'receipt_number' => 'RCT-'.Str::upper((string) Str::uuid()), 'recorded_by' => $actor->id]);
            $fee->update(['paid' => Money::decimal(Money::cents($fee->paid) + $amount), 'paid_at' => $data['paid_on']]);
            $this->audit($actor, 'payment', 'fee_payment', $payment->id, $payment->toArray());

            return $payment;
        });
    }

    public function adjust(Fee $fee, array $data, User $actor): void
    {
        $this->locked(function () use ($fee, $data, $actor) {
            if (! in_array($data['kind'], ['discount', 'waiver', 'credit', 'charge', 'refund'])) {
                $this->fail('Choose a valid adjustment type.');
            }
            $existing = FinancialAdjustment::where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->fee_id !== $fee->id || $existing->kind !== $data['kind'] || $existing->amount !== Money::decimal(Money::cents($data['amount'])) || $existing->reason !== $data['reason'] || (int) $existing->fee_payment_id !== (int) ($data['fee_payment_id'] ?? 0)) {
                    $this->fail('Submission key already used for another adjustment.');
                }

                return;
            }
            $fee = $fee->fresh();
            $amount = Money::cents($data['amount']);
            if ($amount <= 0) {
                $this->fail('Adjustment must be positive.');
            }
            if (in_array($data['kind'], ['discount', 'credit', 'waiver']) && $amount > $fee->balanceCents()) {
                $this->fail('A credit or waiver cannot exceed the outstanding balance. Refund an existing payment separately.');
            }
            if ($data['kind'] === 'refund') {
                $payment = $fee->payments()->whereKey($data['fee_payment_id'] ?? 0)->first();
                if (! $payment) {
                    $this->fail('Choose the original recorded payment to refund. Legacy opening payments need documented reconciliation before refunding.');
                }
                $refunded = $fee->adjustments->where('kind', 'refund')->where('fee_payment_id', $payment->id)->sum(fn ($a) => Money::cents($a->amount));
                if ($amount > Money::cents($payment->amount) - $refunded) {
                    $this->fail('Refund exceeds the remaining amount of this payment.');
                }
                $fee->update(['paid' => Money::decimal(Money::cents($fee->paid) - $amount)]);
            } else {
                $data['fee_payment_id'] = null;
            }
            $adjustment = FinancialAdjustment::create($data + ['fee_id' => $fee->id, 'recorded_by' => $actor->id]);
            $this->audit($actor, 'adjustment', 'financial_adjustment', $adjustment->id, $adjustment->toArray());
        });
    }

    public function summary($fees): array
    {
        $summary = [];
        foreach ($fees as $fee) {
            $currency = $fee->currency ?: 'USD';
            $s = $summary[$currency] ?? ['billed' => 0, 'paid' => 0, 'balance' => 0, 'overdue' => 0, 'month' => 0, 'unpaid_students' => [], 'programs' => [], 'trend' => [], 'opening' => 0, 'next_due' => null];
            $s['billed'] += $fee->netCents();
            $s['paid'] += Money::cents($fee->paid);
            $s['balance'] += $fee->balanceCents();
            $s['opening'] += Money::cents($fee->opening_paid ?? '0.00');
            if ($fee->due_date->lt(today())) {
                $s['overdue'] += max(0, $fee->balanceCents());
            }
            if ($fee->balanceCents() > 0) {
                $s['unpaid_students'][$fee->student_id] = true;
                $date = $fee->due_date->format('Y-m-d');
                if ($s['next_due'] === null || $date < $s['next_due']) {
                    $s['next_due'] = $date;
                }
            }
            $program = $fee->program ?: 'Legacy / unclassified';
            $s['programs'][$program] = ($s['programs'][$program] ?? 0) + Money::cents($fee->paid);
            foreach ($fee->payments as $payment) {
                $month = $payment->paid_on->format('Y-m');
                $s['trend'][$month] = ($s['trend'][$month] ?? 0) + Money::cents($payment->amount);
                if ($month === today()->format('Y-m')) {
                    $s['month'] += Money::cents($payment->amount);
                }
            }
            foreach ($fee->adjustments->where('kind', 'refund') as $refund) {
                $month = $refund->created_at->format('Y-m');
                $s['trend'][$month] = ($s['trend'][$month] ?? 0) - Money::cents($refund->amount);
                if ($month === today()->format('Y-m')) {
                    $s['month'] -= Money::cents($refund->amount);
                }
            }
            ksort($s['trend']);
            $summary[$currency] = $s;
        }

        return $summary;
    }
}
