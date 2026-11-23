<?php

namespace App\Services;

use App\Exceptions\StudentTuitionRateException;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StudentTuitionPayment
{
    public function __construct(private SchoolFinanceService $finance) {}

    public static function level(SchoolClass $class): ?int
    {
        return $class->classGroup?->grade ?? (preg_match('/(?:Grade\s*)?(\d+)/i', $class->name, $match) ? (int) $match[1] : null);
    }

    public function quote(Student $student, int $months, ?array $selection = null): array
    {
        $student->load('studyClasses.classGroup');
        if ($student->status !== 'active' || $student->enrollment_date?->isFuture() || $student->studyClasses->isEmpty()) {
            $this->finance->fail('Choose an active student with a current program enrollment.');
        }
        if ($student->fees()->whereNull('invoice_number')->where('legacy_reviewed', false)->exists()) {
            $this->finance->fail('Review this student’s historical invoices before recording tuition.');
        }
        $enrollments = $student->studyClasses->toBase()->groupBy(fn ($class) => $this->finance->enrollmentKey($class))->sortKeys();
        if ($selection !== null) {
            if (! $selection || array_diff($selection, $enrollments->keys()->all())) {
                $this->finance->fail('Select at least one of this student’s enrolled programs.');
            }
            $enrollments = $enrollments->only($selection);
        }

        $rates = FeeStructure::whereIn('program', $student->studyClasses->pluck('program'))->where('status', 'active')
            ->whereNotNull('approved_at')->whereDate('effective_from', '<=', today())
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))
            ->orderBy('id')->get();
        $periods = DB::table('invoice_periods')->where('student_id', $student->id)->orderBy('id')->get();
        $fees = Fee::withTrashed()->with('adjustments')->whereIn('id', $periods->pluck('fee_id'))->get()->keyBy('id');
        $periodKey = fn ($year, $enrollment, $index) => implode('|', [$year, $enrollment, $index]);
        $coverage = $periods->keyBy(fn ($period) => $periodKey($period->academic_year, $period->enrollment_key, $period->month_index));
        $existing = [];
        $selected = [];
        $tasks = [];
        $rows = [];
        $totals = [];
        $plan = $months === 1 ? 'monthly' : 'bulk';

        foreach ($enrollments as $enrollment => $classes) {
            $class = $classes->sortBy('id')->first();
            $classRates = $rates->where('program', $class->program)->where('grade', self::level($class));
            $month = CarbonImmutable::today()->startOfMonth();
            $dates = [];
            while (count($dates) < $months) {
                $matches = $classRates->filter(fn ($rate) => $this->monthIndex($rate, $month) >= 1
                    && $this->monthIndex($rate, $month) <= $rate->billable_months);
                if ($matches->count() !== 1) {
                    throw StudentTuitionRateException::forMonth(
                        'Check '.$class->program.' tuition for '.$class->name.' in '.$month->format('F Y').'.',
                        [
                            'class' => $class,
                            'level' => self::level($class),
                            'month' => $month,
                            'matching' => $matches->count(),
                            'rates' => $matches->isEmpty() ? $classRates : $matches,
                        ],
                    );
                }
                $rate = $matches->first();
                if ($classes->map(fn ($candidate) => [$rate->monthlyForSession($candidate->session), $rate->quarterlyForSession($candidate->session)])->uniqueStrict()->count() > 1) {
                    $this->finance->fail('This enrollment has differently priced sessions. Review '.$class->label.' before recording the payment.');
                }

                $index = $this->monthIndex($rate, $month);
                $period = $coverage->get($periodKey($rate->academic_year, $enrollment, $index));
                $date = $month->format('Y-m');
                $month = $month->addMonth();
                if ($period) {
                    $fee = $fees->get($period->fee_id);
                    if (! $fee || $fee->trashed()) {
                        $this->finance->fail('An archived invoice reserves '.$class->program.' tuition for '.$date.'. Review the invoice first.');
                    }
                    if ($fee->balanceCents() <= 0) {
                        continue;
                    }
                    $existing[$fee->id] = $fee;
                    $selected[$period->id] = true;
                } else {
                    $key = $enrollment.'|'.$rate->id;
                    $tasks[$key] ??= ['class' => $class, 'rate' => $rate, 'indices' => []];
                    $tasks[$key]['indices'][] = $index;
                }
                $dates[] = $date;
            }
            $rows[] = ['enrollment' => $enrollment, 'class' => $class, 'dates' => $dates];
        }

        foreach ($existing as $fee) {
            if ($periods->where('fee_id', $fee->id)->contains(fn ($period) => ! isset($selected[$period->id]))) {
                $this->finance->fail('Invoice '.$fee->display_number.' covers months outside this selection. Settle it from Invoices & payments or choose a duration covering the whole invoice.');
            }
            $totals[$fee->currency] = ($totals[$fee->currency] ?? 0) + $fee->balanceCents();
        }
        foreach ($tasks as &$task) {
            ['class' => $class, 'rate' => $rate, 'indices' => $indices] = $task;
            $first = ! $periods->where('enrollment_key', $this->finance->enrollmentKey($class))->contains('academic_year', $rate->academic_year);
            $items = $this->finance->quoteItems($rate, $class->session, $indices, $first, $plan);
            $task['amount'] = array_sum(array_map(fn ($item) => Money::cents($item['amount']), $items));
            $totals[$rate->currency] = ($totals[$rate->currency] ?? 0) + $task['amount'];
        }
        unset($task);

        $fingerprint = hash('sha256', json_encode([
            $student->id, $months, today()->toDateString(), $totals,
            array_map(fn ($row) => [$row['class']->id, $row['dates']], $rows),
            array_map(fn ($task) => [$task['class']->id, $task['rate']->id, $task['indices'], $task['amount']], $tasks),
            array_map(fn ($fee) => [$fee->id, $fee->balanceCents()], $existing),
        ], JSON_THROW_ON_ERROR));

        return compact('rows', 'totals', 'fingerprint', 'tasks', 'existing', 'plan');
    }

    private function monthIndex(FeeStructure $rate, CarbonImmutable $month): int
    {
        return ($month->year - $rate->starts_on->year) * 12 + $month->month - $rate->starts_on->month + 1;
    }

    public function record(Student $student, array $data, User $actor): array
    {
        abort_unless($actor->role === 'administrator', 403);

        return $this->finance->locked(function () use ($student, $data, $actor) {
            $receiptGroup = $data['request_key'] ?? (string) Str::uuid();
            if (isset($data['request_key'])) {
                $previous = FeePayment::with('fee')->where('receipt_group', $receiptGroup)->orderBy('id')->get();
                if ($previous->isNotEmpty()) {
                    abort_unless($previous->every(fn ($payment) => (int) $payment->recorded_by === (int) $actor->id
                        && (int) $payment->fee?->student_id === (int) $student->id), 409);

                    return ['receipts' => $previous->modelKeys(), 'invoices' => $previous->pluck('fee_id')->unique()->values()->all()];
                }
            }
            $quote = $this->quote($student->refresh(), (int) $data['months'], $data['enrollments'] ?? null);
            if (! hash_equals($quote['fingerprint'], $data['fingerprint'])) {
                $this->finance->fail('The payment preview has changed. Review the new amounts before recording the payment.');
            }

            $fees = collect($quote['existing'])->values();
            foreach ($quote['tasks'] as $task) {
                $indices = $task['indices'];
                $issued = $this->finance->issue($student, $task['class'], $task['rate'], $quote['plan'], min($indices), max($indices) - min($indices) + 1, $actor);
                $fees = $fees->concat($issued);
            }
            $payments = [];
            $actual = array_map(fn () => 0, $quote['totals']);
            foreach ($fees->unique('id') as $fee) {
                $balance = $fee->balanceCents();
                if ($balance <= 0) {
                    continue;
                }
                $payment = $this->finance->payment($fee, [
                    'amount' => Money::decimal($balance), 'paid_on' => today()->toDateString(),
                    'method' => $data['method'], 'reference' => $data['reference'] ?? null,
                    'request_key' => (string) Str::uuid(),
                    'receipt_group' => $receiptGroup,
                ], $actor);
                $actual[$fee->currency] = ($actual[$fee->currency] ?? 0) + $balance;
                $payments[] = $payment->id;
            }
            if ($actual !== $quote['totals']) {
                $this->finance->fail('The payment total changed. Review a fresh preview.');
            }

            return ['receipts' => $payments, 'invoices' => $fees->pluck('id')->unique()->values()->all()];
        });
    }
}
