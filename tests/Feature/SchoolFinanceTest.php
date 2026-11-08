<?php

namespace Tests\Feature;

use App\Models\ClassGroup;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolFinanceService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchoolFinanceTest extends TestCase
{
    use RefreshDatabase;

    private function setupEnrollment(string $program = 'Khmer', int $months = 12, string $currency = 'USD'): array
    {
        $actor = User::factory()->create(['role' => 'administrator']);
        $parent = User::factory()->create(['role' => 'parent']);
        $group = ClassGroup::create(['name' => 'Grade 8 '.$program, 'grade' => 8, 'section' => $program === 'Khmer' ? 'A' : null, 'program' => $program, 'capacity' => 30, 'status' => 'active']);
        $class = SchoolClass::create(['name' => 'Grade 8', 'program' => $program, 'section' => $group->section ?: '', 'session' => 'Morning', 'class_group_id' => $group->id]);
        $student = Student::factory()->create(['school_class_id' => $class->id, 'parent_id' => $parent->id]);
        $structure = FeeStructure::create(['academic_year' => '2026-2027', 'program' => $program, 'grade' => 8, 'currency' => $currency, 'monthly_tuition' => match ($program) {
            'English' => '40.00', 'Chinese' => '20.00', default => '25.00'
        }, 'billable_months' => $months, 'semester_months' => 5, 'starts_on' => '2026-01-01', 'effective_from' => '2026-01-01', 'due_day' => 10, 'charges' => ['books' => '0.00'], 'discount' => '0.00', 'status' => 'draft', 'created_by' => $actor->id]);
        $service = app(SchoolFinanceService::class);
        $service->approve($structure, $actor);

        return compact('actor', 'parent', 'group', 'class', 'student', 'structure', 'service');
    }

    public function test_four_programs_use_billable_months_and_keep_enrollments_separate(): void
    {
        $enrollments = [];
        foreach (['Khmer' => '250.00', 'English' => '400.00', 'Chinese' => '200.00', 'Japan' => '250.00'] as $program => $expected) {
            $e = $this->setupEnrollment($program, 10);
            $enrollments[$program] = $e;
            $fee = $e['service']->issue($e['student'], $e['class'], $e['structure'], 'annual', 1, 1, $e['actor'])[0];
            $this->assertSame($expected, $fee->amount);
            $this->assertSame($program, $fee->program);
            $this->assertSame('2026-01-10', $fee->due_date->format('Y-m-d'));
        }
        $this->assertSame(40, DB::table('invoice_periods')->count());
        $a = $enrollments['Khmer'];
        $b = $enrollments['English'];
        $multiStudent = Student::factory()->create(['school_class_id' => $a['class']->id]);
        $a['student'] = $multiStudent;
        $a['student']->studyClasses()->attach($b['class']->id, ['class_group_id' => $b['group']->id]);
        $a['service']->issue($a['student'], $a['class'], $a['structure'], 'monthly', 1, 1, $a['actor']);
        $a['service']->issue($a['student'], $b['class'], $b['structure'], 'monthly', 1, 1, $a['actor']);
        $this->assertSame(6500, $a['student']->fees->sum(fn ($f) => Money::cents($f->amount)));
    }

    public function test_monthly_then_annual_and_duplicate_requests_never_double_charge(): void
    {
        $e = $this->setupEnrollment();
        $s = $e['service'];
        $monthly = $s->issue($e['student'], $e['class'], $e['structure'], 'monthly', 1, 1, $e['actor'])[0];
        $s->issue($e['student'], $e['class'], $e['structure'], 'monthly', 1, 1, $e['actor']);
        $annual = $s->issue($e['student'], $e['class'], $e['structure'], 'annual', 1, 1, $e['actor'])[0];
        $s->issue($e['student'], $e['class'], $e['structure'], 'annual', 1, 1, $e['actor']);
        $this->assertSame('25.00', $monthly->amount);
        $this->assertSame('275.00', $annual->amount);
        $this->assertSame(2, Fee::count());
        $this->assertSame(12, DB::table('invoice_periods')->count());
        // Morning and afternoon sessions do not duplicate the same enrollment.
        $afternoon = SchoolClass::create(['class_group_id' => $e['group']->id, 'name' => 'Grade 8', 'program' => 'Khmer', 'section' => 'A', 'session' => 'Afternoon']);
        $e['student']->studyClasses()->attach($afternoon->id, ['class_group_id' => $e['group']->id]);
        $s->issue($e['student'], $afternoon, $e['structure'], 'annual', 1, 1, $e['actor']);
        $this->assertSame(2, Fee::count());
    }

    public function test_installments_semesters_discounts_and_extra_charges(): void
    {
        $e = $this->setupEnrollment();
        $e['structure']->update(['charges' => ['books' => '10.00'], 'discount' => '5.00', 'discount_reason' => 'Scholarship']);
        $fees = $e['service']->issue($e['student'], $e['class'], $e['structure'], 'installments', 1, 3, $e['actor']);
        $this->assertSame(['30.00', '20.00', '20.00'], array_map(fn ($f) => $f->amount, $fees));
        $this->assertSame(['2026-01-10', '2026-02-10', '2026-03-10'], array_map(fn ($f) => $f->due_date->format('Y-m-d'), $fees));
        $semester = $e['service']->issue($e['student'], $e['class'], $e['structure'], 'semester', 4, 1, $e['actor'])[0];
        $this->assertSame('100.00', $semester->amount);
    }

    public function test_payments_refunds_adjustments_and_receipts_are_idempotent_and_audited(): void
    {
        $e = $this->setupEnrollment();
        $fee = $e['service']->issue($e['student'], $e['class'], $e['structure'], 'monthly', 1, 1, $e['actor'])[0];
        $data = ['amount' => '10.00', 'request_key' => (string) Str::uuid(), 'paid_on' => today()->toDateString(), 'method' => 'cash'];
        $this->actingAs($e['actor']);
        $this->post(route('finance.pay', $fee), $data)->assertSessionHasNoErrors();
        $this->post(route('finance.pay', $fee), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, FeePayment::count());
        $this->assertSame('15.00', $fee->fresh()->balance);
        $this->assertNotSame('paid', $fee->fresh()->payment_status);
        $payment = FeePayment::first();
        $this->get(route('finance.receipt', $payment))->assertOk()->assertSee('10.00')->assertSee($payment->receipt_number);
        $adjust = ['amount' => '3.00', 'request_key' => (string) Str::uuid(), 'kind' => 'refund', 'reason' => 'Duplicate bank transfer correction', 'fee_payment_id' => $payment->id];
        $this->post(route('finance.adjust', $fee), $adjust)->assertSessionHasNoErrors();
        $this->post(route('finance.adjust', $fee), $adjust)->assertSessionHasNoErrors();
        $this->assertSame('18.00', $fee->fresh()->balance);
        $this->post(route('finance.adjust', $fee), ['amount' => '18.00', 'request_key' => (string) Str::uuid(), 'kind' => 'discount', 'reason' => 'Approved scholarship'])->assertSessionHasNoErrors();
        $this->assertSame('paid', $fee->fresh()->payment_status);
        $summary = $e['service']->summary(Fee::with('payments', 'adjustments')->get())['USD'];
        $this->assertSame(700, $summary['paid']);
        $this->assertSame(700, $summary['billed']);
        $this->assertSame(0, $summary['balance']);
        $this->assertSame(700, $summary['month']);
        $this->assertSame(1, DB::table('finance_audits')->where('action', 'payment')->count());
    }

    public function test_parent_authorization_currency_separation_and_pages(): void
    {
        $a = $this->setupEnrollment();
        $b = $this->setupEnrollment('English', 12, 'KHR');
        $fa = $a['service']->issue($a['student'], $a['class'], $a['structure'], 'monthly', 1, 1, $a['actor'])[0];
        $fb = $b['service']->issue($b['student'], $b['class'], $b['structure'], 'monthly', 1, 1, $b['actor'])[0];
        $payment = $b['service']->payment($fb, ['amount' => '5.00', 'request_key' => (string) Str::uuid(), 'paid_on' => today()->toDateString(), 'method' => 'cash'], $b['actor']);
        $this->actingAs($a['parent']);
        $this->get(route('finance.index'))->assertOk()->assertSee($fa->display_number)->assertDontSee($fb->display_number);
        $this->get(route('finance.invoice', $fb))->assertNotFound();
        $this->get(route('finance.receipt', $payment))->assertNotFound();
        $this->post(route('finance.pay', $fa), [])->assertForbidden();
        $this->get(route('finance.settings'))->assertForbidden();
        $this->actingAs($a['actor']);
        $this->get(route('finance.index'))->assertOk()->assertSee('USD overview')->assertSee('KHR overview');
        $this->get(route('finance.settings'))->assertOk();
        $this->get(route('finance.billing', ['student' => $a['student']->id]))->assertOk();
        $this->get(route('reports'))->assertOk();
        $this->get(route('finance.export'))->assertOk();
    }

    public function test_legacy_amounts_and_invoices_survive_configuration_changes_and_crud_is_blocked(): void
    {
        $e = $this->setupEnrollment();
        $legacy = Fee::create(['student_id' => $e['student']->id, 'description' => 'Historical tuition', 'amount' => '50.00', 'paid' => '30.00', 'opening_paid' => '30.00', 'legacy_reviewed' => true, 'due_date' => today()]);
        $fee = $e['service']->issue($e['student'], $e['class'], $e['structure'], 'monthly', 1, 1, $e['actor'])[0];
        $next = $e['structure']->replicate(['approved_by', 'approved_at']);
        $next->status = 'draft';
        $next->monthly_tuition = '35.00';
        $next->save();
        $e['service']->approve($next, $e['actor']);
        $this->assertSame('25.00', $fee->fresh()->amount);
        $this->assertSame('50.00', $legacy->fresh()->amount);
        $this->assertSame('30.00', $legacy->fresh()->paid);
        $this->actingAs($e['actor']);
        $this->put('/school/fees/'.$legacy->id, ['paid' => '50.00'])->assertSessionHasErrors('finance');
        $this->delete('/school/fees/'.$legacy->id)->assertSessionHasErrors('finance');
        $this->assertSame('30.00', $legacy->fresh()->paid);
    }

    public function test_overpayment_and_refund_of_another_invoice_are_rejected(): void
    {
        $e = $this->setupEnrollment();
        $fee = $e['service']->issue($e['student'], $e['class'], $e['structure'], 'monthly', 1, 1, $e['actor'])[0];
        $this->actingAs($e['actor']);
        $this->post(route('finance.pay', $fee), ['amount' => '25.01', 'request_key' => (string) Str::uuid(), 'paid_on' => today()->toDateString(), 'method' => 'cash'])->assertSessionHasErrors('finance');
        $this->assertSame('0.00', $fee->fresh()->paid);
        $this->post(route('finance.adjust', $fee), ['amount' => '1.00', 'request_key' => (string) Str::uuid(), 'kind' => 'refund', 'reason' => 'Unverified refund'])->assertSessionHasErrors('finance');
    }

    public function test_legacy_coverage_blocks_billing_until_reviewed_without_changing_original_money(): void
    {
        $e = $this->setupEnrollment();
        $legacy = Fee::create(['student_id' => $e['student']->id, 'description' => 'Previously billed tuition', 'amount' => '27.50', 'paid' => '20.00', 'opening_paid' => '20.00', 'due_date' => today()]);
        $this->actingAs($e['actor']);
        $request = ['student_id' => $e['student']->id, 'school_class_id' => $e['class']->id, 'fee_structure_id' => $e['structure']->id, 'plan' => 'annual', 'start_month' => 1, 'count' => 12];
        $this->post(route('finance.issue'), $request)->assertSessionHasErrors('finance');
        $this->get(route('finance.invoice', $legacy))->assertOk()->assertSee('Review historical billing coverage');
        $this->post(route('finance.reconcile', $legacy), ['coverage_type' => 'tuition', 'school_class_id' => $e['class']->id, 'fee_structure_id' => $e['structure']->id, 'start_month' => 1, 'end_month' => 1, 'reason' => 'Verified January ledger'])->assertSessionHasNoErrors();
        $this->post(route('finance.issue'), $request)->assertSessionHasNoErrors();
        $this->assertSame('275.00', Fee::whereNotNull('invoice_number')->sole()->amount);
        $this->assertSame('27.50', $legacy->fresh()->amount);
        $this->assertSame('20.00', $legacy->fresh()->paid);
        $this->assertSame(12, DB::table('invoice_periods')->count());
    }

    public function test_examples_are_drafts_and_approval_does_not_overwrite_existing_prices(): void
    {
        $e = $this->setupEnrollment();
        $this->actingAs($e['actor']);
        $this->post(route('finance.proposals'), ['academic_year' => '2026-2027', 'starts_on' => '2026-01-01', 'billable_months' => 10])->assertSessionHasNoErrors();
        $this->assertSame('active', $e['structure']->fresh()->status);
        $this->assertSame('25.00', $e['structure']->fresh()->monthly_tuition);
        $this->assertSame(1, FeeStructure::where('status', 'active')->count());
        $chinese = FeeStructure::where('program', 'Chinese')->where('grade', 8)->sole();
        $this->assertSame('200.00', $chinese->annual_tuition);
        $this->assertSame('draft', $chinese->status);
        $count = FeeStructure::count();
        $this->post(route('finance.proposals'), ['academic_year' => '2026-2027', 'starts_on' => '2026-01-01', 'billable_months' => 10])->assertSessionHasNoErrors();
        $this->assertSame($count, FeeStructure::count());
        $this->post(route('finance.approve', $chinese))->assertSessionHasNoErrors();
        $this->assertSame('active', $chinese->fresh()->status);
    }

    public function test_approved_calendar_cannot_reanchor_already_billed_months(): void
    {
        $e = $this->setupEnrollment();
        $e['service']->issue($e['student'], $e['class'], $e['structure'], 'monthly', 1, 1, $e['actor']);
        $next = $e['structure']->replicate(['approved_by', 'approved_at']);
        $next->status = 'draft';
        $next->starts_on = '2026-02-01';
        $next->save();
        $this->actingAs($e['actor'])->post(route('finance.approve', $next))->assertSessionHasErrors('finance');
        $this->assertSame('active', $e['structure']->fresh()->status);
    }

    public function test_fully_waived_and_refunded_statuses_and_wrong_request_key_payloads(): void
    {
        $e = $this->setupEnrollment();
        $fee = $e['service']->issue($e['student'], $e['class'], $e['structure'], 'monthly', 1, 1, $e['actor'])[0];
        $this->actingAs($e['actor']);
        $data = ['amount' => '25.00', 'request_key' => (string) Str::uuid(), 'paid_on' => today()->toDateString(), 'method' => 'cash'];
        $this->post(route('finance.pay', $fee), $data)->assertSessionHasNoErrors();
        $this->post(route('finance.pay', $fee), array_replace($data, ['amount' => '24.00']))->assertSessionHasErrors('finance');
        $this->assertSame('paid', $fee->fresh()->payment_status);
        $this->post(route('finance.adjust', $fee), ['amount' => '25.00', 'request_key' => (string) Str::uuid(), 'kind' => 'refund', 'reason' => 'Payment returned', 'fee_payment_id' => FeePayment::sole()->id])->assertSessionHasNoErrors();
        $this->assertSame('refunded', $fee->fresh()->payment_status);
        $this->post(route('finance.adjust', $fee), ['amount' => '25.00', 'request_key' => (string) Str::uuid(), 'kind' => 'waiver', 'reason' => 'Full scholarship'])->assertSessionHasNoErrors();
        $this->assertSame('waived', $fee->fresh()->payment_status);
    }

    public function test_admin_can_review_save_and_approve_a_revised_fee_without_repricing_invoices(): void
    {
        $e = $this->setupEnrollment();
        $fee = $e['service']->issue($e['student'], $e['class'], $e['structure'], 'monthly', 1, 1, $e['actor'])[0];
        $this->actingAs($e['actor']);
        $this->get(route('finance.settings', ['version' => $e['structure']->id]))->assertOk()->assertSee('Create revised version');
        $data = [
            'academic_year' => '2026-2027', 'program' => 'Khmer', 'grade' => 8, 'currency' => 'USD',
            'monthly_tuition' => '35.00', 'billable_months' => 12, 'semester_months' => 6, 'starts_on' => '2026-01-01',
            'effective_from' => '2026-01-01', 'due_day' => 15, 'discount' => '5.00', 'discount_reason' => 'Approved scholarship',
            'charges' => array_fill_keys(['registration', 'books', 'uniform', 'examination', 'other'], '0.00'), 'supersedes_id' => $e['structure']->id,
        ];
        $this->post(route('finance.settings.save'), $data)->assertSessionHasNoErrors();
        $revised = FeeStructure::latest('id')->first();
        $this->assertSame('draft', $revised->status);
        $this->assertSame('420.00', $revised->annual_tuition);
        $this->assertSame('active', $e['structure']->fresh()->status);
        $this->post(route('finance.approve', $revised))->assertSessionHasNoErrors();
        $this->assertSame('inactive', $e['structure']->fresh()->status);
        $this->assertSame('25.00', $fee->fresh()->amount);
        $this->postJson(route('finance.pay', $fee), ['amount' => 10.25, 'request_key' => (string) Str::uuid(), 'paid_on' => today()->toDateString(), 'method' => 'cash'])->assertRedirect();
        $this->assertSame('14.75', $fee->fresh()->balance);
    }

    public function test_old_fees_can_be_archived_without_deleting_receipts_or_current_program_invoices(): void
    {
        $e = $this->setupEnrollment();
        $current = $e['service']->issue($e['student'], $e['class'], $e['structure'], 'monthly', 1, 1, $e['actor'])[0];
        $legacy = Fee::create(['student_id' => $e['student']->id, 'description' => 'Term tuition', 'amount' => '1200.00', 'paid' => '0.00', 'opening_paid' => '0.00', 'due_date' => today()]);
        $payment = $e['service']->payment($legacy, ['amount' => '600.00', 'paid_on' => today()->format('Y-m-d'), 'method' => 'other', 'request_key' => (string) Str::uuid()], $e['actor']);
        $this->assertSame(1, $e['service']->archiveLegacyFees($e['actor']));
        $this->assertNull(Fee::find($legacy->id));
        $this->assertSame('1200.00', Fee::withTrashed()->find($legacy->id)->amount);
        $this->assertSame('600.00', Fee::withTrashed()->find($legacy->id)->paid);
        $this->assertNotNull(Fee::find($current->id));
        $this->assertNotNull($payment->fresh());
        $this->actingAs($e['actor'])->get(route('finance.archive'))->assertOk()->assertSee('LEGACY-'.$legacy->id);
        $this->get(route('finance.index'))->assertOk()->assertDontSee('LEGACY-'.$legacy->id);
        $this->get(route('finance.invoice', $legacy->id))->assertOk()->assertSee('Archived invoice')->assertDontSee('Record payment &amp; receipt');
        $this->get(route('finance.receipt', $payment))->assertOk();
        $this->assertSame(0, $e['service']->archiveLegacyFees($e['actor']));
        $this->actingAs($e['parent'])->get(route('finance.archive'))->assertForbidden();
        $this->get(route('finance.invoice', $legacy->id))->assertNotFound();
    }
}
