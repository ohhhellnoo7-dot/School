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

    public function test_payroll_compares_scheduled_hours_with_the_work_week_when_configured(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer']);
        \Illuminate\Support\Facades\DB::table('teacher_profiles')->insert(['teacher_id' => $teacher->id, 'weekly_minutes' => 2400]);
        \App\Models\Schedule::create([
            'teacher_id' => $teacher->id, 'entry_type' => 'academic', 'day' => 'Monday',
            'starts_at' => '07:00', 'ends_at' => '08:30', 'room' => 'A1',
        ]);
        \App\Models\Schedule::create([
            'teacher_id' => $teacher->id, 'entry_type' => 'academic', 'day' => 'Tuesday',
            'starts_at' => '07:00', 'ends_at' => '08:00', 'room' => 'A1', 'cancelled_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('employee-salaries.index'))->assertOk()->assertSee('1.5/40 h');

        EmployeeSalaryProfile::create([
            'teacher_id' => $teacher->id, 'day_sessions' => 2, 'evening_sessions' => 2,
            'service_started_on' => '2026-11-01', 'updated_by' => $admin->id,
        ]);
        $this->get(route('employee-salaries.index'))->assertOk()->assertSee('1.5/50 h');
    }

    public function test_attendance_deductions_charge_daytime_absence_lateness_and_excuses_once_per_day(): void
    {
        $profile = new EmployeeSalaryProfile(['day_sessions' => 2, 'evening_sessions' => 2, 'service_started_on' => '2026-11-01']);
        $marks = collect();
        foreach ([['2026-11-02', 'Morning', 'absent'], ['2026-11-02', 'Afternoon', 'present'],
            ['2026-11-03', 'Morning', 'absent'], ['2026-11-03', 'Afternoon', 'absent'],
            ['2026-11-04', 'All', 'late'], ['2026-11-05', 'All', 'excused']] as [$date, $session, $status]) {
            $marks->push(new \App\Models\EmployeeSalaryAttendance(compact('date', 'session', 'status')));
        }
        $salary = app(\App\Services\EmployeeSalaryDeductions::class)->apply(
            ['total_cents' => 31250], $profile, collect(), collect(), $marks, Carbon::parse('2026-11-16'));
        $this->assertSame(3100, $salary['deduction_cents']);
        $this->assertSame(28150, $salary['total_cents']);
        $this->assertSame([1000, 1500, 100, 500], array_column($salary['deduction_details'], 'total_cents'));
    }

    public function test_deduction_sources_do_not_double_count_records_and_skip_future_missing_and_evening_absence(): void
    {
        $profile = new EmployeeSalaryProfile(['day_sessions' => 2, 'evening_sessions' => 2, 'service_started_on' => '2026-11-01']);
        $daily = new \Illuminate\Database\Eloquent\Collection([
            new TeacherAttendance(['id' => 1, 'date' => '2026-11-02', 'status' => 'absent']),
            new TeacherAttendance(['id' => 2, 'date' => '2026-11-03', 'status' => 'late']),
            new TeacherAttendance(['id' => 3, 'date' => '2026-11-20', 'status' => 'absent']),
        ]);
        $scans = new \Illuminate\Database\Eloquent\Collection([
            new TeacherSessionAttendance(['id' => 1, 'date' => '2026-11-04', 'session' => 'Evening', 'status' => 'excused']),
        ]);
        $marks = collect([new \App\Models\EmployeeSalaryAttendance(['date' => '2026-11-02', 'session' => 'All', 'status' => 'present'])]);
        $service = app(\App\Services\EmployeeSalaryDeductions::class);
        $salary = $service->apply(['total_cents' => 25000], $profile, $daily, $scans, $marks, Carbon::parse('2026-11-16'));
        $this->assertSame(600, $salary['deduction_cents']);
        $profile->day_sessions = 0;
        $salary = $service->apply(['total_cents' => 6250], $profile, collect([new TeacherAttendance(['date' => '2026-11-02', 'status' => 'absent'])]), collect(), collect(), Carbon::parse('2026-11-16'));
        $this->assertSame(0, $salary['deduction_cents']);
        $this->assertSame(6250, $salary['total_cents']);
    }

    public function test_payroll_attendance_search_and_saved_deductions_are_applied_to_payment_snapshot(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $profile = $this->profile($teacher, $admin);
        $profile->update(['service_started_on' => '2026-11-01', 'evening_sessions' => 0]);
        $this->assignHours($teacher, 37);
        $other = $this->employee();
        $this->actingAs($admin)->get(route('employee-salaries.index', ['search' => $teacher->name]))
            ->assertOk()->assertSee($teacher->name)->assertDontSee($other->name)->assertSee('data-salary-search', false)->assertDontSee('View payroll');
        $this->get(route('employee-salaries.attendance', ['search' => $teacher->name]))
            ->assertOk()->assertSee($teacher->name)->assertDontSee($other->name)->assertSee('Morning')->assertSee('Afternoon');
        foreach (['Morning', 'Afternoon'] as $session) {
            $this->post(route('employee-salaries.attendance.store', $teacher), ['date' => '2026-11-12', 'session' => $session, 'status' => 'absent'])
                ->assertSessionHasNoErrors();
        }
        $this->get(route('employee-salaries.index', ['search' => $teacher->name]))->assertOk()
            ->assertSee('37/40 h')->assertSee('-$15.00')->assertSeeInOrder(['$216.25', '/ $250.00'])->assertSee('1 absent')
            ->assertViewHas('rows', fn ($rows) => $rows->first()['full_salary_cents'] === 25000
                && $rows->first()['salary']['base_cents'] === 23125);
        $this->get(route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-11']))->assertOk()->assertSee('Attendance deduction details')->assertSee('$231.25')->assertSee('$216.25');
        $data = ['month' => '2026-11', 'paid_on' => '2026-11-16', 'confirm' => 1, 'expected_total_cents' => 25000];
        $this->post(route('employee-salaries.pay', $teacher), $data)->assertSessionHasErrors('salary');
        $this->post(route('employee-salaries.pay', $teacher), array_replace($data, ['expected_total_cents' => 21625]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_salary_payments', ['teacher_id' => $teacher->id, 'base_cents' => 23125, 'deduction_cents' => 1500, 'total_cents' => 21625]);
        $this->post(route('employee-salaries.attendance.store', $teacher), ['date' => '2026-11-12', 'session' => 'All', 'status' => 'present'])
            ->assertSessionHasErrors('attendance');
        $this->actingAs($teacher)->get(route('employee-salaries.attendance'))->assertForbidden();
        $this->post(route('employee-salaries.attendance.store', $teacher), ['date' => '2026-11-12', 'session' => 'All', 'status' => 'present'])->assertForbidden();
    }

    public function test_bulk_setup_uses_salary_cycle_instead_of_old_hire_dates_and_preserves_existing_profiles(): void
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
        $this->assertDatabaseHas('employee_salary_profiles', ['teacher_id' => $teacher->id, 'day_sessions' => 2, 'evening_sessions' => 2, 'service_started_on' => '2026-11-01 00:00:00']);
        $this->assertDatabaseHas('employee_salary_profiles', ['teacher_id' => $withoutHireDate->id, 'day_sessions' => 0, 'evening_sessions' => 1, 'service_started_on' => '2026-11-01 00:00:00']);
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

    private function assignHours(User $teacher, int $hours): void
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        for ($slot = 0; $slot < $hours; $slot++) {
            $start = 7 + intdiv($slot, 5);
            \App\Models\Schedule::create([
                'teacher_id' => $teacher->id, 'entry_type' => 'academic', 'day' => $days[$slot % 5],
                'starts_at' => sprintf('%02d:00', $start), 'ends_at' => sprintf('%02d:00', $start + 1), 'room' => 'A1',
            ]);
        }
    }

    public function test_monthly_base_salary_is_prorated_by_assigned_weekly_hours(): void
    {
        $calculator = new EmployeeSalaryCalculator;
        $profile = new EmployeeSalaryProfile(['day_sessions' => 0, 'evening_sessions' => 2, 'service_started_on' => '2026-01-01']);
        foreach ([0 => 0, 5 => 3125, 10 => 6250, 37 => 23125, 40 => 25000, 50 => 31250] as $hours => $expected) {
            $this->assertSame($expected, $calculator->calculate($profile, Carbon::parse('2026-01-01'), $hours * 60)['total_cents']);
        }
    }

    public function test_increase_is_five_dollars_per_completed_three_months_for_total_salary(): void
    {
        $calculator = new EmployeeSalaryCalculator;
        $profile = new EmployeeSalaryProfile(['day_sessions' => 2, 'evening_sessions' => 2, 'service_started_on' => '2026-01-15']);
        foreach (['2026-04-14' => 31250, '2026-04-15' => 31750, '2026-07-14' => 31750, '2026-07-15' => 32250, '2027-01-15' => 33250] as $date => $expected) {
            $this->assertSame($expected, $calculator->calculate($profile, Carbon::parse($date), 3000)['total_cents']);
        }
        $this->assertSame(0, $calculator->calculate($profile, Carbon::parse('2026-01-14'), 3000)['total_cents']);
        $profile->service_started_on = '2026-01-31';
        $this->assertSame(31750, $calculator->calculate($profile, Carbon::parse('2026-04-30'), 3000)['total_cents']);
    }

    public function test_payroll_pages_show_salary_settings_attendance_and_scan_details(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $this->profile($teacher, $admin);
        $this->assignHours($teacher, 50);
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
        $this->assignHours($teacher, 20);
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
        $this->assignHours($teacher, 50);
        $data = ['month' => '2026-11', 'paid_on' => '2026-11-16', 'confirm' => 1, 'expected_total_cents' => 32750, 'reference' => 'Bank transfer 123'];
        $this->actingAs($admin)->post(route('employee-salaries.pay', $teacher), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_salary_payments', ['teacher_id' => $teacher->id, 'base_cents' => 31250, 'raise_cents' => 1500, 'total_cents' => 32750]);
        $this->post(route('employee-salaries.pay', $teacher), $data)->assertSessionHasErrors('salary');
        $this->assertSame(1, EmployeeSalaryPayment::count());
        $profile->update(['day_sessions' => 1, 'evening_sessions' => 0]);
        \App\Models\Schedule::where('teacher_id', $teacher->id)->delete();
        $this->get(route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-11']))->assertOk()->assertSee('$327.50')->assertSee('Bank transfer 123');
        $this->get(route('employee-salaries.index', ['month' => '2026-11']))->assertOk()->assertSee('$327.50')->assertSee('Paid');
    }

    public function test_salary_payments_require_confirmation_current_amount_and_valid_month(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $this->profile($teacher, $admin);
        $this->assignHours($teacher, 50);
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
