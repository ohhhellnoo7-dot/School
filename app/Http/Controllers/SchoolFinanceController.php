<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\ParentPaymentSchedule;
use App\Services\SchoolFinanceService;
use App\Support\Money;
use App\Support\SchoolModules;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SchoolFinanceController extends Controller
{
    public function parentReminder(Request $request, User $parent, SchoolFinanceService $service)
    {
        abort_unless($request->user()->role === 'administrator' && $parent->role === 'parent', 403);
        $data = $request->validate(['next_payment_on' => 'nullable|date_format:Y-m-d|after_or_equal:today']);
        $service->locked(function () use ($data, $parent, $request, $service) {
            $previous = DB::table('parent_payment_reminders')->where('parent_id', $parent->id)->first();
            DB::table('parent_payment_reminders')->updateOrInsert(['parent_id' => $parent->id], ['next_payment_on' => $data['next_payment_on'] ?? null, 'recorded_by' => $request->user()->id, 'created_at' => $previous?->created_at ?? now(), 'updated_at' => now()]);
            $service->audit($request->user(), 'set_next_parent_payment', 'parent', $parent->id, ['previous_date' => $previous?->next_payment_on, 'next_date' => $data['next_payment_on'] ?? null]);
        });

        return back()->with('success', 'Next payment date saved.');
    }

    public function parents(Request $request, SchoolFinanceService $service)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:paid,partially_paid,unpaid,waived,no_invoices',
        ]);
        $families = User::where('role', 'parent')->with(['children.fees.adjustments', 'children.fees.items', 'children.fees.payments'])
            ->orderBy('name')->get()->map(function ($parent) use ($service) {
                $fees = $parent->children->flatMap->fees;
                $currencies = $service->summary($fees);
                $hasDebt = $fees->contains(fn ($fee) => $fee->balanceCents() > 0);
                $hasPayments = $fees->contains(fn ($fee) => Money::cents($fee->paid) > 0);
                $status = $fees->isEmpty() ? 'no_invoices' : ($hasDebt ? ($hasPayments ? 'partially_paid' : 'unpaid') : ($hasPayments ? 'paid' : 'waived'));

                return compact('parent', 'currencies', 'status') + ['invoice_count' => $fees->count()];
            });
        if ($request->filled('search')) {
            $needle = mb_strtolower(trim($filters['search']));
            $families = $families->filter(fn ($family) => str_contains(mb_strtolower($family['parent']->name.' '.$family['parent']->email.' '.$family['parent']->phone), $needle)
                || $family['parent']->children->contains(fn ($child) => str_contains(mb_strtolower($child->name.' '.$child->student_number), $needle)));
        }
        $counts = $families->countBy('status');
        if ($request->filled('status')) {
            $families = $families->where('status', $filters['status']);
        }
        $page = max(1, $request->integer('page', 1));
        $families = new LengthAwarePaginator($families->forPage($page, 25)->values(), $families->count(), 25, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $parentsOnPage = new Collection(collect($families->items())->pluck('parent')->all());
        $parentsOnPage->load('children.studyClasses.classGroup');
        $paymentSchedules = app(ParentPaymentSchedule::class)->forFamilies($parentsOnPage);
        $unlinked = Student::whereHas('fees')->whereDoesntHave('parent', fn ($q) => $q->where('role', 'parent'))->count();

        return view('school.finance.parents', compact('families', 'counts', 'unlinked', 'paymentSchedules'));
    }

    private function visible(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['administrator', 'parent', 'student']), 403);

        return SchoolModules::query('fees', $request->user())->with(['student', 'items', 'adjustments', 'payments']);
    }

    public function index(Request $request, SchoolFinanceService $service)
    {
        if (! $request->filled('status') && $request->filled('filter')) {
            $request->merge(['status' => $request->filter === 'pending' ? 'unpaid' : $request->filter]);
        }
        $filters = $request->validate([
            'search' => 'nullable|string|max:100', 'program' => 'nullable|string|max:30', 'grade' => 'nullable|integer|between:1,12',
            'academic_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'], 'status' => 'nullable|in:unpaid,partially_paid,paid,overdue,waived,refunded',
        ]);
        $query = $this->visible($request)
            ->when($filters['search'] ?? null, fn ($q, $value) => $q->whereHas('student', fn ($s) => $s->search($value)))
            ->when($filters['program'] ?? null, fn ($q, $v) => $q->where('program', $v))
            ->when($filters['grade'] ?? null, fn ($q, $v) => $q->where('grade', $v))
            ->when($filters['academic_year'] ?? null, fn ($q, $v) => $q->where('academic_year', $v));
        $all = $query->orderByDesc('id')->get();
        $summary = $service->summary($all);
        $studentSummaries = $request->user()->role === 'administrator' ? collect() : $all->groupBy('student_id')->map(fn ($invoices) => [
            'student' => $invoices->first()->student,
            'currencies' => $service->summary($invoices),
        ]);
        if ($request->filled('status')) {
            $all = $all->filter(fn ($fee) => $fee->payment_status === $request->status);
        }
        $page = max(1, $request->integer('page', 1));
        $size = $request->attributes->get('finance_export') ? max(1, $all->count()) : 25;
        if ($request->attributes->get('finance_export')) {
            $page = 1;
        }
        $fees = new LengthAwarePaginator($all->forPage($page, $size)->values(), $all->count(), $size, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('school.finance.index', compact('fees', 'summary', 'studentSummaries') + ['records' => $fees]);
    }

    public function invoice(Request $request, int $fee)
    {
        $fee = $this->visible($request)->findOrFail($fee);
        $structures = $request->user()->role === 'administrator' ? FeeStructure::whereNotNull('approved_at')->orderBy('program')->get() : collect();

        return view('school.finance.invoice', compact('fee', 'structures'));
    }

    public function receipt(Request $request, int $payment)
    {
        $payment = FeePayment::findOrFail($payment);
        $fee = $this->visible($request)->findOrFail($payment->fee_id);

        return view('school.finance.receipt', compact('fee', 'payment'));
    }

    public function settings(Request $request)
    {
        $edit = $request->filled('version') ? FeeStructure::findOrFail($request->integer('version')) : null;
        $structures = FeeStructure::orderByDesc('id')->paginate(30);
        $audits = DB::table('finance_audits')->leftJoin('users', 'users.id', '=', 'finance_audits.actor_id')
            ->select('finance_audits.*', 'users.name as actor_name')->orderByDesc('finance_audits.id')->limit(30)->get();

        return view('school.finance.settings', compact('structures', 'edit', 'audits'));
    }

    public function saveStructure(Request $request, SchoolFinanceService $service)
    {
        $money = ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'];
        $data = $request->validate([
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'], 'program' => ['required', Rule::in(['Khmer', 'English', 'Chinese', 'Japan'])],
            'grade' => 'required|integer|between:1,12', 'currency' => 'required|in:USD,KHR',
            'monthly_tuition' => $money, 'billable_months' => 'required|integer|between:1,12', 'semester_months' => 'required|integer|between:1,12',
            'starts_on' => 'required|date_format:Y-m-d', 'effective_from' => 'required|date_format:Y-m-d', 'effective_until' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from',
            'due_day' => 'required|integer|between:1,28', 'discount' => $money, 'discount_reason' => 'nullable|string|max:255',
            'charges' => 'required|array:registration,books,uniform,examination,other', 'charges.*' => $money,
            'supersedes_id' => 'nullable|integer|exists:fee_structures,id',
        ]);
        if ((int) substr($data['academic_year'], 5) !== (int) substr($data['academic_year'], 0, 4) + 1) {
            $service->fail('Academic year must span two consecutive years.');
        }
        if ($data['program'] === 'Khmer' && $data['grade'] < 7) {
            $service->fail('Khmer fees apply to Grades 7–12.');
        }
        if ($data['program'] === 'Japan' && ! ClassGroup::where('program', 'Japan')->where('grade', $data['grade'])->exists() && ! SchoolClass::where('program', 'Japan')->where('name', 'Grade '.$data['grade'])->exists()) {
            $service->fail('Choose a grade already configured for the Japanese program.');
        }
        if (Money::cents($data['discount']) > Money::cents($data['monthly_tuition'])) {
            $service->fail('Monthly discount cannot exceed monthly tuition.');
        }
        if (Money::cents($data['discount']) > 0 && empty($data['discount_reason'])) {
            $service->fail('Give the scholarship or discount a reason.');
        }
        if ($data['semester_months'] > $data['billable_months']) {
            $service->fail('Semester months cannot exceed billable months.');
        }
        $service->locked(function () use ($data, $request, $service) {
            $structure = FeeStructure::create($data + ['status' => 'draft', 'created_by' => $request->user()->id]);
            $service->audit($request->user(), 'draft', 'fee_structure', $structure->id, $structure->toArray());
        });

        return redirect()->route('finance.settings')->with('success', 'New fee version saved as a draft. Review and approve to activate it.');
    }

    public function approve(Request $request, FeeStructure $structure, SchoolFinanceService $service)
    {
        $service->approve($structure, $request->user());

        return back()->with('success', 'Fee version approved. Existing invoices retain their original amounts.');
    }

    public function deactivate(Request $request, FeeStructure $structure, SchoolFinanceService $service)
    {
        $service->locked(function () use ($structure, $request, $service) {
            $structure->update(['status' => 'inactive']);
            $service->audit($request->user(), 'deactivate', 'fee_structure', $structure->id, []);
        });

        return back()->with('success', 'Fee version deactivated. Issued invoices remain unchanged.');
    }

    public function proposals(Request $request, SchoolFinanceService $service)
    {
        $data = $request->validate(['academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'], 'starts_on' => 'required|date_format:Y-m-d', 'billable_months' => 'required|integer|between:1,12']);
        if ((int) substr($data['academic_year'], 5) !== (int) substr($data['academic_year'], 0, 4) + 1) {
            $service->fail('Academic year must span consecutive years.');
        }
        $service->locked(function () use ($data, $request, $service) {
            $grades = ['Khmer' => range(7, 12), 'English' => range(1, 12), 'Chinese' => range(1, 12), 'Japan' => ClassGroup::where('program', 'Japan')->pluck('grade')->unique()->all()];
            foreach ($grades as $program => $levels) {
                foreach ($levels as $grade) {
                    if (FeeStructure::where('academic_year', $data['academic_year'])->where('program', $program)->where('grade', $grade)->exists()) {
                        continue;
                    }
                    $amount = match ($program) {
                        'Khmer' => $grade <= 9 ? '25.00' : '35.00', 'English' => $grade <= 6 ? '30.00' : '40.00', 'Chinese' => '20.00', default => '25.00'
                    };
                    $structure = FeeStructure::create($data + ['program' => $program, 'grade' => $grade, 'monthly_tuition' => $amount, 'currency' => 'USD', 'semester_months' => min(6, $data['billable_months']), 'effective_from' => $data['starts_on'], 'due_day' => 10, 'charges' => array_fill_keys(['registration', 'books', 'uniform', 'examination', 'other'], '0.00'), 'discount' => '0.00', 'status' => 'draft', 'created_by' => $request->user()->id]);
                    $service->audit($request->user(), 'proposed_example', 'fee_structure', $structure->id, $structure->toArray());
                }
            }
        });

        return back()->with('success', 'Example drafts added for unconfigured grades only. No prices were activated or overwritten.');
    }

    public function billing(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:100', 'student' => 'nullable|integer|exists:students,id']);
        $students = Student::with('user')->when($request->search, fn ($q, $v) => $q->search($v))->orderBy('name')->limit(50)->get();
        $student = $request->filled('student') ? Student::with('studyClasses.classGroup')->findOrFail($request->integer('student')) : null;
        $structures = FeeStructure::where('status', 'active')->orderBy('program')->orderBy('grade')->get();

        return view('school.finance.billing', compact('students', 'student', 'structures'));
    }

    public function issue(Request $request, SchoolFinanceService $service)
    {
        $data = $request->validate(['student_id' => 'required|integer|exists:students,id', 'school_class_id' => 'required|integer|exists:school_classes,id', 'fee_structure_id' => 'required|integer|exists:fee_structures,id', 'plan' => 'required|in:monthly,semester,annual,installments', 'start_month' => 'required|integer|between:1,12', 'count' => 'required|integer|between:1,12']);
        $fees = $service->issue(Student::findOrFail($data['student_id']), SchoolClass::findOrFail($data['school_class_id']), FeeStructure::findOrFail($data['fee_structure_id']), $data['plan'], (int) $data['start_month'], (int) $data['count'], $request->user());

        return redirect()->route('finance.invoice', $fees[0])->with('success', 'Invoices ready. Previously billed months were excluded.');
    }

    public function pay(Request $request, int $fee, SchoolFinanceService $service)
    {
        $data = $request->validate(['amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'], 'request_key' => 'required|uuid', 'paid_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'method' => 'required|in:cash,bank_transfer,qr,card,other', 'reference' => 'nullable|string|max:255']);
        $payment = $service->payment(Fee::findOrFail($fee), $data, $request->user());

        return redirect()->route('finance.receipt', $payment)->with('success', 'Payment recorded.');
    }

    public function adjust(Request $request, int $fee, SchoolFinanceService $service)
    {
        $data = $request->validate(['amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'], 'request_key' => 'required|uuid', 'kind' => 'required|in:discount,waiver,credit,charge,refund', 'reason' => 'required|string|max:500', 'fee_payment_id' => 'nullable|integer|exists:fee_payments,id']);
        $service->adjust(Fee::findOrFail($fee), $data, $request->user());

        return back()->with('success', 'Adjustment recorded in the audit history.');
    }

    public function reconcile(Request $request, int $fee, SchoolFinanceService $service)
    {
        $data = $request->validate(['coverage_type' => 'required|in:tuition,non_tuition', 'school_class_id' => 'nullable|required_if:coverage_type,tuition|integer|exists:school_classes,id', 'fee_structure_id' => 'nullable|required_if:coverage_type,tuition|integer|exists:fee_structures,id', 'start_month' => 'nullable|required_if:coverage_type,tuition|integer|between:1,12', 'end_month' => 'nullable|required_if:coverage_type,tuition|integer|between:1,12', 'reason' => 'required|string|max:500']);
        $service->reconcile(Fee::findOrFail($fee), $data, $request->user());

        return back()->with('success', 'Historical billing coverage reviewed. Original amounts and payments are unchanged.');
    }

    public function export(Request $request)
    {
        // Export follows the same authorization and filters as the invoice directory.
        $request->attributes->set('finance_export', true);
        $view = $this->index($request, app(SchoolFinanceService::class));
        $fees = $view->getData()['fees'];

        return response()->streamDownload(function () use ($fees) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Invoice', 'Student', 'Program', 'Grade', 'Year', 'Currency', 'Net billed', 'Paid', 'Balance', 'Status'], escape: '');
            foreach ($fees as $fee) {
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'".$v : $v, [$fee->display_number, $fee->student->name, $fee->program ?: 'Legacy', $fee->grade, $fee->academic_year, $fee->currency, Money::decimal($fee->netCents()), $fee->paid, $fee->balance, $fee->payment_status]), escape: '');
            }
            fclose($out);
        }, 'school-fees.csv', ['Content-Type' => 'text/csv']);
    }
}
