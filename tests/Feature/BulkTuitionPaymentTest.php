<?php

namespace Tests\Feature;

use App\Models\BulkPaymentRun;
use App\Models\ClassGroup;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\BulkTuitionPaymentService;
use App\Services\SchoolFinanceService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BulkTuitionPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function family(string $date = '2026-11-07'): array
    {
        $this->travelTo(Carbon::parse($date.' 09:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $parent = User::factory()->create(['role' => 'parent']);
        $service = app(SchoolFinanceService::class);
        $rate = FeeStructure::create(['academic_year' => '2026-2027', 'program' => 'English', 'grade' => 1, 'currency' => 'USD', 'monthly_tuition' => '25.00', 'quarterly_tuition' => '120.00', 'package_months' => 6, 'session_prices' => ['Morning' => ['monthly' => '25.00', 'quarterly' => '120.00'], 'Afternoon' => ['monthly' => '25.00', 'quarterly' => '120.00'], 'Evening' => ['monthly' => '15.00', 'quarterly' => '72.00']], 'billable_months' => 12, 'semester_months' => 6, 'starts_on' => today()->startOfMonth(), 'effective_from' => today()->startOfMonth(), 'due_day' => 10, 'charges' => [], 'discount' => '0.00', 'status' => 'draft']);
        $service->approve($rate, $admin);
        $group = ClassGroup::create(['name' => 'Grade 1', 'program' => 'English', 'grade' => 1, 'status' => 'active']);
        $classes = [];
        foreach (['Morning', 'Afternoon', 'Evening'] as $session) {
            $classes[$session] = SchoolClass::create(['name' => 'Grade 1', 'program' => 'English', 'section' => '', 'session' => $session, 'class_group_id' => $group->id]);
        }
        $child = Student::factory()->create(['parent_id' => $parent->id, 'school_class_id' => $classes['Morning']->id, 'enrollment_date' => today()->startOfMonth()]);
        $child->studyClasses()->attach($classes['Afternoon']->id, ['class_group_id' => $group->id]);
        $second = Student::factory()->create(['parent_id' => $parent->id, 'school_class_id' => $classes['Evening']->id, 'enrollment_date' => today()->startOfMonth()]);
        $bulk = app(BulkTuitionPaymentService::class);

        return compact('admin', 'parent', 'rate', 'classes', 'child', 'second', 'service', 'bulk');
    }

    private function data(array $preview): array
    {
        return ['months' => $preview['months'], 'request_key' => (string) Str::uuid(), 'fingerprint' => $preview['fingerprint'], 'method' => 'other', 'confirmed' => 1];
    }

    public function test_parent_duration_counts_use_payment_year_and_identify_families_without_payments(): void
    {
        $f = $this->family();
        $unpaid = User::factory()->create(['role' => 'parent']);
        $this->actingAs($f['admin']);
        $this->post(route('finance.bulk.pay'), $this->data($f['bulk']->preview(6)))->assertSessionHasNoErrors();
        $this->get(route('finance.parents', ['year' => 2026]))->assertOk()
            ->assertViewHas('durationCounts', fn ($counts) => $counts[6] === 1 && $counts[1] === 0 && $counts[12] === 0 && $counts['not_paid'] === 1);
        $this->get(route('finance.parents', ['year' => 2026, 'duration' => 6]))->assertOk()
            ->assertViewHas('families', fn ($rows) => $rows->total() === 1 && $rows->first()['parent']->id === $f['parent']->id);
        $this->get(route('finance.parents', ['year' => 2026, 'duration' => 'not_paid']))->assertOk()
            ->assertViewHas('families', fn ($rows) => $rows->total() === 1 && $rows->first()['parent']->id === $unpaid->id);
        $this->get(route('finance.parents', ['year' => 2027]))->assertOk()
            ->assertViewHas('durationCounts', fn ($counts) => $counts[6] === 0 && $counts['not_paid'] === 2);
    }

    public function test_monthly_payment_settles_all_children_once_and_locks_until_the_same_next_month_date(): void
    {
        $f = $this->family();
        $this->actingAs($f['admin']);
        $preview = $f['bulk']->preview(1);
        $this->assertSame([], $preview['errors']);
        $this->assertSame(4000, $preview['totals']['USD']);
        $this->assertSame(2, count($preview['rows']));
        $this->get(route('finance.bulk.preview', ['months' => 1]))->assertOk()->assertSee('07 December 2026');
        $this->get(route('finance.parents'))->assertOk()->assertSee('Pay school fees for all families')->assertSee('40.00 USD')->assertSee('data-bulk-confirm', false);
        $quick = $this->getJson(route('finance.bulk.preview', ['months' => 1]))->assertOk()->assertJsonPath('locked', false)->assertJsonPath('totals.0', '40.00 USD');
        $this->assertSame(0, Fee::count());
        $data = $quick->json('payload') + ['method' => 'other', 'confirmed' => '1'];
        $response = $this->post(route('finance.bulk.pay'), $data)->assertSessionHasNoErrors()->assertRedirect(route('finance.parents'))->assertSessionHas('bulk_payment_run_id');
        $this->assertSame(2, Fee::count());
        $this->assertSame(2, FeePayment::count());
        $this->get(route('finance.parents'))->assertOk()->assertSee('Fully paid')->assertSee('40.00 USD')->assertSee('1 invoice')->assertSee('View saved invoices');
        $this->get(route('finance.index'))->assertOk()->assertSee(Fee::first()->invoice_number);
        $this->assertTrue(Fee::get()->every(fn ($fee) => $fee->balanceCents() === 0));
        $this->post(route('finance.bulk.pay'), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, BulkPaymentRun::count());
        $this->assertSame(2, FeePayment::count());
        $this->post(route('finance.bulk.pay'), $this->data($f['bulk']->preview(6)))->assertSessionHasErrors('finance');
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('07 December 2026');
        $this->get(route('finance.bulk.show', BulkPaymentRun::first()))->assertOk();
        $this->travelTo(now()->setDate(2026, 12, 7));
        $this->assertFalse($f['bulk']->availability()['locked']);
        $this->post(route('finance.bulk.pay'), $this->data($f['bulk']->preview(1)))->assertSessionHasNoErrors();
        $this->assertSame('2027-01-07', BulkPaymentRun::latest('id')->first()->next_on->format('Y-m-d'));
        $this->assertSame(4, FeePayment::count());
    }

    public function test_six_and_twelve_month_options_use_packages_and_correct_unlock_dates(): void
    {
        $f = $this->family();
        $this->actingAs($f['admin']);
        $six = $f['bulk']->preview(6);
        $year = $f['bulk']->preview(12);
        $this->assertSame(19200, $six['totals']['USD']);
        $this->assertSame(38400, $year['totals']['USD']);
        $this->assertSame('2027-05-07', $six['cycle']['next_date']->format('Y-m-d'));
        $this->assertSame('2027-11-07', $year['cycle']['next_date']->format('Y-m-d'));
        $this->post(route('finance.bulk.pay'), $this->data($year))->assertSessionHasNoErrors();
        $this->assertSame(38400, Money::cents((string) FeePayment::sum('amount')));
        $this->assertTrue($f['bulk']->availability()['locked']);
    }

    public function test_month_end_clamps_february_without_drifting_the_march_payment_date(): void
    {
        $f = $this->family('2026-01-31');
        $this->actingAs($f['admin']);
        $this->post(route('finance.bulk.pay'), $this->data($f['bulk']->preview(1)))->assertSessionHasNoErrors();
        $this->assertSame('2026-02-28', BulkPaymentRun::first()->next_on->format('Y-m-d'));
        $this->travelTo(now()->setDate(2026, 2, 28));
        $this->post(route('finance.bulk.pay'), $this->data($f['bulk']->preview(1)))->assertSessionHasNoErrors();
        $this->assertSame('2026-03-31', BulkPaymentRun::latest('id')->first()->next_on->format('Y-m-d'));
    }

    public function test_stale_preview_missing_prices_and_unauthorized_access_do_not_record_payments(): void
    {
        $f = $this->family();
        $this->actingAs($f['admin']);
        $data = $this->data($f['bulk']->preview(1));
        $f['rate']->update(['monthly_tuition' => '30.00', 'session_prices' => null]);
        $this->post(route('finance.bulk.pay'), $data)->assertSessionHasErrors('finance');
        $f['rate']->update(['status' => 'inactive']);
        $preview = $f['bulk']->preview(1);
        $this->assertNotEmpty($preview['errors']);
        $this->post(route('finance.bulk.pay'), $this->data($preview))->assertSessionHasErrors('finance');
        $this->assertSame(0, Fee::count());
        $this->assertSame(0, BulkPaymentRun::count());
        foreach (['parent', 'teacher', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('finance.bulk.preview'))->assertForbidden();
            $this->post(route('finance.bulk.pay'), $data)->assertForbidden();
        }
    }

    public function test_multi_program_children_are_included_without_duplicate_session_charges(): void
    {
        $f = $this->family();
        $group = ClassGroup::create(['name' => 'Grade 1', 'program' => 'Chinese', 'grade' => 1, 'status' => 'active']);
        $class = SchoolClass::create(['name' => 'Grade 1', 'program' => 'Chinese', 'session' => 'Evening', 'section' => '', 'class_group_id' => $group->id]);
        $f['child']->studyClasses()->attach($class->id, ['class_group_id' => $group->id]);
        $rate = $f['rate']->replicate(['approved_by', 'approved_at']);
        $rate->fill(['program' => 'Chinese', 'monthly_tuition' => '10.00', 'quarterly_tuition' => null, 'session_prices' => null, 'status' => 'draft']);
        $rate->save();
        $f['service']->approve($rate, $f['admin']);
        $preview = $f['bulk']->preview(1);
        $this->assertSame(5000, $preview['totals']['USD']);
        $this->assertCount(3, $preview['rows']);
        $this->actingAs($f['admin'])->post(route('finance.bulk.pay'), $this->data($preview))->assertSessionHasNoErrors();
        $this->assertSame(3, FeePayment::count());
        $this->assertTrue(Fee::get()->every(fn ($fee) => $fee->balanceCents() === 0));
    }

    public function test_an_error_midway_rolls_back_the_entire_bulk_payment_and_date_lock(): void
    {
        $f = $this->family();
        $count = 0;
        $mock = \Mockery::mock(SchoolFinanceService::class)->makePartial();
        $mock->shouldReceive('payment')->andReturnUsing(function ($fee, $data, $actor) use (&$count, $f) {
            if (++$count === 2) {
                throw new \RuntimeException('Simulated write failure');
            }

            return $f['service']->payment($fee, $data, $actor);
        });
        $bulk = new BulkTuitionPaymentService($mock);
        try {
            $bulk->pay($this->data($bulk->preview(1)), $f['admin']);
            $this->fail('Expected payment failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated write failure', $error->getMessage());
        }
        $this->assertSame(0, BulkPaymentRun::count());
        $this->assertSame(0, FeePayment::count());
        $this->assertSame(0, Fee::count());
        $this->assertFalse($f['bulk']->availability()['locked']);
    }

    public function test_a_monthly_run_cannot_pay_a_whole_existing_annual_invoice(): void
    {
        $f = $this->family();
        $fee = $f['service']->issue($f['child'], $f['classes']['Morning'], $f['rate'], 'annual', 1, 1, $f['admin'])[0];
        $plan = $f['bulk']->preview(1);
        $this->assertNotEmpty($plan['errors']);
        $this->actingAs($f['admin'])->post(route('finance.bulk.pay'), $this->data($plan))->assertSessionHasErrors('finance');
        $this->assertSame('0.00', $fee->fresh()->paid);
        $this->assertSame(0, BulkPaymentRun::count());
    }
}

