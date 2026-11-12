<?php
namespace App\Http\Controllers;
use App\Models\SchoolExpense;
use App\Services\{SchoolCashReport, SchoolFinanceService};
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class SchoolExpenseController extends Controller {
 public function index(Request $request) {
  abort_unless($request->user()->role === 'administrator',403);
  $filters=$request->validate(['currency'=>'nullable|in:USD,KHR','month'=>'nullable|date_format:Y-m']);
  $currency=$filters['currency'] ?? 'USD';
  $month=\Carbon\Carbon::createFromFormat('!Y-m',$filters['month'] ?? today()->format('Y-m'));
  $expenses=SchoolExpense::with('recorder')->where('currency',$currency)
   ->whereBetween('spent_on',[$month->toDateString(),$month->copy()->endOfMonth()->toDateString()])
   ->orderByDesc('spent_on')->orderByDesc('id')->paginate(25)->withQueryString();
  $cashReport=app(SchoolCashReport::class)->month($month->toDateString());
  $total=$cashReport['totals'][$currency] ?? ['receipts'=>0,'refunds'=>0,'expenses'=>0,'remaining'=>0];
  return view('school.expenses.index',compact('expenses','currency','month','total'));
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
  $amount=Money::cents($data['amount']);
  if($amount<=0) throw ValidationException::withMessages(['amount'=>'Enter an amount greater than zero.']);
  app(SchoolFinanceService::class)->locked(function() use($data,$amount,$request) {
   $existing=SchoolExpense::where('request_key',$data['request_key'])->first();
   if($existing) {
    if($existing->recorded_by!==$request->user()->id || Money::cents($existing->amount)!==$amount || $existing->currency!==$data['currency'] || $existing->spent_on->toDateString()!==$data['spent_on'] || ($existing->note ?? '')!==($data['note'] ?? '')) throw ValidationException::withMessages(['amount'=>'This request was already used. Refresh the page.']);
    return;
   }
   $total=app(SchoolCashReport::class)->month($data['spent_on'])['totals'][$data['currency']]['remaining'] ?? 0;
   if($amount>$total) throw ValidationException::withMessages(['amount'=>'Expense exceeds recorded receipts remaining for this month and currency.']);
   $expense=SchoolExpense::create($data+['recorded_by'=>$request->user()->id]);
   app(SchoolFinanceService::class)->audit($request->user(),'record_expense','school_expense',$expense->id,$expense->getAttributes());
  });
  return redirect()->route('reports',['date'=>$data['spent_on']])->with('success','Expense recorded. Daily and monthly reports updated.');
 }
}
