<?php

namespace Tests\Feature;

use App\Models\Fee;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParentPaymentDrawerTest extends TestCase
{
    use RefreshDatabase;

    private function family(): array
    {
        $this->travelTo(now()->setDate(2026, 11, 7));
        $admin = User::factory()->create(['role' => 'administrator']);
        $parent = User::factory()->create(['role' => 'parent']);
        $child = Student::factory()->create(['parent_id' => $parent->id]);
        $fee = Fee::create(['student_id' => $child->id, 'description' => 'Tuition', 'amount' => '100.00', 'paid' => '20.00', 'opening_paid' => '20.00', 'paid_at' => '2026-10-15', 'due_date' => '2026-11-10', 'program' => 'Khmer', 'academic_year' => '2026-2027']);
        $service = app(SchoolFinanceService::class);
        $payment = $service->payment($fee, ['amount' => '10.00', 'paid_on' => '2026-11-07', 'request_key' => (string) Str::uuid(), 'method' => 'cash'], $admin);
        $service->adjust($fee, ['kind' => 'refund', 'amount' => '5.00', 'reason' => 'Returned payment', 'request_key' => (string) Str::uuid(), 'fee_payment_id' => $payment->id], $admin);

        return compact('parent', 'admin', 'child', 'fee');
    }

    public function test_drawer_summary_history_and_preview_preserve_exact_financial_data(): void
    {
        $f = $this->family();
        $before = collect(['fees', 'fee_payments', 'financial_adjustments', 'invoice_items', 'finance_audits'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);
        $response = $this->actingAs($f['admin'])->get(route('finance.parents.details', $f['parent']))->assertOk()
            ->assertSee('PARENT PAYMENT OVERVIEW')->assertSee('Payment History')->assertSee('Upcoming Payments')
            ->assertSee('Historical Opening Balance')->assertSee('Recorded Payment')->assertSee('Refund')->assertDontSee("@include('school.finance._parent-timeline'");
        $this->assertSame(2500, $response->viewData('summary')['USD']['paid']);
        $this->assertSame(7500, $response->viewData('summary')['USD']['balance']);
        $this->assertSame(3, $response->viewData('events')->total());
        $this->assertSame('payment', $response->viewData('latestPayment')['type']);
        $this->get(route('finance.invoice', ['fee' => $f['fee'], 'preview' => 1]))->assertOk()->assertSee('Invoice preview')->assertSee($f['parent']->name)->assertSee('75.00')->assertDontSee('Record payment &amp; receipt');
        foreach ($before as $table => $rows) {
            $this->assertSame($rows, DB::table($table)->orderBy('id')->get()->toJson(), $table);
        }
    }

    public function test_history_filters_pagination_and_authorization(): void
    {
        $f = $this->family();
        $this->actingAs($f['admin']);
        $url = route('finance.parents.details', $f['parent']);
        $this->get($url.'?type=opening&program=Khmer&academic_year=2026-2027&child='.$f['child']->id)->assertOk()->assertViewHas('events', fn ($events) => $events->total() === 1 && $events->first()['type'] === 'opening');
        $this->get($url.'?from=2026-11-01&to=2026-11-07&type=payment')->assertOk()->assertViewHas('events', fn ($events) => $events->total() === 1);
        $this->get($url.'?to=2026-11-07')->assertOk();
        $this->get($url.'?search=does-not-exist')->assertOk()->assertSee('No activity matches your filters.');
        $this->get($url.'?program=Japan')->assertOk()->assertViewHas('events', fn ($events) => $events->isEmpty());
        $otherChild = Student::factory()->create();
        $this->get($url.'?child='.$otherChild->id)->assertNotFound();
        $service = app(SchoolFinanceService::class);
        foreach (range(1, 16) as $i) {
            $service->payment($f['fee'], ['amount' => '1.00', 'paid_on' => '2026-11-07', 'method' => 'cash', 'request_key' => (string) Str::uuid()], $f['admin']);
        }
        $this->get($url.'?type=payment&page=2')->assertOk()->assertViewHas('events', fn ($events) => $events->count() === 2 && $events->total() === 17);
        foreach (['parent', 'student', 'teacher'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertForbidden();
        }
    }

    public function test_empty_states_and_json_reminders_do_not_create_invoices(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $parent = User::factory()->create(['role' => 'parent']);
        $this->actingAs($admin)->get(route('finance.parents.details', $parent))->assertOk()->assertSee('No Invoices')->assertSee('No invoices issued yet.')->assertSee('No payment reminder scheduled.');
        $this->postJson(route('finance.parents.next-payment', $parent), ['next_payment_on' => today()->addMonth()->format('Y-m-d')])->assertOk()->assertJsonPath('message', 'Payment reminder saved.');
        $this->postJson(route('finance.parents.next-payment', $parent), ['next_payment_on' => 'invalid'])->assertUnprocessable();
        $this->postJson(route('finance.parents.next-payment', $parent), ['next_payment_on' => ''])->assertOk()->assertJsonPath('message', 'Payment reminder cleared.');
        $this->assertSame(0, Fee::count());
    }

    public function test_each_program_can_be_filtered_without_inferencing_legacy_programs(): void
    {
        $f = $this->family();
        $this->actingAs($f['admin']);
        foreach (['English', 'Chinese', 'Japan'] as $program) {
            Fee::create(['student_id' => $f['child']->id, 'description' => $program.' tuition', 'amount' => '5.00', 'paid' => '5.00', 'opening_paid' => '5.00', 'paid_at' => '2026-10-01', 'due_date' => '2026-11-01', 'program' => $program, 'academic_year' => '2026-2027']);
        }
        foreach (['Khmer', 'English', 'Chinese', 'Japan'] as $program) {
            $this->get(route('finance.parents.details', ['parent' => $f['parent'], 'program' => $program]))
                ->assertOk()->assertViewHas('events', fn ($events) => $events->total() > 0 && collect($events->items())->every(fn ($entry) => $entry['program'] === $program));
        }
        $this->get(route('finance.parents.details', ['parent' => $f['parent'], 'program' => 'legacy']))
            ->assertOk()->assertViewHas('events', fn ($events) => $events->isEmpty());
    }
}
