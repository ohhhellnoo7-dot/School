<?php

namespace Tests\Feature;

use App\Models\Fee;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParentPaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(User $parent, string $amount, string $paid, string $currency = 'USD'): Fee
    {
        $student = Student::factory()->create(['parent_id' => $parent->id]);

        return Fee::create(['student_id' => $student->id, 'description' => 'Tuition', 'amount' => $amount, 'paid' => $paid, 'opening_paid' => $paid, 'currency' => $currency, 'due_date' => today()->subDay()]);
    }

    public function test_family_status_accounts_for_every_child_and_separates_unbilled_and_waived_families(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $paid = User::factory()->create(['role' => 'parent', 'name' => 'Paid Family']);
        $partial = User::factory()->create(['role' => 'parent', 'name' => 'Partial Family']);
        $unpaid = User::factory()->create(['role' => 'parent', 'name' => 'Unpaid Family']);
        $waived = User::factory()->create(['role' => 'parent', 'name' => 'Waived Family']);
        $unbilled = User::factory()->create(['role' => 'parent', 'name' => 'Unbilled Family']);
        $this->invoice($paid, '100.00', '100.00');
        $this->invoice($partial, '100.00', '100.00');
        $debt = $this->invoice($partial, '50.00', '0.00');
        $this->invoice($unpaid, '75.00', '0.00');
        $waiver = $this->invoice($waived, '25.00', '0.00');
        app(SchoolFinanceService::class)->adjust($waiver, ['kind' => 'waiver', 'amount' => '25.00', 'request_key' => (string) Str::uuid(), 'reason' => 'Full scholarship'], $admin);
        Student::factory()->create(['parent_id' => $unbilled->id]);
        $response = $this->actingAs($admin)->get(route('finance.parents'))->assertOk();
        $families = collect($response->viewData('families')->items())->keyBy(fn ($row) => $row['parent']->id);
        foreach ([$paid->id => 'paid', $partial->id => 'partially_paid', $unpaid->id => 'unpaid', $waived->id => 'waived', $unbilled->id => 'no_invoices'] as $id => $status) {
            $this->assertSame($status, $families[$id]['status']);
        }
        $this->assertSame(5000, $families[$partial->id]['currencies']['USD']['balance']);
        $this->assertSame(5000, $families[$partial->id]['currencies']['USD']['overdue']);
        $this->assertSame($debt->due_date->format('Y-m-d'), $families[$partial->id]['currencies']['USD']['next_due']);
        $this->get(route('finance.parents', ['status' => 'unpaid']))->assertOk()->assertSee('Unpaid Family')->assertDontSee('Partial Family')->assertDontSee('Paid Family');
        $this->get(route('finance.parents', ['search' => $debt->student->student_number]))->assertOk()->assertSee('Partial Family')->assertDontSee('Unpaid Family');
        $this->get(route('finance.index'))->assertOk()->assertSee('Parent payment status');
    }

    public function test_currency_overpayments_do_not_hide_another_currency_debt_and_access_is_admin_only(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $this->invoice($parent, '10.00', '20.00');
        $this->invoice($parent, '1000.00', '0.00', 'KHR');
        $this->get(route('finance.parents'))->assertRedirect('/login');
        foreach (['parent', 'student', 'teacher'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('finance.parents'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'administrator']))->get(route('finance.parents'))->assertOk()
            ->assertViewHas('families', function ($rows) use ($parent) {
                $family = collect($rows->items())->first(fn ($row) => $row['parent']->id === $parent->id);
                return $family['status'] === 'partially_paid' && $family['currencies']['USD']['balance'] === -1000 && $family['currencies']['KHR']['balance'] === 100000;
            });
    }
}
