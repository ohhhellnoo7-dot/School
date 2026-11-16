<?php

namespace Tests\Feature;

use App\Models\{EmployeeSalaryPayment, EmployeeSalaryProfile, TeacherAttendance, TeacherSessionAttendance, User};
use App\Services\EmployeeSalaryCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSalaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_setup_uses_duties_and_hire_dates_preserves_existing_profiles_and_is_repeatable(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $withoutHireDate = $this->employee();
        $withoutHireDate->update(['hire_date' => null]);
        $configured = $this->employee();
        $this->profile($configured, $admin);
        foreach ([['07:00', '11:00'], ['13:00', '15:00'], ['15:00', '17:00'], ['17:30', '18:30'], ['18:30', '19:30'], ['17:30', '19:30']] as [$start, $end]) {
            \Illuminate\Support\Facades\DB::table('teacher_working_periods')->insert(['teacher_id' => $teacher->id, 'weekday' => 'Monday', 'start_time' => $start, 'end_time' => $end, 'unavailable' => false]);
        }
        \Illuminate\Support\Facades\DB::table('teacher_working_periods')->insert(['teacher_id' => $withoutHireDate->id, 'weekday' => 'Tuesday', 'start_time' => '17:30', 'end_time' => '18:30', 'unavailable' => false]);
        $service = app(\App\Services\EmployeeSalarySetup::class);
        $plan = $service->plan(Carbon::parse('2026-11-16'));
        $this->assertCount(2, $plan);
        $this->assertSame(2, $service->apply($plan, $admin));
        $this->assertDatabaseHas('employee_salary_profiles', ['teacher_id' => $teacher->id, 'day_sessions' => 2, 'evening_sessions' => 2, 'service_started_on' => '2026-01-01 00:00:00']);
        $this->assertDatabaseHas('employee_salary_profiles', ['teacher_id' => $withoutHireDate->id, 'day_sessions' => 0, 'evening_sessions' => 1, 'service_started_on' => '2026-11-16 00:00:00']);
        $this->assertSame(0, $service->apply($plan, $admin));
        $this->assertCount(0, $service->plan(Carbon::parse('2026-11-16')));
        $this->assertSame(3, EmployeeSalaryProfile::count());
        $this->assertSame(0, EmployeeSalaryPayment::count());
    }

    private function employee(): User
    {
        return User::factory()->create(['role' => 'teacher', 'status' => 'active', 'hire_date' => '2026-01-01']);
    }

    private function profile(User $teacher, User $admin): EmployeeSalaryProfile
    {
        return EmployeeSalaryProfile::create(['teacher_id' => $teacher->id, 'day_sessions' => 2, 'evening_sessions' => 2,
            'service_started_on' => '2026-01-01', 'updated_by' => $admin->id]);
    }

    public function test_salary_rates_match_every_session_combination(): void
    {
        $calculator = new EmployeeSalaryCalculator;
        foreach ([[1, 0, 12500], [2, 0, 25000], [0, 1, 3125], [0, 2, 6250], [2, 2, 31250], [1, 1, 15625]] as [$day, $evening, $expected]) {
            $profile = new EmployeeSalaryProfile(['day_sessions' => $day, 'evening_sessions' => $evening, 'service_started_on' => '2026-01-01']);
            $this->assertSame($expected, $calculator->calculate($profile, Carbon::parse('2026-01-01'))['total_cents']);
        }
    }

    public function test_increase_is_five_dollars_per_completed_three_months_for_total_salary(): void
    {
        $calculator = new EmployeeSalaryCalculator;
        $profile = new EmployeeSalaryProfile(['day_sessions' => 2, 'evening_sessions' => 2, 'service_started_on' => '2026-01-15']);
        foreach (['2026-04-14' => 31250, '2026-04-15' => 31750, '2026-07-14' => 31750, '2026-07-15' => 32250, '2027-01-15' => 33250] as $date => $expected) {
            $this->assertSame($expected, $calculator->calculate($profile, Carbon::parse($date))['total_cents']);
        }
        $this->assertSame(0, $calculator->calculate($profile, Carbon::parse('2026-01-14'))['total_cents']);
        $profile->service_started_on = '2026-01-31';
        $this->assertSame(31750, $calculator->calculate($profile, Carbon::parse('2026-04-30'))['total_cents']);
    }

    public function test_payroll_pages_show_salary_settings_attendance_and_scan_details(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $this->profile($teacher, $admin);
        TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => '2026-11-13', 'status' => 'present', 'recorded_by' => $admin->id]);
        TeacherSessionAttendance::create(['teacher_id' => $teacher->id, 'date' => '2026-11-13', 'session' => 'Morning',
            'status' => 'present', 'scanned_at' => '2026-11-13 07:00:00']);

        $this->actingAs($admin)->get(route('employee-salaries.index', ['month' => '2026-11']))
            ->assertOk()->assertSee($teacher->name)->assertSee('$327.50')->assertSee('1 present');
        $this->get(route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-11']))
            ->assertOk()->assertSee('Work sessions')->assertSee('13 Nov 2026')->assertSee('Saturday')->assertSee('Sunday')
            ->assertSee('Morning')->assertSee('07:00')->assertSee('Record daily attendance');
    }

    public function test_salary_setup_validates_sessions_and_does_not_allow_student_profiles(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $this->actingAs($admin)->put(route('employee-salaries.update', $teacher), ['day_sessions' => 2, 'evening_sessions' => 2, 'service_started_on' => '2026-01-01'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('employee_salary_profiles', ['teacher_id' => $teacher->id, 'day_sessions' => 2, 'evening_sessions' => 2]);
        $this->put(route('employee-salaries.update', $teacher), ['day_sessions' => 0, 'evening_sessions' => 0, 'service_started_on' => '2026-01-01'])
            ->assertSessionHasErrors('day_sessions');
        $this->put(route('employee-salaries.update', $teacher), ['day_sessions' => 3, 'evening_sessions' => 1, 'service_started_on' => '2026-01-01'])
            ->assertSessionHasErrors('day_sessions');
        $student = User::factory()->create(['role' => 'student']);
        $this->get(route('employee-salaries.show', $student))->assertNotFound();
    }

    public function test_current_month_includes_midmonth_hires_and_completed_service_anniversaries(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $profile = $this->profile($teacher, $admin);
        $profile->update(['service_started_on' => '2026-11-11', 'day_sessions' => 1, 'evening_sessions' => 0]);
        $this->actingAs($admin)->get(route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-11']))
            ->assertOk()->assertSee('$125.00')->assertSee('Record salary payment');
        $profile->update(['service_started_on' => '2026-08-15']);
        $this->get(route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-11']))
            ->assertOk()->assertSee('$130.00');
    }

    public function test_recorded_salary_is_not_duplicated_and_remains_unchanged_after_profile_edit(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $profile = $this->profile($teacher, $admin);
        $data = ['month' => '2026-11', 'paid_on' => '2026-11-16', 'confirm' => 1, 'expected_total_cents' => 32750, 'reference' => 'Bank transfer 123'];
        $this->actingAs($admin)->post(route('employee-salaries.pay', $teacher), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_salary_payments', ['teacher_id' => $teacher->id, 'base_cents' => 31250, 'raise_cents' => 1500, 'total_cents' => 32750]);
        $this->post(route('employee-salaries.pay', $teacher), $data)->assertSessionHasErrors('salary');
        $this->assertSame(1, EmployeeSalaryPayment::count());
        $profile->update(['day_sessions' => 1, 'evening_sessions' => 0]);
        $this->get(route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-11']))->assertOk()->assertSee('$327.50')->assertSee('Bank transfer 123');
        $this->get(route('employee-salaries.index', ['month' => '2026-11']))->assertOk()->assertSee('$327.50')->assertSee('Paid');
    }

    public function test_salary_payments_require_confirmation_current_amount_and_valid_month(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $this->profile($teacher, $admin);
        $data = ['month' => '2026-11', 'paid_on' => '2026-11-16', 'confirm' => 1, 'expected_total_cents' => 31250];
        $this->actingAs($admin)->post(route('employee-salaries.pay', $teacher), $data)->assertSessionHasErrors('salary');
        $this->post(route('employee-salaries.pay', $teacher), array_replace($data, ['expected_total_cents' => 32750, 'confirm' => 0]))->assertSessionHasErrors('confirm');
        $this->post(route('employee-salaries.pay', $teacher), array_replace($data, ['month' => '2026-12']))->assertSessionHasErrors('month');
        $this->assertSame(0, EmployeeSalaryPayment::count());
    }

    public function test_non_administrators_cannot_view_or_modify_payroll(): void
    {
        $teacher = $this->employee();
        foreach (['teacher', 'student', 'parent'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor)->get(route('employee-salaries.index'))->assertForbidden();
            $this->get(route('employee-salaries.show', $teacher))->assertForbidden();
            $this->put(route('employee-salaries.update', $teacher), [])->assertForbidden();
            $this->post(route('employee-salaries.pay', $teacher), [])->assertForbidden();
        }
    }
}
