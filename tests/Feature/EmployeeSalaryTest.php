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

    public function test_first_day_withdrawal_counts_completed_month_and_pay_moves_the_monthly_status(): void
    {
        $this->travelTo(Carbon::parse('2028-02-29 12:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $this->profile($teacher, $admin)->update(['service_started_on' => '2028-02-01', 'evening_sessions' => 0]);
        $this->assignHours($teacher, 40);
        $withdrawal = route('employee-salaries.index', ['withdrawal' => 1]);
        $pay = route('employee-salaries.payday', $teacher);
        $this->actingAs($admin)->get($withdrawal)->assertForbidden();
        $this->get(route('reports'))->assertOk()->assertSee('Available on the 1st of each month');
        $this->post(route('employee-salaries.attendance.store', $teacher), [
            'date' => '2028-02-29', 'session' => 'All', 'status' => 'absent',
        ])->assertSessionHasNoErrors();

        $this->travelTo(Carbon::parse('2028-03-01 08:00'));
        $this->get(route('reports'))->assertOk()->assertSee('#payday-withdrawal', false)
            ->assertSee('data-payroll-withdrawal-remaining>$235.00', false)
            ->assertSee('data-payroll-withdrawal-paid>$0.00', false);
        $this->get($withdrawal)->assertOk()->assertSee('29 February 2028')->assertSee('Total still to pay $235.00 USD')
            ->assertSee($pay, false)->assertViewHas('recorded', fn ($recorded) => (int) $recorded->paid_count === 0);
        $this->post($pay, [
            'month' => '2028-03', 'paid_on' => '2028-03-01', 'confirm' => 1, 'expected_total_cents' => 23500, 'expected_advance_cents' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->get($withdrawal)->assertOk()->assertSee('Total still to pay $0.00 USD')->assertDontSee($pay, false)
            ->assertViewHas('recorded', fn ($recorded) => (int) $recorded->paid_count === 1);
        $this->get(route('reports'))->assertOk()
            ->assertSee('data-payroll-withdrawal-remaining>$0.00', false)
            ->assertSee('data-payroll-withdrawal-paid>$235.00', false);
        $this->get(route('employee-salaries.index', ['month' => '2028-04']))->assertOk()
            ->assertViewHas('recorded', fn ($recorded) => (int) $recorded->paid_count === 0);
        $this->travelTo(Carbon::parse('2028-03-02 08:00'));
        $this->get($withdrawal)->assertForbidden();
    }

    public function test_early_salary_requires_approval_before_payout_and_is_applied_to_the_next_payday(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 12:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $this->profile($teacher, $admin)->update(['service_started_on' => '2026-11-01', 'evening_sessions' => 0]);
        $this->assignHours($teacher, 40);
        $fee = \App\Models\Fee::create([
            'student_id' => \App\Models\Student::factory()->create()->id,
            'description' => 'Tuition', 'amount' => '300.00', 'paid' => '300.00', 'currency' => 'USD', 'due_date' => today(),
        ]);
        \App\Models\FeePayment::create([
            'fee_id' => $fee->id, 'receipt_number' => 'EARLY-SALARY', 'request_key' => (string) \Illuminate\Support\Str::uuid(),
            'amount' => '300.00', 'currency' => 'USD', 'paid_on' => today(), 'method' => 'cash', 'recorded_by' => $admin->id,
        ]);
        $payload = ['request_type' => 'early_salary', 'submission_key' => (string) \Illuminate\Support\Str::uuid()];
        $store = route('dashboard.advance-requests.store');
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee('data-early-salary-amount="250.00"', false);
        $this->post($store, $payload + ['requested_amount' => '20.00'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post($store, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $early = \App\Models\TeacherAdvanceRequest::sole();
        $this->assertSame('2026-12-01', $early->salary_month->toDateString());
        $this->assertSame(25000, $early->amount_cents);
        $this->post($store, array_replace($payload, ['submission_key' => (string) \Illuminate\Support\Str::uuid()]))->assertSessionHasErrors('requested_amount');
        $approve = route('reports.expenses.advance-requests.approve', $early);
        $payout = route('reports.expenses.advance-requests.confirm', $early);
        $this->post($approve)->assertForbidden();
        $this->actingAs($admin)->post($payout)->assertSessionHasErrors('advance_request');
        $this->get(route('employee-salaries.teacher-reports'))->assertOk()
            ->assertDontSee('data-pending-advance-badge', false)->assertDontSee('Pending money requests');
        $this->get(route('reports.expenses.index'))->assertOk()
            ->assertSee('text-bg-danger ms-2" data-expense-live-count="modal"', false)
            ->assertSee('1 pending request')->assertSee('Early salary');
        $this->post($approve)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('school_expenses', 0);
        $approvedReport = $this->get(route('employee-salaries.teacher-reports', ['month' => '2026-11', 'report' => 'salary']))
            ->assertOk()->assertSee('Early salary awaiting payout')->assertSee('payroll-strip-early-paid', false);
        $this->assertSame(5, substr_count($approvedReport->getContent(), '<td class="payroll-strip-deduction"></td>'));
        $this->post($payout)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($payout)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('school_expenses', 1);
        $this->post(route('employee-salaries.attendance.store', $teacher), [
            'date' => '2026-11-19', 'session' => 'All', 'status' => 'absent',
        ])->assertSessionHasNoErrors();
        $detail = fn ($month) => route('employee-salaries.show', ['teacher' => $teacher, 'month' => $month]);
        $report = fn ($month) => route('employee-salaries.teacher-reports', ['month' => $month, 'report' => 'salary', 'teacher' => $teacher->id]);
        $this->get($detail('2026-11'))->assertOk()->assertViewHas('salary', fn ($salary) => $salary['advance_cents'] === 0);
        $this->get($report('2026-11'))->assertOk()
            ->assertSee('payroll-strip-total payroll-strip-early-paid', false)->assertSee('Paid early')->assertDontSee('$15.00 USD');
        $this->get(route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-11', 'slip' => 'salary']))
            ->assertOk()->assertSee('payroll-strip-total payroll-strip-early-paid', false)->assertSee('$250.00 USD')->assertDontSee('$15.00 USD');
        $this->travelTo(Carbon::parse('2026-12-01 12:00'));
        $quickPay = route('employee-salaries.payday', $teacher);
        $this->get(route('employee-salaries.index', ['withdrawal' => 1]))->assertOk()
            ->assertSee('Paid early')->assertSee('Total still to pay $0.00 USD')->assertDontSee($quickPay, false)
            ->assertViewHas('recorded', fn ($recorded) => (int) $recorded->paid_count === 1);
        $this->post($quickPay, [
            'month' => '2026-12', 'paid_on' => '2026-12-01', 'confirm' => 1, 'expected_total_cents' => 0,
        ])->assertSessionHasErrors('salary');
        $this->travelTo(Carbon::parse('2026-12-02 12:00'));
        $this->post(route('employee-salaries.attendance.store', $teacher), [
            'date' => '2026-12-02', 'session' => 'All', 'status' => 'absent',
        ])->assertSessionHasNoErrors();
        $salary = $this->get($detail('2026-12'))->assertOk()->assertSee('Scheduled payday is 1 December 2026')->viewData('salary');
        $this->assertSame(['total_cents' => 0, 'advance_cents' => 23500], array_intersect_key($salary, array_flip(['total_cents', 'advance_cents'])), json_encode($salary));
        $this->assertSame('2026-11-19', $salary['deduction_details'][0]['date']);
        $this->get($report('2026-12'))->assertOk()->assertDontSee('payroll-strip-early-paid', false)->assertSee('$15.00 USD');
        $this->post(route('employee-salaries.pay', $teacher), [
            'month' => '2026-12', 'paid_on' => '2026-12-01', 'confirm' => 1, 'expected_total_cents' => 0, 'expected_advance_cents' => 23500,
        ])->assertSessionHasNoErrors();
        $this->get($detail('2026-11'))->assertOk()->assertViewHas('salary', fn ($salary) => $salary['deduction_cents'] === 0);
        $this->get($detail('2027-01'))->assertOk()->assertViewHas('salary', fn ($salary) => $salary['advance_cents'] === 1500
            && $salary['deduction_details'][0]['date'] === '2026-12-02');
    }

    public function test_outstanding_advance_totals_are_separate_for_each_employee(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sarah = User::factory()->create(['role' => 'teacher', 'name' => 'Sarah Williams']);
        $james = User::factory()->create(['role' => 'teacher', 'name' => 'James Anderson']);

        foreach ([[$sarah, '5.00', 0], [$james, '100.00', 0], [$sarah, '100.00', 2000]] as [$employee, $amount, $repaid]) {
            \App\Models\SchoolExpense::create([
                'employee_id' => $employee->id, 'amount' => $amount, 'currency' => 'USD',
                'advance_recovered_cents' => $repaid, 'spent_on' => today(), 'recorded_by' => $admin->id,
                'request_key' => (string) \Illuminate\Support\Str::uuid(),
            ]);
        }

        $this->actingAs($admin)->get(route('employee-salaries.teacher-reports'))->assertOk()
            ->assertSeeInOrder(['Total for Sarah Williams', '$105.00', '$20.00', '$85.00', 'Total for James Anderson', '$100.00', '$0.00', '$100.00'])
            ->assertDontSee('$205.00');
    }

    public function test_teacher_advance_repayment_plan_is_applied_once_per_salary_month(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Sarah Williams', 'gender' => 'female']);
        $this->profile($teacher, $admin)->update(['service_started_on' => '2026-11-01', 'evening_sessions' => 0]);
        $this->assignHours($teacher, 10);
        $fee = \App\Models\Fee::create([
            'student_id' => \App\Models\Student::factory()->create()->id,
            'description' => 'Tuition', 'amount' => '200.00', 'paid' => '200.00', 'currency' => 'USD', 'due_date' => today(),
        ]);
        \App\Models\FeePayment::create([
            'fee_id' => $fee->id, 'receipt_number' => 'INSTALLMENT-ADVANCE', 'request_key' => (string) \Illuminate\Support\Str::uuid(),
            'amount' => '200.00', 'currency' => 'USD', 'paid_on' => today(), 'method' => 'cash', 'recorded_by' => $admin->id,
        ]);

        $store = route('dashboard.advance-requests.store');
        $request = ['requested_amount' => '100', 'submission_key' => (string) \Illuminate\Support\Str::uuid()];
        $this->actingAs($teacher)->from(route('dashboard'))->post($store, $request)->assertSessionHasErrors('repayment_months');
        $this->post($store, $request + ['repayment_months' => 5])->assertRedirect(route('dashboard'));
        $this->get(route('dashboard.advance-requests.history'))->assertOk()->assertSee('$20.00 per month for 5 months');

        $advance = \App\Models\TeacherAdvanceRequest::sole();
        $history = route('reports.expenses.index', ['currency' => 'USD', 'month' => '2026-11']);
        $this->actingAs($admin)->get($history)->assertOk()->assertSee('$20.00 per month for 5 months');
        $this->post(route('reports.expenses.advance-requests.confirm', $advance))->assertRedirect($history);
        $this->assertDatabaseHas('school_expenses', ['employee_id' => $teacher->id, 'repayment_months' => 5, 'amount' => '100.00']);
        $this->get($history)->assertOk()->assertSee(['Outstanding $100.00', '$20.00 per month for 5 months']);
        $liveReports = route('employee-salaries.teacher-reports', ['report' => 'leave', 'year' => 2026, 'search' => 'Unrelated']);
        $liveHeaders = ['X-Requested-With' => 'XMLHttpRequest', 'X-Include-Outstanding-Advances' => '1'];
        $this->get($liveReports, $liveHeaders)->assertOk()
            ->assertSeeInOrder(['Total for Sarah Williams', '$100.00', '$0.00', '$100.00']);

        foreach (range(0, 4) as $offset) {
            $date = Carbon::parse('2026-11-16 12:00:00')->addMonths($offset);
            $this->travelTo($date);
            $month = $date->format('Y-m');
            $report = route('employee-salaries.teacher-reports', ['teacher' => $teacher->id, 'month' => $month, 'report' => 'salary']);
            $salary = $this->get($report)->assertOk()->assertSee('$20.00 USD')->viewData('reports')->first()['salary'];
            $this->assertSame(2000, $salary['advance_cents']);
            $this->post(route('employee-salaries.pay', $teacher), [
                'month' => $month, 'paid_on' => $date->toDateString(), 'confirm' => 1,
                'expected_total_cents' => $salary['total_cents'], 'expected_advance_cents' => 2000,
            ])->assertSessionHasNoErrors();
            $this->get($report)->assertOk()->assertViewHas('reports', fn ($reports) => $reports->first()['payment']->advance_cents === 2000);
            if ($offset === 0) {
                $this->get(route('employee-salaries.teacher-reports', ['report' => 'leave', 'year' => 2026, 'search' => 'Unrelated']))
                    ->assertOk()->assertSee(['Outstanding advances', 'Sarah Williams', 'Female', 'Nov 16, 2026', '5 months'])
                    ->assertSeeInOrder(['Total for Sarah Williams', '$100.00', '$20.00', '$80.00']);
                $this->get($liveReports, $liveHeaders)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                    ->assertSee(['teacher-payroll-report-results', 'data-outstanding-advance-body'], false)
                    ->assertSeeInOrder(['Total for Sarah Williams', '$100.00', '$20.00', '$80.00']);
            }
        }

        $this->assertDatabaseHas('school_expenses', ['employee_id' => $teacher->id, 'advance_recovered_cents' => 10000]);
        $this->travelTo(Carbon::parse('2027-04-16 12:00:00'));
        $this->get(route('employee-salaries.teacher-reports', ['teacher' => $teacher->id, 'month' => '2027-04', 'report' => 'salary']))
            ->assertOk()->assertViewHas('reports', fn ($reports) => $reports->first()['salary']['advance_cents'] === 0)
            ->assertSee('No employees have outstanding salary advances.');
    }

    public function test_teacher_request_is_recorded_in_expenses_and_payroll_only_after_confirmation(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Amara Teacher']);
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->profile($teacher, $admin)->update(['service_started_on' => '2026-11-01', 'evening_sessions' => 0]);
        $this->assignHours($teacher, 2);
        $fee = \App\Models\Fee::create([
            'student_id' => \App\Models\Student::factory()->create()->id,
            'description' => 'Tuition', 'amount' => '100.00', 'paid' => '100.00', 'currency' => 'USD', 'due_date' => today(),
        ]);
        \App\Models\FeePayment::create([
            'fee_id' => $fee->id, 'receipt_number' => 'TEACHER-ADVANCE-REQUEST', 'request_key' => (string) \Illuminate\Support\Str::uuid(),
            'amount' => '100.00', 'currency' => 'USD', 'paid_on' => today(), 'method' => 'cash', 'recorded_by' => $admin->id,
        ]);
        $store = route('dashboard.advance-requests.store');
        $data = ['requested_amount' => '10', 'submission_key' => (string) \Illuminate\Support\Str::uuid()];
        $this->actingAs($admin)->post($store, $data)->assertForbidden();
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee('Request money');
        $this->from(route('dashboard'))->post($store, array_replace($data, ['requested_amount' => '$10']))
            ->assertSessionHasErrors('requested_amount');
        $this->post($store, $data)->assertRedirect(route('dashboard'));
        $this->post($store, $data)->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('teacher_advance_requests', 1);
        $this->assertDatabaseCount('school_expenses', 0);

        $advance = \App\Models\TeacherAdvanceRequest::sole();
        $confirm = route('reports.expenses.advance-requests.confirm', $advance);
        $history = route('reports.expenses.index', ['currency' => 'USD', 'month' => '2026-11']);
        $report = route('employee-salaries.teacher-reports', ['teacher' => $teacher->id, 'month' => '2026-11', 'report' => 'salary']);
        $liveExpenseHeaders = ['Accept' => 'application/json', 'X-Expense-Live' => '1'];
        $this->get($history, $liveExpenseHeaders)->assertForbidden();
        $this->post($confirm)->assertForbidden();
        $this->actingAs($admin)->get($history)->assertOk()->assertSee(['1 pending request', 'Amara Teacher requested $10.00']);
        $liveExpenses = $this->get($history, $liveExpenseHeaders)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('pendingCount', 1)->json('html');
        $this->assertStringContainsString('Amara Teacher requested $10.00', $liveExpenses);
        $this->assertStringNotContainsString('id="advance-search"', $liveExpenses);
        $sidebarPage = $this->get(route('employee-salaries.index'))->assertOk();
        $this->assertMatchesRegularExpression('~<span class="nav-label">Reports</span>\s*<span[^>]+data-pending-advance-count\s*>.*?</span>1</span>~s', $sidebarPage->getContent());
        $this->get($report)->assertOk()->assertViewHas('reports', fn ($reports) => $reports->first()['salary']['advance_cents'] === 0);
        $this->post($confirm)->assertRedirect($history);
        $this->post($confirm)->assertRedirect($history);
        $this->assertDatabaseCount('school_expenses', 1);
        $this->assertDatabaseHas('school_expenses', ['employee_id' => $teacher->id, 'amount' => '10.00', 'recorded_by' => $admin->id]);
        $this->get($history)->assertOk()->assertSee('Outstanding $10.00')->assertDontSee('Amara Teacher requested $10.00');
        $liveExpenses = $this->get($history, $liveExpenseHeaders)->assertOk()->assertJsonPath('pendingCount', 0)->json('html');
        $this->assertStringContainsString('Outstanding $10.00', $liveExpenses);
        $this->assertStringContainsString('90.00 USD', $liveExpenses);
        $this->assertStringNotContainsString('Amara Teacher requested $10.00', $liveExpenses);
        $this->get(route('employee-salaries.index'))->assertOk()->assertDontSee('data-pending-advance-count', false);
        $this->get($report)->assertOk()->assertViewHas('reports', fn ($reports) => $reports->first()['salary']['advance_cents'] === 1000);
        $this->post(route('employee-salaries.pay', $teacher), [
            'month' => '2026-11', 'paid_on' => '2026-11-16', 'confirm' => 1,
            'expected_total_cents' => 250, 'expected_advance_cents' => 1000,
        ])->assertSessionHasNoErrors();
        $this->get($report)->assertOk()->assertViewHas('reports', fn ($reports) => $reports->first()['payment']->advance_cents === 1000);

        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($otherTeacher)->post($store, [
            'requested_amount' => '99', 'submission_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect(route('dashboard'));
        $requestHistory = route('dashboard.advance-requests.history');
        $this->actingAs($admin)->get($requestHistory)->assertForbidden();
        $this->actingAs($teacher)->get(route('dashboard'))->assertSee('Request History');
        foreach ([['date' => '2026-11-16'], ['month' => '2026-11'], ['year' => '2026']] as $filter) {
            $this->get(route('dashboard.advance-requests.history', $filter))->assertOk()
                ->assertSee(['$10.00', 'Paid', 'Nov 16, 2026'])->assertDontSee('$99.00');
        }
        $this->get(route('dashboard.advance-requests.history', ['year' => '2025']))->assertOk()
            ->assertSee('No salary advance requests match these filters.')->assertDontSee('$10.00');

        $cancelPaid = route('dashboard.advance-requests.cancel', $advance);
        $pending = \App\Models\TeacherAdvanceRequest::where('teacher_id', $otherTeacher->id)->sole();
        $cancelPending = route('dashboard.advance-requests.cancel', $pending);
        $this->from($requestHistory)->post($cancelPaid)->assertSessionHasErrors('advance_request');
        $this->post($cancelPending)->assertNotFound();
        $this->actingAs($otherTeacher)->get($requestHistory)->assertOk()->assertSee($cancelPending);
        $this->from($requestHistory)->post($cancelPending)->assertRedirect($requestHistory);
        $this->get($requestHistory)->assertOk()->assertSee(['$99.00', 'Cancelled'])->assertDontSee($cancelPending);
        $this->actingAs($admin)->get($history)->assertOk()->assertDontSee('data-pending-advance-count', false);
        $this->from($history)->post(route('reports.expenses.advance-requests.confirm', $pending))
            ->assertSessionHasErrors('advance_request');

        $this->actingAs($otherTeacher)->post($store, [
            'requested_amount' => '3', 'submission_key' => (string) \Illuminate\Support\Str::uuid(),
        ])->assertRedirect(route('dashboard'));
        $toReject = \App\Models\TeacherAdvanceRequest::pending()->sole();
        $reject = route('reports.expenses.advance-requests.reject', $toReject);
        $this->post($reject)->assertForbidden();
        $this->actingAs($admin)->get($history)->assertOk()->assertSee($reject);
        $this->from($history)->post($reject)->assertRedirect($history);
        $this->post($reject)->assertRedirect($history);
        $this->get($history)->assertOk()->assertDontSee('data-pending-advance-count', false);
        $this->post(route('reports.expenses.advance-requests.confirm', $toReject))->assertSessionHasErrors('advance_request');
        $this->actingAs($otherTeacher)->get($requestHistory)->assertOk()->assertSee(['$3.00', 'Rejected'])
            ->assertDontSee(route('dashboard.advance-requests.cancel', $toReject));
        $this->assertDatabaseCount('school_expenses', 1);
    }

    public function test_employee_advance_is_recorded_once_and_recovered_across_payroll_months(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Amara Teacher']);
        $this->profile($teacher, $admin)->update(['service_started_on' => '2026-11-01', 'evening_sessions' => 0]);
        $this->assignHours($teacher, 2);
        $fee = \App\Models\Fee::create([
            'student_id' => \App\Models\Student::factory()->create()->id,
            'description' => 'Tuition', 'amount' => '100.00', 'paid' => '100.00', 'currency' => 'USD', 'due_date' => today(),
        ]);
        \App\Models\FeePayment::create([
            'fee_id' => $fee->id, 'receipt_number' => 'ADVANCE-TEST', 'request_key' => (string) \Illuminate\Support\Str::uuid(),
            'amount' => '100.00', 'currency' => 'USD', 'paid_on' => today(), 'method' => 'cash', 'recorded_by' => $admin->id,
        ]);
        $store = route('reports.expenses.advances.store');
        $advance = ['employee_id' => $teacher->id, 'amount' => '20.00', 'spent_on' => '2026-11-16', 'request_key' => (string) \Illuminate\Support\Str::uuid()];
        $history = route('reports.expenses.index', ['currency' => 'USD', 'month' => '2026-11']);

        $this->actingAs($teacher)->post($store, $advance)->assertForbidden();
        $this->actingAs($admin)->get($history)->assertOk()
            ->assertSee('data-bs-target="#employee-advance-modal"', false)->assertSee('Amara Teacher');
        $this->get(route('reports.expenses.advances.create'))->assertOk()->assertSee('Amara Teacher');
        $this->from($history)->post($store, array_replace($advance, ['amount' => '0.00']))
            ->assertRedirect($history)->assertSessionHasErrors('amount');
        $this->get($history)->assertOk()->assertSee('data-reopen-advance', false);
        $this->post($store, $advance)->assertRedirect($history);
        $this->post($store, $advance)->assertRedirect($history);
        $this->assertDatabaseCount('school_expenses', 1);
        $this->get($history)->assertOk()->assertSee(['Amara Teacher', '80.00 USD', 'Outstanding $20.00']);

        $report = route('employee-salaries.teacher-reports', ['teacher' => $teacher->id, 'month' => '2026-11', 'report' => 'salary']);
        $this->get($report)->assertOk()->assertSee('ប្រាក់ខ្ចី')
            ->assertViewHas('reports', fn ($reports) => $reports->first()['salary']['advance_cents'] === 1250);
        $pay = route('employee-salaries.pay', $teacher);
        $this->post($pay, ['month' => '2026-11', 'paid_on' => '2026-11-16', 'confirm' => 1, 'expected_total_cents' => 0, 'expected_advance_cents' => 1250])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_salary_payments', ['teacher_id' => $teacher->id, 'advance_cents' => 1250, 'total_cents' => 0]);
        $this->get($history)->assertOk()->assertSee('Outstanding $7.50');

        $this->travelTo(Carbon::parse('2026-12-16 12:00:00'));
        $this->post($pay, ['month' => '2026-12', 'paid_on' => '2026-12-16', 'confirm' => 1, 'expected_total_cents' => 500, 'expected_advance_cents' => 750])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('school_expenses', ['employee_id' => $teacher->id, 'advance_recovered_cents' => 2000]);
        $this->get($report)->assertOk()->assertViewHas('reports', fn ($reports) => $reports->first()['payment']->advance_cents === 1250);
    }

    public function test_report_salary_editor_changes_unpaid_amounts_and_preserves_recorded_payments(): void
    {
        $this->travelTo(Carbon::parse('2026-11-16 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $this->profile($teacher, $admin)->update(['service_started_on' => '2026-11-01', 'evening_sessions' => 1]);
        $this->assignHours($teacher, 40);
        foreach (['2026-11-09' => 'late', '2026-11-10' => 'absent', '2026-11-11' => 'excused', '2026-11-12' => 'excused'] as $date => $status) {
            TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => $date, 'status' => $status, 'recorded_by' => $admin->id]);
        }
        $reports = route('employee-salaries.teacher-reports', ['teacher' => $teacher->id, 'month' => '2026-11', 'report' => 'salary']);
        $update = route('employee-salaries.salary-rates.update');
        $rates = ['morning' => '135.00', 'afternoon' => '125', 'evening' => '55', 'late' => '2', 'excused' => '7', 'absence_one' => '12', 'absence_two' => '20', 'absence_single_session' => '8'];

        $this->actingAs($teacher)->put($update, ['rates' => $rates])->assertForbidden();
        $this->actingAs($admin)->get($reports)->assertOk()->assertSee('Edit salary')->assertSee('125.00');
        $this->from($reports)->put($update, ['rates' => array_replace($rates, ['late' => '-1'])])
            ->assertRedirect($reports)->assertSessionHasErrorsIn('salaryRates', 'rates.late');
        $this->from($reports)->put($update, ['rates' => $rates])->assertRedirect($reports)->assertSessionHasNoErrors();
        $this->get($reports)->assertOk()->assertSee(['$315.00 USD', '$280.00 USD', '$251.00 USD']);

        $this->post(route('employee-salaries.pay', $teacher), [
            'month' => '2026-11', 'paid_on' => '2026-11-16', 'confirm' => 1, 'expected_total_cents' => 25100,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('employee_salary_payments', [
            'teacher_id' => $teacher->id, 'full_salary_cents' => 31500, 'deduction_cents' => 2900, 'total_cents' => 25100,
        ]);
        $this->put($update, ['rates' => array_replace($rates, ['morning' => '145', 'late' => '5'])])->assertSessionHasNoErrors();
        $this->get($reports)->assertOk()->assertSee(['$315.00 USD', '$251.00 USD'])->assertDontSee('$325.00 USD');

        $eveningProfile = new EmployeeSalaryProfile(['day_sessions' => 0, 'evening_sessions' => 1, 'service_started_on' => '2026-11-01']);
        $absence = collect([new TeacherAttendance(['date' => '2026-11-10', 'status' => 'absent'])]);
        $salary = app(\App\Services\EmployeeSalaryDeductions::class)->apply(
            ['total_cents' => 5500], $eveningProfile, $absence, collect(), collect(), Carbon::parse('2026-11-16'),
        );
        $this->assertSame(800, $salary['deduction_cents']);
    }

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
        $this->get(route('employee-salaries.teacher-reports', ['teacher' => $teacher->id, 'report' => 'salary']))
            ->assertOk()->assertSeeText(['ចំនួនវេនការងារ', '1.5/50 ម៉ោង'])
            ->assertSee('ម៉ោងការងារ<br>ប្រចាំសប្ដាហ៍', false)->assertSee('<td>4</td>', false);
        EmployeeSalaryProfile::where('teacher_id', $teacher->id)->update(['evening_sessions' => 0]);
        $this->get(route('employee-salaries.show', ['teacher' => $teacher, 'slip' => 'salary']))
            ->assertOk()->assertSee('1.5/40 ម៉ោង')->assertSee('<td>2</td>', false);
    }

    public function test_attendance_deductions_allow_one_free_excused_day_per_month(): void
    {
        $profile = new EmployeeSalaryProfile(['day_sessions' => 2, 'evening_sessions' => 2, 'service_started_on' => '2026-11-01']);
        $marks = collect();
        foreach ([['2026-11-02', 'Morning', 'absent'], ['2026-11-02', 'Afternoon', 'present'],
            ['2026-11-03', 'Morning', 'absent'], ['2026-11-03', 'Afternoon', 'absent'],
            ['2026-11-04', 'All', 'late'], ['2026-11-05', 'All', 'excused'],
            ['2026-11-06', 'Morning', 'excused'], ['2026-11-06', 'Afternoon', 'excused'],
            ['2026-12-01', 'All', 'excused']] as [$date, $session, $status]) {
            $marks->push(new \App\Models\EmployeeSalaryAttendance(compact('date', 'session', 'status')));
        }
        $salary = app(\App\Services\EmployeeSalaryDeductions::class)->apply(
            ['total_cents' => 31250], $profile, collect(), collect(), $marks, Carbon::parse('2026-12-16'));
        $this->assertSame(3100, $salary['deduction_cents']);
        $this->assertSame(28150, $salary['total_cents']);
        $this->assertSame([1000, 1500, 100, 0, 500, 0], array_column($salary['deduction_details'], 'total_cents'));

        $profile->evening_sessions = 0;
        $counts = app(\App\Services\EmployeeSalaryDeductions::class)->slipCounts(collect(), collect(), $marks, $profile);
        $this->assertSame(1, $counts->get('absent_full'));
        $this->assertSame(1, $counts->get('absent_partial'));
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
        $this->assertSame(100, $salary['deduction_cents']);
        $profile->day_sessions = 0;
        $salary = $service->apply(['total_cents' => 6250], $profile, collect([new TeacherAttendance(['date' => '2026-11-02', 'status' => 'absent'])]), collect(), collect(), Carbon::parse('2026-11-16'));
        $this->assertSame(0, $salary['deduction_cents']);
        $this->assertSame(6250, $salary['total_cents']);
    }

    public function test_payroll_search_matches_name_prefixes_and_employee_ids(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        User::factory()->create(['role' => 'teacher', 'name' => 'Bernadette Marquardt', 'profile_number' => 'TCH026']);
        User::factory()->create(['role' => 'teacher', 'name' => 'Abigail Brown', 'profile_number' => 'TCH027']);

        $this->actingAs($admin)->get(route('employee-salaries.index', ['search' => 'b']))
            ->assertOk()->assertSee('Bernadette Marquardt')->assertDontSee('Abigail Brown');

        $this->get(route('employee-salaries.index', ['search' => '027']))
            ->assertOk()->assertSee('Abigail Brown')->assertDontSee('Bernadette Marquardt');

        $download = $this->get(route('employee-salaries.index', ['search' => '027', 'month' => '2026-11', 'export' => 'csv']))
            ->assertOk()->assertDownload('teacher-payroll-2026-11.csv');
        $this->assertStringContainsString('Abigail Brown', $download->streamedContent());
        $this->assertStringNotContainsString('Bernadette Marquardt', $download->streamedContent());
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
        $this->get(route('employee-salaries.index', ['month' => '2026-11', 'search' => $other->name]))
            ->assertOk()->assertViewHas('teacherCount', 2)
            ->assertViewHas('recorded', fn ($recorded) => (int) $recorded->paid_count === 1 && (int) $recorded->paid_cents === 21625);
        $this->get(route('employee-salaries.index', ['month' => '2026-10']))
            ->assertOk()->assertViewHas('recorded', fn ($recorded) => (int) $recorded->paid_count === 0 && (int) $recorded->paid_cents === 0);
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
        $slip = ['teacher' => $teacher, 'month' => '2026-11', 'slip' => 'salary'];
        $this->get(route('employee-salaries.show', $slip))
            ->assertOk()->assertSee(['Salary slip', '312.50 USD', '327.50 USD', 'Bank transfer 123']);
    }

    public function test_annual_leave_report_counts_at_most_one_day_per_month_and_skips_closed_holidays(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        foreach (['2026-01-12' => 'absent', '2026-04-14' => 'absent', '2026-10-21' => 'excused', '2026-10-22' => 'absent', '2026-11-16' => 'excused'] as $date => $status) {
            \App\Models\TeacherAttendance::create([
                'teacher_id' => $teacher->id, 'date' => $date, 'status' => $status,
                'program' => $teacher->program, 'recorded_by' => $admin->id,
            ]);
        }
        app(\App\Services\SchoolHolidayClosure::class)->setEnabled(true);
        $url = route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-11', 'slip' => 'leave']);
        $grid = route('teacher-attendance.monthly', ['month' => '2026-11']);
        $reportList = route('employee-salaries.teacher-reports', ['month' => '2026-11']);
        $this->actingAs($admin)->get($grid)->assertOk()->assertSee('Teacher reports')
            ->assertSee($reportList)->assertDontSee('attendance-report-heading', false);
        $this->get($reportList)->assertOk()->assertSee([$teacher->name, 'Salary slip', 'Annual leave record']);
        $this->get($url)->assertOk()->assertSee('Annual leave record')->assertSee('<td>3</td><td>9</td>', false)
            ->assertViewHas('leaveMonths', fn ($months) => $months[1] === 1 && $months[4] === 0 && $months[10] === 1 && $months[11] === 1 && $months->sum() === 3);
        $this->actingAs($teacher)->get($grid)->assertOk()->assertDontSee('Salary slip');
        $this->get($url)->assertForbidden();
    }

    public function test_attendance_bonus_is_report_only_and_requires_every_scheduled_session(): void
    {
        $this->travelTo(Carbon::parse('2026-05-01 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        EmployeeSalaryProfile::create([
            'teacher_id' => $teacher->id, 'day_sessions' => 2, 'evening_sessions' => 2,
            'service_started_on' => '2026-04-01', 'updated_by' => $admin->id,
        ]);
        foreach (['Morning', 'Afternoon', 'Evening'] as $index => $session) {
            $class = \App\Models\SchoolClass::create([
                'name' => $session, 'section' => 'A', 'program' => 'Khmer', 'session' => $session,
            ]);
            \App\Models\Schedule::create([
                'teacher_id' => $teacher->id, 'school_class_id' => $class->id,
                'day' => 'Wednesday', 'academic_year' => '2026-2027', 'entry_type' => 'academic',
                'starts_at' => ['07:00', '13:00', '17:00'][$index],
                'ends_at' => ['11:00', '17:00', '19:00'][$index], 'room' => 'A1',
            ]);
        }
        foreach (['2026-04-01', '2026-04-08', '2026-04-22', '2026-04-29'] as $date) {
            $mark = TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => $date,
                'status' => 'present', 'program' => 'Khmer', 'recorded_by' => $admin->id]);
        }
        app(\App\Services\SchoolHolidayClosure::class)->setEnabled(true);
        $url = route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-04', 'slip' => 'leave']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('$300.00 USD')->assertSee('payroll-strip-short-service', false)
            ->assertViewHas('attendanceBonus', ['month' => 2500, 'year' => 2500, 'qualifyingMonths' => [4], 'pending' => null, 'annualMaximum' => 30000]);
        $list = route('employee-salaries.teacher-reports', ['teacher' => $teacher->id, 'month' => '2026-04', 'report' => 'leave']);
        $this->get($list)->assertOk()->assertSee('$300.00 USD');
        $this->assertDatabaseCount('employee_salary_payments', 0);
        $this->travelTo(Carbon::parse('2026-04-29 18:00:00'));
        $this->get($url)->assertOk()
            ->assertSee(['payroll-leave-not-started', 'payroll-leave-in-progress', 'payroll-leave-future'], false)
            ->assertViewHas('attendanceBonus', ['month' => null, 'year' => 0, 'qualifyingMonths' => [], 'pending' => 2500, 'annualMaximum' => 30000]);
        $mark->update(['status' => 'excused']);
        $this->get($url)->assertOk()->assertViewHas('attendanceBonus', ['month' => 0, 'year' => 0, 'qualifyingMonths' => [], 'pending' => null, 'annualMaximum' => 30000]);
        $this->travelTo(Carbon::parse('2026-05-01 12:00:00'));
        $mark->update(['status' => 'late']);
        $this->get($url)->assertOk()->assertSee('payroll-leave-complete', false)
            ->assertViewHas('attendanceBonus', ['month' => 2500, 'year' => 2500, 'qualifyingMonths' => [4], 'pending' => null, 'annualMaximum' => 30000]);
        $mark->update(['status' => 'excused']);
        $this->get($url)->assertOk()->assertViewHas('attendanceBonus', ['month' => 0, 'year' => 0, 'qualifyingMonths' => [], 'pending' => null, 'annualMaximum' => 30000]);
        $mark->delete();
        $this->get($url)->assertOk()->assertViewHas('attendanceBonus', ['month' => 0, 'year' => 0, 'qualifyingMonths' => [], 'pending' => null, 'annualMaximum' => 30000]);
        EmployeeSalaryProfile::where('teacher_id', $teacher->id)->update(['evening_sessions' => 0]);
        TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => '2026-04-29',
            'status' => 'excused', 'program' => 'Khmer', 'recorded_by' => $admin->id]);
        $this->get($url)->assertOk()->assertSeeInOrder(['ប្រាក់រង្វាន់ពេញមួយឆ្នាំ', '$240.00 USD', '$0.00 USD']);
        TeacherAttendance::where('teacher_id', $teacher->id)->whereDate('date', '2026-04-29')->update(['status' => 'present']);
        foreach (['2026-05-06', '2026-05-13', '2026-05-20', '2026-05-27'] as $date) {
            TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => $date,
                'status' => 'present', 'program' => 'Khmer', 'recorded_by' => $admin->id]);
        }
        $this->travelTo(Carbon::parse('2026-06-01 12:00:00'));
        $this->get($url)->assertOk()->assertSee('$40.00 USD')
            ->assertViewHas('attendanceBonus', fn ($bonus) => $bonus['year'] === 4000 && $bonus['qualifyingMonths'] === [4, 5]);
    }

    public function test_teacher_reports_use_the_selected_leave_year_and_hide_empty_history(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $url = route('employee-salaries.teacher-reports');

        $this->actingAs($admin)->get($url.'?report=leave&year=2025&month=2026-11')
            ->assertOk()->assertSee('Leave record year')
            ->assertSee('No leave records match these filters for 2025.')
            ->assertViewHas('month', fn ($month) => $month->format('Y-m') === '2025-12');
        TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => '2025-05-01',
            'status' => 'excused', 'program' => 'Khmer', 'recorded_by' => $admin->id]);
        $this->get($url.'?report=leave&year=2025')->assertOk()
            ->assertViewHas('reports', fn ($reports) => $reports->pluck('teacher.id')->all() === [$teacher->id]);
        $this->get($url.'?report=leave&month=2026-06')
            ->assertOk()->assertViewHas('month', fn ($month) => $month->year === 2026);
        $this->get($url.'?report=salary&year=2025&month=2026-06')
            ->assertOk()->assertViewHas('month', fn ($month) => $month->format('Y-m') === '2026-06');
    }

    public function test_leave_bonus_service_requirement_applies_to_all_teachers(): void
    {
        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $settings = [
            'teacher_id' => $teacher->id, 'day_sessions' => 2, 'evening_sessions' => 0,
            'service_started_on' => '2026-04-01', 'updated_by' => $admin->id,
        ];
        EmployeeSalaryProfile::create($settings);
        EmployeeSalaryProfile::create(array_replace($settings, ['teacher_id' => $this->employee()->id]));
        $report = route('employee-salaries.teacher-reports', ['month' => '2026-06', 'report' => 'leave']);
        $update = route('employee-salaries.leave-bonus-eligibility.update');

        $this->actingAs($admin)->get($report)->assertOk()
            ->assertSee('data-bonus-months="6"', false)->assertSee('payroll-strip-short-service', false);
        $this->from($report)->put($update, ['bonus_service_months' => 0])->assertSessionHasErrors('bonus_service_months');
        $this->put($update, ['bonus_service_months' => 2])->assertRedirect($report);
        $this->assertDatabaseHas('teacher_management_settings', ['id' => 1, 'bonus_service_months' => 2]);
        $response = $this->get($report)->assertOk()
            ->assertSee('data-bonus-months="2"', false)->assertDontSee('payroll-strip-short-service', false);
        $this->assertSame(2, substr_count($response->getContent(), 'data-bonus-months="2"'));
        $this->actingAs($teacher)->put($update, ['bonus_service_months' => 1])->assertForbidden();
    }

    public function test_session_stop_is_cancellable_until_next_month_and_locks_the_earlier_leave_period(): void
    {
        $this->travelTo(Carbon::parse('2026-06-13 12:00:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = $this->employee();
        $profile = EmployeeSalaryProfile::create([
            'teacher_id' => $teacher->id, 'day_sessions' => 2, 'evening_sessions' => 0,
            'service_started_on' => '2026-01-01', 'updated_by' => $admin->id,
        ]);
        $group = \App\Models\ClassGroup::create(['name' => 'Grade 7A', 'grade' => 7, 'program' => 'Khmer', 'teacher_id' => $teacher->id]);
        $room = \App\Models\Classroom::create(['name' => 'Room A1', 'room_number' => 'A1']);
        foreach (['Morning' => ['07:00', '11:00'], 'Afternoon' => ['13:00', '17:00']] as $session => [$start, $end]) {
            $class = \App\Models\SchoolClass::create([
                'name' => $session, 'section' => 'A', 'program' => 'Khmer', 'session' => $session, 'teacher_id' => $teacher->id,
                'class_group_id' => $group->id,
            ]);
            $classSession = $group->sessions()->create(['session' => $session, 'starts_at' => $start, 'ends_at' => $end]);
            $bookings[$session] = \App\Models\ClassroomBooking::create([
                'class_group_id' => $group->id, 'class_session_id' => $classSession->id, 'classroom_id' => $room->id,
                'teacher_id' => $teacher->id, 'day_of_week' => 'Wednesday', 'starts_at' => $start, 'ends_at' => $end,
            ]);
            $lessons[$session] = \App\Models\Schedule::create([
                'teacher_id' => $teacher->id, 'school_class_id' => $class->id, 'day' => 'Wednesday',
                'academic_year' => '2026-2027', 'entry_type' => 'academic', 'starts_at' => $start, 'ends_at' => $end, 'room' => 'A1',
            ]);
        }
        foreach (Carbon::parse('2026-01-07')->weeksUntil('2026-07-31') as $day) {
            TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => $day->toDateString(), 'status' => 'present',
                'program' => 'Khmer', 'recorded_by' => $admin->id]);
        }

        $stop = route('teacher-management.sessions.stop', $teacher);
        $edit = route('teacher-management.edit', $teacher);
        $reports = route('employee-salaries.teacher-reports', ['teacher' => $teacher->id, 'month' => '2026-07', 'report' => 'leave']);
        $this->actingAs($admin)->from($edit)->post($stop, ['session' => 'Morning'])->assertRedirect($edit);
        $this->get($edit)->assertOk()->assertSee('01 Jul 2026')->assertSee('Cancel stop');
        $this->delete($stop, ['session' => 'Morning'])->assertRedirect();
        $this->assertDatabaseCount('teacher_session_stops', 0);
        $this->post($stop, ['session' => 'Morning'])->assertRedirect();
        $this->assertSame(2, $profile->fresh()->day_sessions);
        $this->assertNotNull($lessons['Morning']->fresh());

        $this->travelTo(Carbon::parse('2026-07-01 00:01:00'));
        $this->get($reports)->assertOk()->assertSeeInOrder(['Locked leave record', '$120.00 USD', 'New leave period', '$60.00 USD', '$0.00 USD']);
        $this->get($edit)->assertOk()->assertDontSee('Cancel stop');
        $this->delete($stop, ['session' => 'Morning'])->assertSessionHasErrors('session');
        $this->assertNull(\App\Models\Schedule::find($lessons['Morning']->id));
        $this->assertNotNull(\App\Models\Schedule::find($lessons['Afternoon']->id));
        $this->assertSame(1, $profile->fresh()->day_sessions);
        $this->assertDatabaseHas('school_classes', ['id' => $lessons['Morning']->school_class_id, 'teacher_id' => null]);
        $this->assertNull($bookings['Morning']->fresh()->teacher_id);
        $this->assertEquals($teacher->id, $bookings['Afternoon']->fresh()->teacher_id);
        $this->assertEquals($teacher->id, $group->fresh()->teacher_id);

        TeacherAttendance::where('teacher_id', $teacher->id)->whereDate('date', '2026-06-24')->update(['status' => 'absent']);
        $this->travelTo(Carbon::parse('2026-08-01 12:00:00'));
        $this->get($reports)->assertOk()->assertSeeInOrder(['$120.00 USD', '$60.00 USD', '$10.00 USD']);
        $this->get(route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-07', 'slip' => 'leave']))
            ->assertOk()->assertSeeInOrder(['Locked leave record', '$120.00 USD', 'New leave period', '$60.00 USD', '$10.00 USD']);
        $this->get(route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-06']))
            ->assertOk()->assertViewHas('salary', fn ($salary) => $salary['base_cents'] === 5000);
        $this->get(route('employee-salaries.show', ['teacher' => $teacher, 'month' => '2026-07']))
            ->assertOk()->assertViewHas('salary', fn ($salary) => $salary['base_cents'] === 2500);
    }

    public function test_teacher_reports_filter_both_slips_by_name_program_and_session(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $match = User::factory()->create(['role' => 'teacher', 'name' => 'Sok Dara', 'program' => 'Khmer']);
        $others = [
            User::factory()->create(['role' => 'teacher', 'name' => 'Chan Dara', 'program' => 'Khmer']),
            User::factory()->create(['role' => 'teacher', 'name' => 'Sam Dara', 'program' => 'English']),
            User::factory()->create(['role' => 'teacher', 'name' => 'Kim Sopheak', 'program' => 'Khmer']),
        ];
        foreach ([$match, ...$others] as $index => $teacher) {
            \App\Models\SchoolClass::create([
                'name' => 'Grade '.($index + 1), 'section' => 'A', 'program' => $teacher->program,
                'session' => $index === 1 ? 'Afternoon' : 'Morning', 'teacher_id' => $teacher->id,
            ]);
        }

        $url = route('employee-salaries.teacher-reports', [
            'month' => '2026-11', 'program' => 'Khmer', 'session' => 'Morning', 'search' => 'Dara',
        ]);
        $this->actingAs($admin)->get($url, ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee(['Sok Dara', 'Salary slip', 'Annual leave record'])
            ->assertDontSee(['Chan Dara', 'Sam Dara', 'Kim Sopheak']);
        $all = $this->get(route('employee-salaries.teacher-reports', ['month' => '2026-11']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->getContent();
        $this->assertSame(8, substr_count($all, '<table class="payroll-slip-table'));
        $this->assertSame(4, substr_count($all, 'class="salary-report-teacher"'));
        $this->assertStringContainsString('class="salary-report-teacher" aria-label="Sok Dara"', $all);
        $this->assertSame(2, substr_count($all, '>Sok Dara</a>'));
        $this->assertSame(2, substr_count($all, '>Chan Dara</a>'));
        $teacherQuery = ['month' => '2026-11', 'teacher' => $match->id];
        $salaryOnly = $this->get(route('employee-salaries.teacher-reports', $teacherQuery + ['report' => 'salary']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('aria-label="Salary slip"', false)
            ->assertDontSee(['aria-label="Annual leave record"', 'Chan Dara'], false);
        $this->assertSame(1, substr_count($salaryOnly->getContent(), '<table class="payroll-slip-table'));
        $this->get(route('employee-salaries.teacher-reports', $teacherQuery + ['report' => 'leave']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('aria-label="Annual leave record"', false)->assertDontSee('aria-label="Salary slip"', false);
        $this->get(route('teacher-attendance.monthly', ['month' => '2026-11']))
            ->assertOk()->assertSee(route('employee-salaries.teacher-reports', $teacherQuery));
        $this->actingAs($match)->get($url)->assertForbidden();
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
