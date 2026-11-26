<?php
namespace App\Http\Controllers;
use App\Models\SchoolExpense;
use App\Models\EmployeeSalaryPayment;
use App\Models\TeacherAdvanceRequest;
use App\Models\User;
use App\Services\{EmployeeSalaryAdvances, SchoolCashReport, SchoolFinanceService};
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class SchoolExpenseController extends Controller {
 public function index(Request $request) {
  abort_unless($request->user()->role === 'administrator',403);
  $filters=$request->validate(['currency'=>'nullable|in:USD,KHR','month'=>'nullable|date_format:Y-m']);
  $currency=$filters['currency'] ?? 'USD';
  $month=\Carbon\Carbon::createFromFormat('!Y-m',$filters['month'] ?? today()->format('Y-m'));
  $expenses=SchoolExpense::with(['recorder', 'employee'])->where('currency',$currency)
   ->whereBetween('spent_on',[$month->toDateString(),$month->copy()->endOfMonth()->toDateString()])
   ->orderByDesc('spent_on')->orderByDesc('id')->paginate(25)->withQueryString();
  $total=app(SchoolCashReport::class)->currencyTotals($month, $currency);
  $advanceRequests = TeacherAdvanceRequest::with('teacher')->pending()->oldest()->orderBy('id')->get();
  $live = $request->header('X-Expense-Live') === '1';
  $view = view('school.expenses.index', compact('expenses', 'currency', 'month', 'total', 'advanceRequests') + [
   'teachers' => $live ? collect() : $this->teachers(),
   'pendingAdvanceCount' => $advanceRequests->count(),
  ]);
  if ($live) {
   return response()->json([
    'html' => $view->fragments(['expense-live-metrics', 'expense-live-history', 'expense-live-queue']),
    'pendingCount' => $advanceRequests->count(),
    'pendingLabel' => trans_choice(':count pending request|:count pending requests', $advanceRequests->count()),
   ])->header('Cache-Control', 'private, no-store');
  }

  return response($view)->header('Cache-Control', 'private, no-store');
 }
 public function create(Request $request) {
  abort_unless($request->user()->role === 'administrator',403);
  $data=$request->validate(['currency'=>'nullable|in:USD,KHR','date'=>'nullable|date_format:Y-m-d|before_or_equal:today']);
  $date=$data['date'] ?? today()->toDateString();
  $currency=$data['currency'] ?? 'USD';
  $cashReport=app(SchoolCashReport::class)->month($date);
  return view('school.expenses.create',compact('date','currency','cashReport'));
 }
 public function store(Request $request) {
  abort_unless($request->user()->role === 'administrator',403);
  $data=$request->validate(['amount'=>['required','regex:/^\d{1,10}(?:\.\d{1,2})?$/'], 'currency'=>'required|in:USD,KHR',
   'spent_on'=>'required|date_format:Y-m-d|before_or_equal:today','note'=>'nullable|string|max:1000','request_key'=>'required|uuid']);
  $this->record($request, $data);
  return redirect()->route('reports',['date'=>$data['spent_on']])->with('success','Expense recorded. Daily and monthly reports updated.');
 }

 public function createAdvance() {
  return view('school.expenses.advance', ['teachers' => $this->teachers()]);
 }

 private function teachers() {
  return User::where('role', 'teacher')->orderBy('name')->orderBy('id')->get(['id', 'name', 'profile_number']);
 }

 public function storeAdvance(Request $request) {
  $data = $request->validate([
   'employee_id' => 'required|integer',
   'amount' => ['required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
   'spent_on' => 'required|date_format:Y-m-d|before_or_equal:today',
   'note' => 'nullable|string|max:1000',
   'request_key' => 'required|uuid',
  ]) + ['currency' => 'USD'];
  $this->record($request, $data);
  return redirect()->route('reports.expenses.index', [
   'currency' => 'USD', 'month' => substr($data['spent_on'], 0, 7),
  ])->with('success', 'Employee advance recorded.');
 }

 private function record(Request $request, array $data): void {
  app(SchoolFinanceService::class)->locked(fn () => $this->recordExpense($request, $data));
 }

 public function advanceRequestHistory(Request $request) {
  $filters = $request->validate([
   'date' => 'nullable|date_format:Y-m-d',
   'month' => 'nullable|date_format:Y-m',
   'year' => 'nullable|date_format:Y',
  ]);
  $history = TeacherAdvanceRequest::with('expense:id,spent_on')
   ->where('teacher_id', $request->user()->id)
   ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', $date))
   ->when($filters['month'] ?? null, function ($query, $month) {
    $start = \Carbon\Carbon::createFromFormat('!Y-m', $month);
    return $query->where('created_at', '>=', $start)
     ->where('created_at', '<', $start->copy()->addMonth());
   })
   ->when($filters['year'] ?? null, fn ($query, $year) => $query->whereYear('created_at', $year))
   ->latest()->orderByDesc('id')->paginate(25)->withQueryString();

  return view('school.expenses.request-history', compact('history', 'filters'));
 }

 public function requestAdvance(Request $request) {
  $data = $request->validate([
   'request_type' => 'nullable|in:advance,early_salary',
   'requested_amount' => ['exclude_if:request_type,early_salary', 'required', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
   'submission_key' => 'required|uuid',
   'repayment_months' => 'exclude_if:request_type,early_salary|nullable|integer|min:1|max:'.EmployeeSalaryAdvances::PLAN_MAX_MONTHS,
  ], ['requested_amount.regex' => 'Enter a number with up to two decimal places.']);
  $early = ($data['request_type'] ?? 'advance') === 'early_salary';
  $amount = $early ? 0 : Money::cents($data['requested_amount']);
  if (!$early && $amount <= 0) {
   throw ValidationException::withMessages(['requested_amount' => 'Enter an amount greater than zero.']);
  }
  $months = (int) ($data['repayment_months'] ?? 1);
  if (!$early && $amount >= EmployeeSalaryAdvances::PLAN_MINIMUM_CENTS && !isset($data['repayment_months'])) {
   throw ValidationException::withMessages(['repayment_months' => __('Choose how many months to repay this advance.')]);
  }
  if (!$early && $amount < EmployeeSalaryAdvances::PLAN_MINIMUM_CENTS && $months !== 1) {
   throw ValidationException::withMessages(['repayment_months' => __('Installment plans start at $100.')]);
  }

  $months = $early ? 1 : $months;
  DB::transaction(function () use ($request, $data, $amount, $months, $early) {
   $employee = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
   $advance = TeacherAdvanceRequest::where('request_key', $data['submission_key'])->first();
   if ($advance) {
    abort_unless((int) $advance->teacher_id === $employee->id, 403);
    if ((!$early && $advance->amount_cents !== $amount) || $advance->repayment_months !== $months || ($advance->salary_month !== null) !== $early) {
     throw ValidationException::withMessages(['requested_amount' => 'This request has already been submitted. Reopen the form to make a new request.']);
    }
    return;
   }

   $month = $early ? today()->startOfMonth()->addMonth() : null;
   if ($month) {
    $amount = app(EmployeeSalaryAdvances::class)->availableEarlySalary($employee->id, $month);
    if ($amount === 0) {
     throw ValidationException::withMessages(['requested_amount' => __('No early salary is available for :month.', ['month' => $month->translatedFormat('F Y')])]);
    }
   }
   TeacherAdvanceRequest::create([
    'request_key' => $data['submission_key'], 'teacher_id' => $employee->id, 'amount_cents' => $amount,
    'repayment_months' => $months, 'salary_month' => $month?->toDateString(),
   ]);
  });

  return redirect()->route('dashboard')->with('success', $early ? __('Early salary request sent.') : 'Salary advance request sent for $'.Money::display($amount).'.');
 }

 private function validateEarlySalary(int $employeeId, Carbon $month, int $amount, ?int $approvingId = null): void {
  if ($month->lte(today()) || EmployeeSalaryPayment::where('teacher_id', $employeeId)->whereDate('salary_month', $month)->exists()) {
   throw ValidationException::withMessages(['requested_amount' => __('The scheduled payday has arrived. Use regular payroll for this month.')]);
  }
  if ($amount > app(EmployeeSalaryAdvances::class)->availableEarlySalary($employeeId, $month, $approvingId)) {
   throw ValidationException::withMessages(['requested_amount' => __('The request exceeds the available scheduled salary for :month.', ['month' => $month->translatedFormat('F Y')])]);
  }
 }

 public function cancelAdvanceRequest(Request $request, TeacherAdvanceRequest $advanceRequest) {
  abort_unless((int) $advanceRequest->teacher_id === $request->user()->id, 404);
  app(SchoolFinanceService::class)->locked(function () use ($advanceRequest) {
   $advance = TeacherAdvanceRequest::findOrFail($advanceRequest->id);
   if ($advance->school_expense_id) {
    throw ValidationException::withMessages(['advance_request' => __('This request has already been paid.')]);
   }
   if ($advance->rejected_at) {
    throw ValidationException::withMessages(['advance_request' => __('This request has already been rejected.')]);
   }
   if (!$advance->cancelled_at) {
    $advance->cancelled_at = now();
    $advance->save();
   }
  });

  return redirect()->back(302, [], route('dashboard.advance-requests.history'))
   ->with('success', __('Salary advance request cancelled.'));
 }

 public function rejectAdvanceRequest(Request $request, TeacherAdvanceRequest $advanceRequest) {
  $finance = app(SchoolFinanceService::class);
  $finance->locked(function () use ($request, $advanceRequest, $finance) {
   $advance = TeacherAdvanceRequest::findOrFail($advanceRequest->id);
   if ($advance->school_expense_id || $advance->cancelled_at) {
    throw ValidationException::withMessages(['advance_request' => __('Only pending requests can be rejected.')]);
   }
   if (!$advance->rejected_at) {
    $advance->rejected_at = now();
    $advance->save();
    $finance->audit($request->user(), 'reject_salary_advance_request', 'teacher_advance_request', $advance->id, [
     'teacher_id' => $advance->teacher_id, 'amount_cents' => $advance->amount_cents,
    ]);
   }
  });

  return redirect()->back(302, [], route('reports.expenses.index'))
   ->with('success', __('Salary advance request rejected.'));
 }

 public function approveEarlySalary(Request $request, TeacherAdvanceRequest $advanceRequest) {
  $finance = app(SchoolFinanceService::class);
  $finance->locked(function () use ($request, $advanceRequest, $finance) {
   $advance = TeacherAdvanceRequest::findOrFail($advanceRequest->id);
   abort_unless($advance->salary_month, 404);
   if ($advance->cancelled_at || $advance->rejected_at) {
    throw ValidationException::withMessages(['advance_request' => __('Only pending requests can be approved.')]);
   }
   if ($advance->approved_at) {
    return;
   }
   User::whereKey($advance->teacher_id)->lockForUpdate()->firstOrFail();
   $this->validateEarlySalary($advance->teacher_id, $advance->salary_month, $advance->amount_cents, $advance->id);
   $advance->approved_at = now();
   $advance->save();
   $finance->audit($request->user(), 'approve_early_salary', 'teacher_advance_request', $advance->id, [
    'teacher_id' => $advance->teacher_id, 'amount_cents' => $advance->amount_cents,
    'salary_month' => $advance->salary_month->toDateString(),
   ]);
  });

  return redirect()->back(302, [], route('reports.expenses.index'))->with('success', __('Early salary approved. Record the payout when paid.'));
 }

 public function confirmAdvanceRequest(Request $request, TeacherAdvanceRequest $advanceRequest) {
  $expense = app(SchoolFinanceService::class)->locked(function () use ($request, $advanceRequest) {
   $advance = TeacherAdvanceRequest::findOrFail($advanceRequest->id);
   if ($advance->cancelled_at) {
    throw ValidationException::withMessages(['advance_request' => __('The teacher cancelled this request.')]);
   }
   if ($advance->rejected_at) {
    throw ValidationException::withMessages(['advance_request' => __('This request has already been rejected.')]);
   }
   if ($advance->school_expense_id) {
    return SchoolExpense::findOrFail($advance->school_expense_id);
   }
   if ($advance->salary_month) {
    if (!$advance->approved_at) {
     throw ValidationException::withMessages(['advance_request' => __('Approve the early salary request before recording a payout.')]);
    }
    User::whereKey($advance->teacher_id)->lockForUpdate()->firstOrFail();
    $this->validateEarlySalary($advance->teacher_id, $advance->salary_month, $advance->amount_cents, $advance->id);
   }

   $expense = $this->recordExpense($request, [
    'employee_id' => $advance->teacher_id,
    'amount' => Money::decimal($advance->amount_cents),
    'currency' => 'USD',
    'spent_on' => today()->toDateString(),
    'note' => $advance->salary_month ? 'Early salary for '.$advance->salary_month->format('F Y') : 'Teacher salary advance request',
    'recover_from_month' => $advance->salary_month?->toDateString(),
    'repayment_months' => $advance->repayment_months,
    'request_key' => (string) \Illuminate\Support\Str::uuid(),
   ]);
   $advance->update(['school_expense_id' => $expense->id]);

   return $expense;
  });

  return redirect()->route('reports.expenses.index', [
   'currency' => 'USD', 'month' => $expense->spent_on->format('Y-m'),
  ])->with('success', 'Teacher advance confirmed and recorded.');
 }

 private function recordExpense(Request $request, array $data): SchoolExpense {
  $amount=Money::cents($data['amount']);
  if($amount<=0) throw ValidationException::withMessages(['amount'=>'Enter an amount greater than zero.']);
   $existing=SchoolExpense::where('request_key',$data['request_key'])->first();
   if($existing) {
    if($existing->recorded_by!==$request->user()->id || Money::cents($existing->amount)!==$amount || $existing->currency!==$data['currency'] || $existing->spent_on->toDateString()!==$data['spent_on'] || ($existing->note ?? '')!==($data['note'] ?? '') || (int) $existing->employee_id !== (int) ($data['employee_id'] ?? 0)) throw ValidationException::withMessages(['amount'=>'This request was already used. Refresh the page.']);
    return $existing;
   }
   if (isset($data['employee_id']) && !User::whereKey($data['employee_id'])->where('role', 'teacher')->lockForUpdate()->first()) {
    throw ValidationException::withMessages(['employee_id' => 'Choose an available teacher.']);
   }
   $total=app(SchoolCashReport::class)->month($data['spent_on'])['totals'][$data['currency']]['remaining'] ?? 0;
   if($amount>$total) throw ValidationException::withMessages(['amount'=>'Expense exceeds recorded receipts remaining for this month and currency.']);
   $expense=SchoolExpense::create($data+['recorded_by'=>$request->user()->id]);
   app(SchoolFinanceService::class)->audit($request->user(), isset($data['employee_id']) ? 'record_salary_advance' : 'record_expense', 'school_expense', $expense->id, $expense->getAttributes());
   return $expense;
 }
}
