<?php

namespace App\Http\Controllers;

use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\SchoolFinanceService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class EnrollmentPaymentController extends Controller
{
    public function show(Request $request, Student $student, SchoolFinanceService $finance)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $fee = $student->fees()->where('invoice_number', 'ENR-'.$student->id)->first();
        if ($fee) return redirect()->route('fees.pay', $fee);

        $student->load(['schoolClass', 'studyClasses']);
        $classes = collect([$student->schoolClass])->filter()->concat($student->studyClasses)->unique('id');
        [$tuition, $invoiceId, $problem] = $this->tuitionQuote($student, $finance);
        if ($invoiceId) {
            return redirect()->route('fees.pay', $invoiceId);
        }

        $total = Money::display($tuition->sum(fn ($row) => collect($row['items'])->sum(fn ($item) => Money::cents($item['amount']))));
        $currency = $tuition->first()['rate']->currency ?? null;

        return view('school.students.enrollment-payment', compact('student', 'classes', 'tuition', 'problem', 'total', 'currency'));
    }

    public function store(Request $request, Student $student, SchoolFinanceService $finance)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $method = $request->validate(['method' => 'required|in:cash,qr'])['method'];
        $fee = $finance->locked(function () use ($student, $method, $request, $finance) {
            $existing = $student->fees()->where('invoice_number', 'ENR-'.$student->id)->first();
            if ($existing) return $existing;
            $student->refresh();
            [$tuition, $invoiceId, $problem] = $this->tuitionQuote($student, $finance);
            if ($problem) $finance->fail($problem);
            if ($invoiceId || $tuition->isNotEmpty()) {
                if ($invoiceId) {
                    $invoice = $student->fees()->findOrFail($invoiceId);
                } elseif ($tuition->count() === 1) {
                    ['class' => $class, 'rate' => $rate, 'month' => $month] = $tuition->first();
                    [$invoice] = $finance->issue($student, $class, $rate, 'monthly', $month, 1, $request->user());
                } else {
                    $invoice = $finance->issueCombinedEnrollment($student, $tuition, $request->user());
                }
                if (! $invoice->checkout_submitted_at) {
                    $invoice->update(['checkout_method' => $method]);
                }

                return $invoice;
            }

            $data = $request->validate([
                'amount_usd' => ['required', 'numeric', 'min:0.01', 'max:999999', 'decimal:0,2'],
                'currency' => 'required|in:USD,KHR',
            ]);
            $usdCents = Money::cents($data['amount_usd']);
            $amount = Money::decimal($usdCents * ($data['currency'] === 'KHR' ? 4100 : 1));
            $fee = $student->fees()->create([
                'invoice_number' => 'ENR-'.$student->id,
                'description' => 'Enrollment fee'.($data['currency'] === 'KHR' ? ' (USD '.Money::decimal($usdCents).' at 4,100 KHR/USD)' : ''),
                'amount' => $amount, 'currency' => $data['currency'], 'paid' => 0,
                'opening_paid' => 0, 'due_date' => today(), 'legacy_reviewed' => true,
                'issued_by' => $request->user()->id, 'checkout_method' => $method,
            ]);
            $fee->items()->create(['description' => $fee->description, 'amount' => $amount]);
            $finance->audit($request->user(), 'issue_enrollment_invoice', 'fee', $fee->id, $fee->toArray());
            return $fee;
        });

        return redirect()->route('fees.pay', $fee);
    }

    private function tuitionQuote(Student $student, SchoolFinanceService $finance): array
    {
        $classes = $student->studyClasses->sortBy(fn ($class) => $class->id !== $student->school_class_id)
            ->unique(fn ($class) => $finance->enrollmentKey($class));
        $rows = collect();
        $invoiceId = null;
        $matched = 0;
        foreach ($classes as $class) {
            $configured = $this->configuredTuition($student, $class);
            if (! $configured) continue;

            $matched++;
            [$rate, $month] = $configured;
            $key = $finance->enrollmentKey($class);
            $coverage = DB::table('invoice_periods')->where('student_id', $student->id)
                ->where('academic_year', $rate->academic_year)->where('enrollment_key', $key);
            $coveredBy = (clone $coverage)->where('month_index', $month)->value('fee_id');
            if ($coveredBy) {
                $invoiceId = max($invoiceId ?? 0, $coveredBy);
                continue;
            }

            $label = collect([$class->program, trim($class->name.' '.$class->section), $class->session])->filter()->join(' · ');
            $items = $finance->quoteItems($rate, $class->session, [$month], ! $coverage->exists(), 'monthly');
            $rows->push(compact('class', 'rate', 'month', 'key', 'label', 'items'));
        }

        $problem = match (true) {
            $matched > 0 && $matched < $classes->count() => __('An enrolled program is missing an approved tuition rate for this month. Review the Finance rates before continuing.'),
            $rows->pluck('rate.currency')->unique()->count() > 1 => __('The programs use different currencies. Review the Finance rates before continuing.'),
            default => null,
        };

        return [$rows, $rows->isEmpty() && ! $problem ? $invoiceId : null, $problem];
    }

    private function configuredTuition(Student $student, SchoolClass $class): ?array
    {
        if (! $class->program || ! $student->enrollment_date) return null;

        $grade = $class->classGroup?->grade;
        if (! $grade && preg_match('/(?:Grade\s*)?(\d+)/i', $class->name, $match)) {
            $grade = (int) $match[1];
        }
        if (! $grade) return null;

        $enrollment = $student->enrollment_date;
        $rates = FeeStructure::where('program', $class->program)->where('grade', $grade)
            ->where('status', 'active')->whereNotNull('approved_at')->where('effective_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhereDate('effective_until', '>=', today()))
            ->orderByDesc('effective_from')->orderByDesc('id')->get();
        foreach ($rates as $rate) {
            $month = ($enrollment->year - $rate->starts_on->year) * 12 + $enrollment->month - $rate->starts_on->month + 1;
            if ($month >= 1 && $month <= $rate->billable_months) {
                return [$rate, $month];
            }
        }

        return null;
    }
}
