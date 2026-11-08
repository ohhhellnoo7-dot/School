<?php

namespace App\Services;

use App\Models\BulkPaymentRun;
use App\Models\Fee;
use App\Models\FeeStructure;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BulkTuitionPaymentService
{
    public function __construct(private SchoolFinanceService $finance) {}

    public function availability(): array
    {
        $last = BulkPaymentRun::latest('id')->first();

        return ['last' => $last, 'locked' => $last && today()->lt($last->next_on), 'next_on' => $last?->next_on];
    }

    private function cycle(int $months): array
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        $state = $this->availability();
        $last = $state['last'];
        $onTime = $last && $today->format('Y-m-d') === $last->next_on->format('Y-m-d');
        $anchor = $onTime ? CarbonImmutable::parse($last->anchor_on) : $today;
        $before = $onTime ? $last->months_before + $last->months : 0;

        return $state + ['starts_on' => $today, 'anchor_on' => $anchor, 'months_before' => $before, 'next_date' => $anchor->addMonthsNoOverflow($before + $months)];
    }

    private function rate($class, $month, $structures): ?FeeStructure
    {
        $grade = $class->classGroup?->grade;
        if (! $grade && preg_match('/(?:Grade\s*)?(\d+)/i', $class->name, $match)) {
            $grade = (int) $match[1];
        }

        $matches = $structures->filter(function ($rate) use ($class, $grade, $month) {
            $index = ($month->year - $rate->starts_on->year) * 12 + $month->month - $rate->starts_on->month + 1;

            return $rate->program === $class->program && (int) $rate->grade === (int) $grade && $index >= 1 && $index <= $rate->billable_months;
        });

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function periodKey($student, $year, $enrollment, $index): string
    {
        return implode('|', [$student, $year, $enrollment, $index]);
    }

    public function preview(int $months): array
    {
        if (! in_array($months, [1, 6, 12], true)) {
            $this->finance->fail('Choose 1, 6 or 12 months.');
        }
        $cycle = $this->cycle($months);
        $students = Student::where('status', 'active')->whereDate('enrollment_date', '<=', today())
            ->whereHas('parent', fn ($q) => $q->where('role', 'parent'))->with(['parent:id,name', 'studyClasses' => fn ($q) => $q->orderBy('school_classes.id'), 'studyClasses.classGroup'])->orderBy('id')->get();
        $structures = FeeStructure::where('status', 'active')->whereNotNull('approved_at')->whereDate('effective_from', '<=', today())
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))->orderByDesc('academic_year')->orderByDesc('id')->get();
        $allPeriods = DB::table('invoice_periods')->whereIn('student_id', $students->pluck('id'))->orderBy('id')->get();
        $coverage = $allPeriods->keyBy(fn ($p) => $this->periodKey($p->student_id, $p->academic_year, $p->enrollment_key, $p->month_index));
        $oldFees = Fee::with('adjustments')->whereIn('student_id', $students->pluck('id'))->orderBy('id')->get();
        $sessions = Program::sessionMap();
        $errors = [];
        $tasks = [];
        $selected = [];
        $existingIds = [];
        $rows = [];
        $totals = [];
        foreach ($students as $student) {
            if ($oldFees->contains(fn ($fee) => $fee->student_id === $student->id && ! $fee->invoice_number && ! $fee->legacy_reviewed)) {
                $errors[] = $student->name.': review historical fees before bulk payment.';

                continue;
            }
            if ($student->studyClasses->isEmpty()) {
                $errors[] = $student->name.': no program enrollment.';

                continue;
            }
            foreach ($student->studyClasses->groupBy(fn ($c) => $this->finance->enrollmentKey($c)) as $enrollment => $classes) {
                $class = $classes->sortBy('id')->first();
                $signatures = [];
                foreach ($classes as $candidate) {
                    $rate = $this->rate($candidate, $cycle['starts_on']->startOfMonth(), $structures);
                    $signatures[] = $rate ? $rate->monthlyForSession($candidate->session).'|'.$rate->quarterlyForSession($candidate->session).'|'.$rate->packageMonths() : 'missing';
                }
                if (count(array_unique($signatures)) > 1) {
                    $errors[] = $student->name.': multiple differently priced sessions in '.$class->name.'. Review the student enrollment before bulk payment.';

                    continue;
                }
                if (! in_array($class->session, $sessions[$class->program] ?? [], true)) {
                    $errors[] = $student->name.': configure the program/session for '.$class->name.'.';

                    continue;
                }
                foreach (range(0, $months - 1) as $offset) {
                    $month = $cycle['starts_on']->startOfMonth()->addMonthsNoOverflow($offset);
                    $rate = $this->rate($class, $month, $structures);
                    if (! $rate) {
                        $errors[] = $student->name.': no single approved '.$class->program.' tuition covering '.$class->name.' / '.$class->session.' / '.$month->format('M Y').'.';
                        break;
                    }
                    $index = ($month->year - $rate->starts_on->year) * 12 + $month->month - $rate->starts_on->month + 1;
                    $periodKey = $this->periodKey($student->id, $rate->academic_year, $enrollment, $index);
                    $selected[$periodKey] = true;
                    if ($coverage->has($periodKey)) {
                        $existingIds[$coverage[$periodKey]->fee_id] = true;

                        continue;
                    }
                    $key = $student->id.'|'.$enrollment.'|'.$rate->id;
                    $tasks[$key] ??= ['student' => $student, 'class' => $class, 'rate' => $rate, 'indices' => []];
                    $tasks[$key]['indices'][] = $index;
                }
            }
        }
        $tasks = array_values($tasks);
        foreach ($tasks as &$task) {
            $first = ! $allPeriods->contains(fn ($p) => $p->student_id === $task['student']->id && $p->academic_year === $task['rate']->academic_year && $p->enrollment_key === $this->finance->enrollmentKey($task['class']));
            try {
                $items = $this->finance->quoteItems($task['rate'], $task['class']->session, $task['indices'], $first, $months === 1 ? 'monthly' : 'bulk');
            } catch (ValidationException $e) {
                $errors[] = $task['student']->name.': '.collect($e->errors())->flatten()->join(' ');

                continue;
            }
            $amount = array_sum(array_map(fn ($item) => Money::cents($item['amount']), $items));
            $task['amount'] = Money::decimal($amount);
            $totals[$task['rate']->currency] = ($totals[$task['rate']->currency] ?? 0) + $amount;
            $rows[] = ['parent' => $task['student']->parent->name, 'student' => $task['student']->name, 'program' => $task['class']->program, 'grade' => $task['rate']->grade, 'session' => $task['class']->session, 'year' => $task['rate']->academic_year, 'amount' => $task['amount'], 'currency' => $task['rate']->currency, 'existing' => false];
        }
        unset($task);
        $existing = [];
        foreach (array_keys($existingIds) as $id) {
            $fee = $oldFees->firstWhere('id', $id);
            if (! $fee) {
                $errors[] = 'An archived invoice reserves this billing period. Review its coverage first.';

                continue;
            }
            if ($allPeriods->where('fee_id', $id)->contains(fn ($p) => ! isset($selected[$this->periodKey($p->student_id, $p->academic_year, $p->enrollment_key, $p->month_index)]))) {
                $errors[] = $fee->display_number.': existing invoice covers a longer period. Choose its full duration or settle it individually.';

                continue;
            }
            $student = $students->firstWhere('id', $fee->student_id);
            $amount = max(0, $fee->balanceCents());
            $existing[] = $fee;
            $totals[$fee->currency] = ($totals[$fee->currency] ?? 0) + $amount;
            $rows[] = ['parent' => $student->parent->name, 'student' => $student->name, 'program' => $fee->program, 'grade' => $fee->grade, 'session' => $fee->session, 'year' => $fee->academic_year, 'amount' => Money::decimal($amount), 'currency' => $fee->currency, 'existing' => true];
        }
        $errors = array_values(array_unique($errors));
        if ($students->isEmpty()) {
            $errors[] = 'No active students linked to parent accounts are available.';
        }
        $fingerprint = hash('sha256', json_encode(['months' => $months, 'start' => $cycle['starts_on']->format('Y-m-d'), 'next' => $cycle['next_date']->format('Y-m-d'), 'last' => $cycle['last']?->id, 'rows' => $rows, 'errors' => $errors, 'tasks' => array_map(fn ($t) => [$t['student']->id, $t['class']->id, $t['rate']->id, $t['rate']->getAttributes(), $t['indices']], $tasks), 'existing' => array_map(fn ($fee) => [$fee->id, $fee->paid, $fee->balance], $existing)], JSON_THROW_ON_ERROR));

        return compact('cycle', 'months', 'tasks', 'existing', 'rows', 'totals', 'errors', 'fingerprint') + ['students_count' => $students->count(), 'parents_count' => $students->pluck('parent_id')->unique()->count()];
    }

    public function pay(array $data, User $actor): BulkPaymentRun
    {
        abort_unless($actor->role === 'administrator', 403);

        return $this->finance->locked(function () use ($data, $actor) {
            $existing = BulkPaymentRun::where('request_key', $data['request_key'])->first();
            if ($existing) {
                if ($existing->months !== (int) $data['months'] || $existing->method !== $data['method'] || ($existing->reference ?? '') !== ($data['reference'] ?? '')) {
                    $this->finance->fail('This submission key was already used for a different payment.');
                }

                return $existing;
            }
            if ($this->availability()['locked']) {
                $this->finance->fail('Bulk payment is locked until '.$this->availability()['next_on']->format('d F Y').'.');
            }
            $plan = $this->preview((int) $data['months']);
            if ($plan['errors']) {
                $this->finance->fail('Fix the listed enrollment or tuition issues before paying.');
            }
            if (! hash_equals($plan['fingerprint'], $data['fingerprint'])) {
                $this->finance->fail('Prices, enrollments or balances changed. Review a fresh payment preview.');
            }
            $cycle = $plan['cycle'];
            $run = BulkPaymentRun::create(['request_key' => $data['request_key'], 'months' => $plan['months'], 'starts_on' => $cycle['starts_on'], 'anchor_on' => $cycle['anchor_on'], 'months_before' => $cycle['months_before'], 'next_on' => $cycle['next_date'], 'totals' => array_map(fn ($cents) => Money::decimal($cents), $plan['totals']), 'parents_count' => $plan['parents_count'], 'students_count' => $plan['students_count'], 'recorded_by' => $actor->id, 'method' => $data['method'], 'reference' => $data['reference'] ?? null]);
            $fees = collect($plan['existing']);
            foreach ($plan['tasks'] as $task) {
                $indices = $task['indices'];
                $issued = $this->finance->issue($task['student'], $task['class'], $task['rate'], $plan['months'] === 1 ? 'monthly' : 'bulk', min($indices), max($indices) - min($indices) + 1, $actor);
                $fees = $fees->merge($issued);
            }
            $paid = [];
            foreach ($fees->unique('id') as $fee) {
                $fee = $fee->fresh();
                $payment = null;
                if ($fee->balanceCents() > 0) {
                    $amount = $fee->balanceCents();
                    $paid[$fee->currency] = ($paid[$fee->currency] ?? 0) + $amount;
                    $payment = $this->finance->payment($fee, ['request_key' => (string) Str::uuid(), 'amount' => $fee->balance, 'paid_on' => today()->format('Y-m-d'), 'method' => $data['method'], 'reference' => 'Bulk tuition #'.$run->id.($run->reference ? ': '.$run->reference : '')], $actor);
                }
                DB::table('bulk_payment_allocations')->insert(['bulk_payment_run_id' => $run->id, 'fee_id' => $fee->id, 'fee_payment_id' => $payment?->id]);
            }
            foreach ($plan['totals'] as $currency => $expected) {
                if (($paid[$currency] ?? 0) !== $expected) {
                    $this->finance->fail('Payment total changed; no bulk payment was saved. Review a fresh preview.');
                }
            }
            $this->finance->audit($actor, 'bulk_tuition_payment', 'bulk_payment_run', $run->id, $run->toArray());

            return $run;
        });
    }
}
