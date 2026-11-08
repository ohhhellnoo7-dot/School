<?php

namespace Tests\Feature;

use App\Models\ClassGroup;
use App\Models\Fee;
use App\Models\FeeStructure;
use App\Models\Program;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolFinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SessionTuitionTest extends TestCase
{
    use RefreshDatabase;

    private function pricing(): array
    {
        $this->travelTo(now()->setDate(2026, 11, 7));
        $admin = User::factory()->create(['role' => 'administrator']);
        $service = app(SchoolFinanceService::class);
        $structures = [];
        foreach ([1, 2, 3] as $grade) {
            $structure = FeeStructure::create(['program' => 'English', 'grade' => $grade, 'academic_year' => '2026-2027', 'currency' => 'USD', 'monthly_tuition' => '20.00', 'billable_months' => 12, 'semester_months' => 6, 'starts_on' => '2026-11-01', 'effective_from' => '2026-11-01', 'due_day' => 10, 'discount' => '0.00', 'charges' => [], 'status' => 'draft']);
            $service->approve($structure, $admin);
            $structures[$grade] = $structure;
        }
        $group = ClassGroup::create(['name' => 'Grade 1', 'grade' => 1, 'program' => 'English', 'status' => 'active']);
        $morning = SchoolClass::create(['class_group_id' => $group->id, 'name' => 'Grade 1', 'program' => 'English', 'session' => 'Morning', 'section' => '']);
        $evening = SchoolClass::create(['class_group_id' => $group->id, 'name' => 'Grade 1', 'program' => 'English', 'session' => 'Evening', 'section' => '']);
        $student = Student::factory()->create(['school_class_id' => $morning->id]);
        $payload = ['program' => 'English', 'grades' => [1, 2, 3], 'academic_year' => '2026-2027', 'currency' => 'USD', 'billable_months' => 12, 'starts_on' => '2026-11-01', 'due_day' => 10, 'same_day' => 1, 'package_months' => 3, 'versions' => array_map(fn ($s) => $s->id, $structures), 'prices' => [
            'Morning' => ['monthly' => '25.00', 'quarterly' => '60.00'], 'Afternoon' => ['monthly' => '99.00', 'quarterly' => ''], 'Evening' => ['monthly' => '15.00', 'quarterly' => '36.00'],
        ]];

        return compact('admin', 'service', 'structures', 'group', 'morning', 'evening', 'student', 'payload');
    }

    public function test_bulk_grade_editor_activates_session_prices_and_preserves_existing_invoices(): void
    {
        $f = $this->pricing();
        $old = $f['service']->issue($f['student'], $f['morning'], $f['structures'][1], 'monthly', 1, 1, $f['admin'])[0];
        $this->actingAs($f['admin'])->get(route('finance.tuition.edit', ['structure' => $f['structures'][1]->id, 'grades' => [1, 2, 3]]))->assertOk()->assertSee('Session prices');
        $saved = $this->post(route('finance.tuition.save'), $f['payload'])->assertSessionHasNoErrors();
        $currentVersion = FeeStructure::where('program', 'English')->where('grade', 1)->where('status', 'active')->sole();
        $saved->assertRedirect(route('finance.tuition.edit', ['program' => 'English', 'structure' => $currentVersion->id, 'grades' => [1, 2, 3]]));
        $this->get($saved->headers->get('Location'))->assertOk()
            ->assertViewHas('source', fn ($source) => $source->id === $currentVersion->id)
            ->assertViewHas('selected', ['1', '2', '3'])->assertSee('Tuition updated for 3 grades/levels.')
            ->assertViewHas('savedRates', fn ($rates) => $rates->pluck('grade')->all() === [1, 2, 3])
            ->assertSee('Saved grade prices')->assertSee('Edit grade 3');
        $this->get(route('finance.tuition.edit', ['program' => 'Chinese']))->assertOk()
            ->assertViewHas('savedRates', fn ($rates) => $rates->isEmpty())
            ->assertSee('No grades have been edited for this program yet.');
        foreach ([1, 2, 3] as $grade) {
            $rate = FeeStructure::where('program', 'English')->where('grade', $grade)->where('status', 'active')->sole();
            $this->assertSame('25.00', $rate->monthlyForSession('Morning'));
            $this->assertSame('25.00', $rate->monthlyForSession('Afternoon'));
            $this->assertSame('15.00', $rate->monthlyForSession('Evening'));
            $this->assertSame('240.00', $rate->tuitionForMonths(12, 'Morning'));
            $this->assertSame('144.00', $rate->tuitionForMonths(12, 'Evening'));
        }
        $this->assertSame('20.00', $old->fresh()->amount);
        $this->assertSame('inactive', $f['structures'][1]->fresh()->status);
        $this->assertSame(1, Fee::count());
        $this->get(route('finance.index'))->assertOk()->assertSee('Edit tuition')->assertSee('Morning / Afternoon')->assertSee('72.00');
        $this->post(route('finance.tuition.save'), $f['payload'])->assertSessionHasErrors('finance');
    }

    public function test_packages_bill_exactly_once_and_evening_uses_its_own_rate(): void
    {
        $f = $this->pricing();
        $this->actingAs($f['admin'])->post(route('finance.tuition.save'), $f['payload'])->assertSessionHasNoErrors();
        $rate = FeeStructure::where('grade', 1)->where('status', 'active')->sole();
        $fee = $f['service']->issue($f['student'], $f['morning'], $rate, 'quarterly', 1, 1, $f['admin'])[0];
        $this->assertSame('60.00', $fee->amount);
        $this->assertSame('Morning', $fee->session);
        $f['service']->issue($f['student'], $f['morning'], $rate, 'quarterly', 1, 1, $f['admin']);
        $annual = $f['service']->issue($f['student'], $f['morning'], $rate, 'annual', 1, 1, $f['admin'])[0];
        $this->assertSame('180.00', $annual->amount);
        $this->assertSame(12, DB::table('invoice_periods')->count());
        $student = Student::factory()->create(['school_class_id' => $f['evening']->id]);
        $evening = $f['service']->issue($student, $f['evening'], $rate, 'quarterly', 1, 1, $f['admin'])[0];
        $this->assertSame('36.00', $evening->amount);
        $this->assertSame('Evening', $evening->session);
        $this->assertSame('15.00', $f['service']->issue($student, $f['evening'], $rate, 'monthly', 4, 1, $f['admin'])[0]->amount);
    }

    public function test_package_rounding_blank_evening_package_and_validation(): void
    {
        $f = $this->pricing();
        $payload = $f['payload'];
        $payload['prices']['Morning']['quarterly'] = '60.01';
        $payload['prices']['Evening']['quarterly'] = '';
        $this->actingAs($f['admin'])->post(route('finance.tuition.save'), $payload)->assertSessionHasNoErrors();
        $rate = FeeStructure::where('grade', 1)->where('status', 'active')->sole();
        $this->assertNull($rate->quarterlyForSession('Evening'));
        $this->assertSame('45.00', $rate->tuitionForMonths(3, 'Evening'));
        $fee = $f['service']->issue($f['student'], $f['morning'], $rate, 'quarterly', 1, 1, $f['admin'])[0];
        $this->assertSame('60.01', $fee->amount);
        $this->assertSame(['20.01', '20.00', '20.00'], $fee->items->pluck('amount')->all());
        $this->actingAs(User::factory()->create(['role' => 'parent']))->post(route('finance.tuition.save'), $payload)->assertForbidden();
    }

    public function test_editor_renders_only_configured_sessions_and_saves_evening_only_prices(): void
    {
        $f = $this->pricing();
        $this->actingAs($f['admin']);
        $this->get(route('finance.tuition.edit', ['program' => 'English']))->assertOk()
            ->assertViewHas('sessions', ['Morning', 'Afternoon', 'Evening']);
        $this->get(route('finance.tuition.edit', ['program' => 'Khmer']))->assertOk()
            ->assertViewHas('sessions', ['Morning', 'Afternoon'])->assertDontSee('id="Evening-monthly"', false);
        $this->get(route('finance.tuition.edit', ['program' => 'Chinese']))->assertOk()
            ->assertViewHas('sessions', ['Evening'])->assertSee('id="Evening-annual"', false)
            ->assertDontSee('id="Morning-monthly"', false)->assertDontSee('name="same_day"', false);
        $payload = $f['payload'];
        $payload['program'] = 'Chinese';
        $payload['grades'] = [1];
        $payload['versions'] = [1 => 0];
        unset($payload['same_day']);
        $payload['prices'] = ['Evening' => ['monthly' => '25.00', 'quarterly' => '60.00']];
        $this->post(route('finance.tuition.save'), $payload)->assertSessionHasNoErrors();
        $rate = FeeStructure::where('program', 'Chinese')->where('status', 'active')->sole();
        $this->assertSame(['Evening'], array_keys($rate->session_prices));
        $this->assertSame('25.00', $rate->monthly_tuition);
        $this->assertSame('240.00', $rate->annual_tuition);
        Program::where('name', 'Chinese')->update(['sessions' => ['Morning', 'Afternoon']]);
        $this->get(route('finance.tuition.edit', ['program' => 'Chinese']))->assertOk()
            ->assertViewHas('sessions', ['Morning', 'Afternoon'])->assertDontSee('id="Evening-monthly"', false);
    }

    public function test_six_month_editor_prices_and_invoices_preserve_legacy_package_meaning(): void
    {
        $f = $this->pricing();
        $payload = $f['payload'];
        $payload['package_months'] = 6;
        $payload['prices']['Morning'] = ['monthly' => '10.00', 'quarterly' => '50.00'];
        $payload['prices']['Evening'] = ['monthly' => '8.00', 'quarterly' => '40.00'];
        $this->actingAs($f['admin'])->post(route('finance.tuition.save'), $payload)->assertSessionHasNoErrors();
        $rate = FeeStructure::where('program', 'English')->where('grade', 1)->where('status', 'active')->sole();
        $this->assertSame(6, $rate->packageMonths());
        $this->assertSame('50.00', $rate->tuitionForMonths(6, 'Morning'));
        $this->assertSame('100.00', $rate->tuitionForMonths(12, 'Morning'));
        $this->assertSame('90.00', $rate->tuitionForMonths(10, 'Morning'));
        $this->assertSame('240.00', $f['structures'][1]->fresh()->annual_tuition);
        $this->get(route('finance.tuition.edit', ['structure' => $rate->id]))->assertOk()->assertSee('6-month package')->assertSee('value="6"', false);
        $fee = $f['service']->issue($f['student'], $f['morning'], $rate, 'package', 1, 1, $f['admin'])[0];
        $this->assertSame('50.00', $fee->amount);
        $this->assertSame(6, $fee->items->count());
        $this->assertStringContainsString('6-month package', $fee->items->first()->description);
        $f['service']->issue($f['student'], $f['morning'], $rate, 'package', 1, 1, $f['admin']);
        $annual = $f['service']->issue($f['student'], $f['morning'], $rate, 'annual', 1, 1, $f['admin'])[0];
        $this->assertSame('50.00', $annual->amount);
        $this->assertSame(12, DB::table('invoice_periods')->count());
        $other = Student::factory()->create(['school_class_id' => $f['morning']->id]);
        $f['service']->issue($other, $f['morning'], $rate, 'monthly', 1, 1, $f['admin']);
        $this->post(route('finance.issue'), ['student_id' => $other->id, 'school_class_id' => $f['morning']->id, 'fee_structure_id' => $rate->id, 'plan' => 'package', 'start_month' => 1, 'count' => 6])->assertSessionHasErrors('finance');
    }
}
