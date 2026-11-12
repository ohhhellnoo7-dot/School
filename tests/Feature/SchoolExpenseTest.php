<?php
namespace Tests\Feature;
use App\Models\{Fee, FeePayment, FinancialAdjustment, SchoolExpense, Student, User};
use App\Services\SchoolCashReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class SchoolExpenseTest extends TestCase {
 use RefreshDatabase;
 private function funds(): array {
  $admin=User::factory()->create(['role'=>'administrator']);
  $student=Student::factory()->create();
  $fee=Fee::create(['student_id'=>$student->id,'description'=>'Tuition','amount'=>'100.00','paid'=>'100.00','currency'=>'USD','due_date'=>today(),'paid_at'=>now()]);
  $payment=FeePayment::create(['fee_id'=>$fee->id,'receipt_number'=>'TEST-1','request_key'=>(string)Str::uuid(),'amount'=>'100.00','currency'=>'USD','paid_on'=>today(),'method'=>'cash','recorded_by'=>$admin->id]);
  return [$admin,$fee,$payment];
 }
 public function test_expenses_allow_optional_notes_and_update_daily_report_without_changing_fees(): void {
  [$admin,$fee]=$this->funds();
  $data=['amount'=>'10.00','currency'=>'USD','spent_on'=>today()->toDateString(),'request_key'=>(string)Str::uuid()];
  $this->actingAs($admin)->post(route('reports.expenses.store'),$data)->assertRedirect();
  $this->post(route('reports.expenses.store'),$data)->assertRedirect();
  $this->assertDatabaseCount('school_expenses',1);
  $this->post(route('reports.expenses.store'),array_merge($data,['request_key'=>(string)Str::uuid(),'note'=>'New student chair']))->assertRedirect();
  $report=app(SchoolCashReport::class)->month(today()->toDateString());
  $this->assertSame(8000,$report['totals']['USD']['remaining']);
  $this->assertSame(2000,$report['days'][today()->toDateString()]['USD']['expenses']);
  $this->assertSame('100.00',$fee->fresh()->paid);
  $this->assertDatabaseHas('school_expenses',['note'=>'New student chair']);
  $this->get(route('reports'))->assertOk()->assertDontSee('Daily money report')
   ->assertDontSee('Net receipts before expenses')
   ->assertSeeInOrder(['Money taken out', '$20.00', 'Money remaining', '$80.00']);
  $this->assertDatabaseCount('finance_audits',2);
 }
 public function test_expense_creation_page_is_available_only_to_administrators(): void {
  [$admin]=$this->funds();
  $this->actingAs($admin)->get(route('reports.expenses.create',['currency'=>'KHR']))
   ->assertOk()->assertSee('Add expense &amp; reason',false)->assertSee('Reason / note')
   ->assertSee('Money available')->assertSee('name="request_key"',false);
  $this->get(route('reports'))->assertOk()->assertSee(route('reports.expenses.create',['currency'=>'USD']),false);
  $this->actingAs(User::factory()->create(['role'=>'teacher']))->get(route('reports.expenses.create'))->assertForbidden();
 }
 public function test_expenses_enforce_balance_currency_date_amount_and_permissions(): void {
  [$admin]=$this->funds();
  $data=['amount'=>'101.00','currency'=>'USD','spent_on'=>today()->toDateString(),'request_key'=>(string)Str::uuid()];
  $this->actingAs($admin)->postJson(route('reports.expenses.store'),$data)->assertUnprocessable();
  foreach ([['amount'=>'0'],['amount'=>'1.001'],['amount'=>'-1'],['amount'=>'10','currency'=>'KHR'],['amount'=>'10','spent_on'=>today()->addDay()->toDateString()]] as $change) {
   $this->postJson(route('reports.expenses.store'),array_merge($data,$change))->assertUnprocessable();
  }
  foreach(['teacher','parent','student'] as $role) $this->actingAs(User::factory()->create(['role'=>$role]))->postJson(route('reports.expenses.store'),$data)->assertForbidden();
  $this->assertDatabaseCount('school_expenses',0);
 }
 public function test_expense_details_are_filtered_by_month_and_currency_and_protected(): void {
  [$admin]=$this->funds();
  foreach ([['USD',today(),'Classroom supplies'],['KHR',today(),'Other currency'],['USD',today()->subMonth()->startOfMonth(),'Previous month']] as [$currency,$day,$note]) {
   SchoolExpense::create(['amount'=>'10.00','currency'=>$currency,'spent_on'=>$day,'note'=>$note,'request_key'=>(string)Str::uuid(),'recorded_by'=>$admin->id]);
  }
  $this->actingAs($admin)->get(route('reports.expenses.index',['currency'=>'USD','month'=>today()->format('Y-m')]))
   ->assertOk()->assertSee('Classroom supplies')->assertSee($admin->name)->assertSee('90.00 USD')
   ->assertDontSee('Other currency')->assertDontSee('Previous month');
  $this->get(route('reports.expenses.index',['month'=>'invalid']))->assertRedirect()->assertSessionHasErrors('month');
  $this->actingAs(User::factory()->create(['role'=>'teacher']))->get(route('reports.expenses.index'))->assertForbidden();
 }
 public function test_refunds_reduce_remaining_and_previous_month_receipts_are_not_spendable_this_month(): void {
  [$admin,$fee,$payment]=$this->funds();
  FinancialAdjustment::create(['fee_id'=>$fee->id,'fee_payment_id'=>$payment->id,'request_key'=>(string)Str::uuid(),'kind'=>'refund','amount'=>'20.00','reason'=>'Refund','recorded_by'=>$admin->id]);
  $this->assertSame(8000,app(SchoolCashReport::class)->month(today()->toDateString())['totals']['USD']['remaining']);
  $data=['amount'=>'81.00','currency'=>'USD','spent_on'=>today()->toDateString(),'request_key'=>(string)Str::uuid()];
  $this->actingAs($admin)->postJson(route('reports.expenses.store'),$data)->assertUnprocessable();
  $payment->update(['paid_on'=>today()->subMonth()->startOfMonth()]);
  $this->postJson(route('reports.expenses.store'),array_merge($data,['amount'=>'1.00']))->assertUnprocessable();
 }
}
