<?php

namespace App\Http\Controllers;

use App\Services\SchoolHolidayClosure;
use App\Models\{EmployeeSalaryAttendance, EmployeeSalaryPayment, EmployeeSalaryProfile, TeacherAttendance, TeacherSessionAttendance, User};
use App\Services\EmployeeAttendanceBonus;
use App\Services\EmployeeSalaryCalculator;
use App\Services\EmployeeSalaryAdvances;
use App\Services\EmployeeSalaryRates;
use App\Services\{TeacherLeaveReport, TeacherManagementService, TeacherSessionStops};
use App\Support\AttendanceSession;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EmployeeSalaryController extends Controller
{
    private function month(Request $request): Carbon
    {
        $data = $request->validate(['month' => 'nullable|date_format:Y-m']);

        return Carbon::createFromFormat('!Y-m', $data['month'] ?? today()->format('Y-m'));
    }

    private function teacher(User $teacher): void
    {
        abort_unless($teacher->role === 'teacher', 404);
    }

    private function assessmentDate(Carbon $month): Carbon
    {
        return $month->isCurrentMonth() ? today() : $month->copy()->endOfMonth()->startOfDay();
    }

    private function salary(EmployeeSalaryProfile $profile, Carbon $month, EmployeeSalaryCalculator $calculator, ?int $assignedMinutes = null, ?\Illuminate\Support\Collection $advances = null): array
    {
        $range = [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()];
        $assignedMinutes ??= $calculator->assignedMinutes([$profile->teacher_id])->get($profile->teacher_id, 0);

        $salary = app(\App\Services\EmployeeSalaryDeductions::class)->apply(
            $calculator->calculate($profile, $this->assessmentDate($month), $assignedMinutes), $profile,
            TeacherAttendance::where('teacher_id', $profile->teacher_id)->whereBetween('date', $range)->get(),
            TeacherSessionAttendance::where('teacher_id', $profile->teacher_id)->whereBetween('date', $range)->get(),
            EmployeeSalaryAttendance::where('teacher_id', $profile->teacher_id)->whereBetween('date', $range)->get(),
            $this->assessmentDate($month)
        );
        $service = app(EmployeeSalaryAdvances::class);

        return $service->apply($salary, $advances ?? $service->outstanding([$profile->teacher_id], $this->assessmentDate($month))->get($profile->teacher_id, collect()));
    }

    public function index(Request $request, EmployeeSalaryCalculator $calculator, TeacherManagementService $teaching)
    {
        $month = $this->month($request);
        $asOf = $this->assessmentDate($month);
        $request->validate(['search' => 'nullable|string|max:255', 'export' => 'nullable|in:csv']);
        $teacherPopulation = User::where('role', 'teacher');
        $teacherQuery = (clone $teacherPopulation)
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->whereRaw('LOWER(name) LIKE ?', [mb_strtolower($request->search).'%'])
                ->orWhere('profile_number', 'like', '%'.$request->search.'%')))
            ->orderBy('name')->orderBy('id');
        $exporting = $request->input('export') === 'csv';
        $teachers = $exporting ? $teacherQuery->get() : $teacherQuery->paginate(20)->withQueryString();
        $profiles = EmployeeSalaryProfile::whereIn('teacher_id', $teachers->modelKeys())->get()->keyBy('teacher_id');
        $payments = EmployeeSalaryPayment::whereIn('teacher_id', $teachers->modelKeys())->whereDate('salary_month', $month)->get()->keyBy('teacher_id');
        $attendance = TeacherAttendance::whereIn('teacher_id', $teachers->modelKeys())
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get()->groupBy('teacher_id');
        $range = [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()];
        $scans = TeacherSessionAttendance::whereIn('teacher_id', $teachers->modelKeys())->whereBetween('date', $range)->get()->groupBy('teacher_id');
        $overrides = EmployeeSalaryAttendance::whereIn('teacher_id', $teachers->modelKeys())->whereBetween('date', $range)->get()->groupBy('teacher_id');
        $teachingMinutes = $calculator->assignedMinutes($teachers->modelKeys());
        $stops = app(TeacherSessionStops::class);
        $stopHistory = $stops->history($teachers->modelKeys());
        $weeklyLimits = DB::table('teacher_profiles')->whereIn('teacher_id', $teachers->modelKeys())->pluck('weekly_minutes', 'teacher_id');
        $advanceService = app(EmployeeSalaryAdvances::class);
        $advances = $advanceService->outstanding($teachers->modelKeys(), $asOf->min(today()));
        $rows = ($exporting ? $teachers : $teachers->getCollection())->map(function ($teacher) use ($profiles, $payments, $attendance, $calculator, $asOf, $scans, $overrides, $teaching, $teachingMinutes, $weeklyLimits, $stops, $stopHistory, $month, $advanceService, $advances) {
            $profile = $profiles->get($teacher->id);
            $payment = $payments->get($teacher->id);
            [$profile, $assignedMinutes] = $stops->payrollAt($stopHistory->get($teacher->id, collect()), $month, $profile, $teachingMinutes->get($teacher->id, 0));
            $workSessions = $payment ?? $profile;
            $weeklyHours = $workSessions
                ? $calculator->weeklyWorkHours($workSessions->day_sessions, $workSessions->evening_sessions)
                : null;

            return ['teacher' => $teacher, 'profile' => $profile, 'payment' => $payment,
                'weekly_hours' => $assignedMinutes / 60,
                'weekly_work_hours' => $weeklyHours,
                'full_salary_cents' => $workSessions ? $calculator->fullSalaryCents($workSessions) : null,
                'weekly_limit' => $weeklyHours ?? ($weeklyLimits->get($teacher->id) ?? $teaching->defaultLimit($teacher->program)) / 60,
                'salary' => $payment ? $payment->only(['base_cents', 'raise_cents', 'total_cents', 'deduction_cents']) : ($profile ? $advanceService->apply(app(\App\Services\EmployeeSalaryDeductions::class)->apply(
                    $calculator->calculate($profile, $asOf, $assignedMinutes), $profile, $attendance->get($teacher->id, collect()),
                    $scans->get($teacher->id, collect()), $overrides->get($teacher->id, collect()), $asOf
                ), $advances->get($teacher->id, collect())) : null),
                'attendance' => app(\App\Services\EmployeeSalaryDeductions::class)->counts($attendance->get($teacher->id, collect()), $scans->get($teacher->id, collect()), $overrides->get($teacher->id, collect()))];
        });

        if ($exporting) {
            return response()->streamDownload(function () use ($rows) {
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Employee', 'Employee ID', 'Weekly hours', 'Present', 'Late', 'Absent', 'Excused', 'Base salary (USD)', 'Increase (USD)', 'Deductions (USD)', 'Net salary (USD)', 'Status'], escape: '');
                $safeText = fn ($value) => preg_match('/^[\s]*[=+@-]|^[\t\r\n]/', $value) ? "'".$value : $value;
                foreach ($rows as $row) {
                    $salary = $row['salary'];
                    fputcsv($output, [
                        $safeText($row['teacher']->name), $safeText($row['teacher']->profile_number ?? ''),
                        $row['weekly_hours'], ...collect(['present', 'late', 'absent', 'excused'])->map(fn ($status) => $row['attendance']->get($status, 0))->all(),
                        $salary ? Money::decimal($salary['base_cents']) : '',
                        $salary ? Money::decimal($salary['raise_cents']) : '',
                        $salary ? Money::decimal($salary['deduction_cents']) : '',
                        $salary ? Money::decimal($salary['total_cents']) : '',
                        $row['payment'] ? 'Paid' : ($row['profile'] ? 'Not recorded' : 'Setup needed'),
                    ], escape: '');
                }
                fclose($output);
            }, 'teacher-payroll-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $teacherCount = $request->filled('search') ? $teacherPopulation->count() : $teachers->total();
        $recorded = EmployeeSalaryPayment::whereIn('teacher_id', $teacherPopulation->select('id'))
            ->whereDate('salary_month', $month)
            ->selectRaw('COUNT(*) AS paid_count, COALESCE(SUM(total_cents), 0) AS paid_cents')
            ->first();

        return view('school.salaries.index', compact('month', 'teachers', 'rows', 'asOf', 'teacherCount', 'recorded') + [
            'salaryHourlyCents' => $calculator->baseCents(60),
            'salaryRates' => collect([5, 10, 20, 40, 50])->mapWithKeys(fn ($hours) => [$hours => $calculator->baseCents($hours * 60)]),
        ]);
    }

    public function teacherReports(Request $request, EmployeeSalaryCalculator $calculator, \App\Services\EmployeeSalaryDeductions $deductions)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:255',
            'report' => 'nullable|in:all,salary,leave',
            'year' => 'nullable|integer|between:1900,9999',
            'teacher' => 'nullable|integer',
            'program' => ['nullable', Rule::in(\App\Models\Program::names())],
        ]);
        $filters['session'] = AttendanceSession::selected($request);
        $filters['report'] ??= 'all';
        $month = $this->month($request);
        if ($filters['report'] === 'leave' && isset($filters['year'])) {
            $selectedYear = (int) $filters['year'];
            $month = today()->setDate($selectedYear, $selectedYear === today()->year ? today()->month : 12, 1);
        }
        $asOf = $this->assessmentDate($month);
        $year = [$month->copy()->startOfYear()->toDateString(), $month->copy()->endOfYear()->toDateString()];
        $teacherOptions = User::where('role', 'teacher')->orderBy('name')->orderBy('id')->get(['id', 'name', 'profile_number']);
        $search = mb_strtolower(trim($filters['search'] ?? ''));
        $teachers = AttendanceSession::teachers(User::where('role', 'teacher'), $filters['session'])
            ->when($filters['report'] === 'leave' && $month->year !== today()->year, function ($query) use ($year, $month) {
                $query->where(function ($teachers) use ($year, $month) {
                    $teachers->whereIn('users.id', TeacherAttendance::whereBetween('date', $year)->select('teacher_id'))
                        ->orWhereIn('users.id', TeacherSessionAttendance::whereBetween('date', $year)->select('teacher_id'))
                        ->orWhereIn('users.id', EmployeeSalaryAttendance::whereBetween('date', $year)->select('teacher_id'))
                        ->orWhereIn('users.id', EmployeeSalaryPayment::whereBetween('salary_month', $year)->select('teacher_id'))
                        ->orWhereIn('users.id', \App\Models\TeacherSessionStop::whereNotNull('applied_at')
                            ->whereNotNull('snapshot')
                            ->whereDate('effective_on', '>=', $month->copy()->startOfYear()->addMonth())
                            ->whereDate('effective_on', '<=', $month->copy()->startOfYear()->addYear())
                            ->select('teacher_id'));
                });
            })
            ->when($filters['teacher'] ?? null, fn ($query, $teacherId) => $query->whereKey($teacherId))
            ->when($filters['program'] ?? null, fn ($query, $program) => $query->where('program', $program))
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']))
            ->orderBy('name')->orderBy('id')->paginate(25)->withQueryString();
        $ids = $teachers->modelKeys();
        $profiles = EmployeeSalaryProfile::whereIn('teacher_id', $ids)->get()->keyBy('teacher_id');
        $payments = EmployeeSalaryPayment::whereIn('teacher_id', $ids)->whereDate('salary_month', $month)->get()->keyBy('teacher_id');
        $daily = TeacherAttendance::whereIn('teacher_id', $ids)->whereBetween('date', $year)->get()->groupBy('teacher_id');
        $sessions = TeacherSessionAttendance::whereIn('teacher_id', $ids)->whereBetween('date', $year)->get()->groupBy('teacher_id');
        $overrides = EmployeeSalaryAttendance::whereIn('teacher_id', $ids)->whereBetween('date', $year)->get()->groupBy('teacher_id');
        $minutes = $calculator->assignedMinutes($ids);
        $bonus = app(EmployeeAttendanceBonus::class);
        $requiredMonths = $bonus->requiredServiceMonths();
        $showLeave = $filters['report'] !== 'salary';
        $bonusTimetables = $showLeave ? $bonus->timetables($ids, $month->year) : collect();
        $bonusPayments = $showLeave ? EmployeeSalaryPayment::whereIn('teacher_id', $ids)->whereBetween('salary_month', $year)
            ->get()->groupBy('teacher_id')->map(fn ($entries) => $entries->keyBy(fn ($entry) => $entry->salary_month->month)) : collect();
        $closedDates = $showLeave ? $bonus->closedDates(app(SchoolHolidayClosure::class)) : [];
        $stops = app(TeacherSessionStops::class);
        $stopHistory = $stops->history($ids);
        $leave = app(TeacherLeaveReport::class);
        $advanceService = app(EmployeeSalaryAdvances::class);
        $advances = $advanceService->outstanding($ids, $asOf->min(today()));
        $reports = $teachers->getCollection()->map(function (User $teacher) use ($month, $asOf, $profiles, $payments, $daily, $sessions, $overrides, $minutes, $calculator, $deductions, $bonus, $showLeave, $bonusTimetables, $bonusPayments, $closedDates, $stops, $stopHistory, $leave, $requiredMonths, $advanceService, $advances) {
            $id = $teacher->id;
            $profile = $profiles->get($id);
            $payment = $payments->get($id);
            $history = $stopHistory->get($id, collect());
            [$salaryProfile, $salaryMinutes] = $stops->payrollAt($history, $month, $profile, $minutes->get($id, 0));
            $byMonth = fn ($record) => $record->date->month;
            $dailyMonths = $daily->get($id, collect())->groupBy($byMonth);
            $sessionMonths = $sessions->get($id, collect())->groupBy($byMonth);
            $overrideMonths = $overrides->get($id, collect())->groupBy($byMonth);
            $marks = [
                $dailyMonths->get($month->month, collect()),
                $sessionMonths->get($month->month, collect()),
                $overrideMonths->get($month->month, collect()),
            ];
            $salary = $salaryProfile && !$payment ? $advanceService->apply($deductions->apply(
                $calculator->calculate($salaryProfile, $asOf, $salaryMinutes), $salaryProfile,
                $marks[0], $marks[1], $marks[2], $asOf,
            ), $advances->get($id, collect())) : null;

            $report = compact('teacher', 'payment', 'salary') + [
                'counts' => $deductions->slipCounts($marks[0], $marks[1], $marks[2], $payment ?? $salaryProfile),
                'work' => $this->reportWork($payment ?? $salaryProfile, $salaryMinutes, $calculator, $asOf, $requiredMonths),
                'leaveMonths' => $showLeave ? $leave->countLeaveMonths($dailyMonths, $sessionMonths, $overrideMonths, $deductions) : collect(),
                'attendanceBonus' => $showLeave ? $bonus->forYear(
                    $id, $month, $profile, $bonusPayments->get($id, collect()), $bonusTimetables->get($id, collect()),
                    $daily->get($id, collect()), $sessions->get($id, collect()), $overrides->get($id, collect()), $closedDates,
                ) : null,
            ];
            if ($showLeave && $history->isNotEmpty()) {
                $report['leaveSegments'] = $leave->segments($this->currentLeaveRow($report, $profile, $history, $month, $minutes->get($id, 0), $calculator), $history, $month);
            }

            return $report;
        });
        $view = view('school.salaries.teacher-reports', compact('month', 'teachers', 'reports', 'filters', 'teacherOptions'));

        $fragments = ['teacher-payroll-reports'];
        if (!$request->ajax() || $request->header('X-Include-Outstanding-Advances') === '1') {
            $view->with('outstandingAdvances', $advanceService->outstandingForReport(today()));
            $fragments[] = 'teacher-outstanding-advances';
        }

        return $request->ajax()
            ? response($view->fragments($fragments))->header('Cache-Control', 'private, no-store')
            : response($view)->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, User $teacher, EmployeeSalaryCalculator $calculator)
    {
        $this->teacher($teacher);
        $month = $this->month($request);
        $asOf = $this->assessmentDate($month);
        $profile = EmployeeSalaryProfile::where('teacher_id', $teacher->id)->first();
        $payment = EmployeeSalaryPayment::where('teacher_id', $teacher->id)->whereDate('salary_month', $month)->first();
        $stops = app(TeacherSessionStops::class);
        $history = $stops->history([$teacher->id])->get($teacher->id, collect());
        $minutes = $calculator->assignedMinutes([$teacher->id])->get($teacher->id, 0);
        [$salaryProfile, $salaryMinutes] = $stops->payrollAt($history, $month, $profile, $minutes);
        $salary = $salaryProfile ? $this->salary($salaryProfile, $month, $calculator, $salaryMinutes) : null;
        $slip = $request->validate(['slip' => 'nullable|in:salary,leave'])['slip'] ?? null;
        $attendance = TeacherAttendance::where('teacher_id', $teacher->id)
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get()->keyBy(fn ($record) => $record->date->toDateString());
        $scans = TeacherSessionAttendance::where('teacher_id', $teacher->id)
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->orderBy('scanned_at')->get()->groupBy(fn ($record) => $record->date->toDateString());
        $overrides = EmployeeSalaryAttendance::where('teacher_id', $teacher->id)->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get()->groupBy(fn ($record) => $record->date->toDateString());
        $counts = app(\App\Services\EmployeeSalaryDeductions::class)->counts($attendance->values(), $scans->flatten(1), $overrides->flatten(1));

        if ($slip) {
            if ($slip === 'salary') {
                $counts = app(\App\Services\EmployeeSalaryDeductions::class)->slipCounts(
                    $attendance->values(), $scans->flatten(1), $overrides->flatten(1), $payment ?? $salaryProfile
                );
            }
            $requiredMonths = app(EmployeeAttendanceBonus::class)->requiredServiceMonths();
            $work = $this->reportWork($payment ?? $salaryProfile, $salaryMinutes, $calculator, $asOf, $requiredMonths);
            $data = compact('slip', 'teacher', 'month', 'profile', 'salary', 'payment', 'counts', 'work');
            if ($slip === 'leave') {
                $leave = app(TeacherLeaveReport::class);
                $data += $leave->forTeacher($teacher, $month, $profile);
                $data['leaveSegments'] = $leave->segments($this->currentLeaveRow($data, $profile, $history, $month, $minutes, $calculator), $history, $month);
                unset($data['attendanceBonus']['monthly']);
            }

            return view('school.salaries.slip', $data);
        }

        $history = EmployeeSalaryPayment::where('teacher_id', $teacher->id)->latest('salary_month')->limit(12)->get();

        return view('school.salaries.show', compact('teacher', 'month', 'profile', 'salary', 'payment', 'history', 'attendance', 'scans', 'asOf', 'overrides', 'counts'));
    }

    private function reportWork(EmployeeSalaryPayment|EmployeeSalaryProfile|null $settings, int $assignedMinutes, EmployeeSalaryCalculator $calculator, Carbon $asOf, int $requiredMonths): array
    {
        $weeklyHours = $settings ? $calculator->weeklyWorkHours($settings->day_sessions, $settings->evening_sessions) : null;

        return [
            'sessions' => $settings ? $settings->day_sessions + $settings->evening_sessions : null,
            'scheduledHours' => round($assignedMinutes / 60, 2),
            'weeklyHours' => $weeklyHours,
            'fullSalaryCents' => $settings ? $calculator->fullSalaryCents($settings) : null,
            'serviceStartedOn' => $settings?->service_started_on,
            'requiredBonusMonths' => $requiredMonths,
            'belowBonusServiceMinimum' => $settings?->service_started_on?->copy()->addMonthsNoOverflow($requiredMonths)->gt($asOf->min(today())) ?? false,
        ];
    }

    private function currentLeaveRow(array $report, ?EmployeeSalaryProfile $profile, \Illuminate\Support\Collection $history, Carbon $month, int $minutes, EmployeeSalaryCalculator $calculator): array
    {
        $effective = $history->last()?->effective_on;
        if (!$effective || $effective->year !== $month->year || !$effective->gt($month)) {
            return $report;
        }

        return array_replace($report, [
            'payment' => null,
            'salary' => $profile ? $calculator->calculate($profile, $effective, $minutes) : null,
            'work' => $this->reportWork($profile, $minutes, $calculator, $effective, $report['work']['requiredBonusMonths']),
        ]);
    }

    public function updateLeaveBonusEligibility(Request $request)
    {
        $data = $request->validate(['bonus_service_months' => 'required|integer|min:1|max:1200']);
        DB::table('teacher_management_settings')->where('id', 1)->update($data);

        return back()->with('success', __('Leave bonus eligibility updated.'));
    }

    public function updateSalaryRates(Request $request, EmployeeSalaryRates $rates)
    {
        $rules = collect(EmployeeSalaryRates::DEFAULTS)->mapWithKeys(
            fn ($amount, $key) => ["rates.$key" => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100000']]
        )->all();
        $data = $request->validateWithBag('salaryRates', $rules + [
            'rates' => 'required|array:'.implode(',', array_keys(EmployeeSalaryRates::DEFAULTS)),
        ]);
        $rates->save(array_map(Money::cents(...), $data['rates']));

        return back()->with('success', __('Salary rates saved.'));
    }

    public function update(Request $request, User $teacher)
    {
        $this->teacher($teacher);
        $data = $request->validate(['day_sessions' => 'required|integer|between:0,2', 'evening_sessions' => 'required|integer|between:0,2',
            'service_started_on' => 'required|date_format:Y-m-d']);
        if ((int) $data['day_sessions'] + (int) $data['evening_sessions'] === 0) {
            throw ValidationException::withMessages(['day_sessions' => 'Choose at least one work session.']);
        }
        EmployeeSalaryProfile::updateOrCreate(['teacher_id' => $teacher->id], $data + ['updated_by' => $request->user()->id]);

        return back()->with('success', 'Salary settings saved. Previously recorded salary payments are unchanged.');
    }

    public function pay(Request $request, User $teacher, EmployeeSalaryCalculator $calculator)
    {
        $this->teacher($teacher);
        $data = $request->validate(['month' => 'required|date_format:Y-m|before_or_equal:'.today()->format('Y-m'),
            'paid_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'reference' => 'nullable|string|max:255', 'confirm' => 'required|accepted',
            'expected_total_cents' => 'required|integer|min:0',
            'expected_advance_cents' => 'nullable|integer|min:0']);
        $month = Carbon::createFromFormat('!Y-m', $data['month']);
        DB::transaction(function () use ($teacher, $calculator, $request, $data, $month) {
            User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $profile = EmployeeSalaryProfile::where('teacher_id', $teacher->id)->lockForUpdate()->first();
            if (! $profile) {
                throw ValidationException::withMessages(['salary' => 'Save the work sessions and service start date first.']);
            }
            if (EmployeeSalaryPayment::where('teacher_id', $teacher->id)->whereDate('salary_month', $month)->exists()) {
                throw ValidationException::withMessages(['salary' => 'Salary payment has already been recorded for this month.']);
            }
            $stops = app(TeacherSessionStops::class);
            [$profile, $minutes] = $stops->payrollAt(
                $stops->history([$teacher->id])->get($teacher->id, collect()), $month, $profile,
                $calculator->assignedMinutes([$teacher->id])->get($teacher->id, 0),
            );
            if (!$profile) {
                throw ValidationException::withMessages(['salary' => 'No salary settings exist for this month.']);
            }
            $paidOn = Carbon::parse($data['paid_on']);
            $advanceService = app(EmployeeSalaryAdvances::class);
            $advances = $advanceService->outstanding([$teacher->id], $this->assessmentDate($month)->min($paidOn))->get($teacher->id, collect());
            $salary = $this->salary($profile, $month, $calculator, $minutes, $advances);
            if (($salary['total_cents'] === 0 && $salary['advance_cents'] === 0) || $paidOn->lt($month) || $paidOn->lt($profile->service_started_on)
                || $salary['total_cents'] !== (int) $data['expected_total_cents']
                || (isset($data['expected_advance_cents']) && $salary['advance_cents'] !== (int) $data['expected_advance_cents'])) {
                throw ValidationException::withMessages(['salary' => 'Check the salary amount and payment date, then refresh before recording payment.']);
            }
            EmployeeSalaryPayment::create(['teacher_id' => $teacher->id, 'salary_month' => $month,
                'day_sessions' => $profile->day_sessions, 'evening_sessions' => $profile->evening_sessions,
                'service_started_on' => $profile->service_started_on,
                'base_cents' => $salary['base_cents'], 'full_salary_cents' => $salary['full_salary_cents'],
                'raise_cents' => $salary['raise_cents'], 'total_cents' => $salary['total_cents'],
                'deduction_cents' => $salary['deduction_cents'], 'deduction_details' => $salary['deduction_details'], 'advance_cents' => $salary['advance_cents'],
                'paid_on' => $data['paid_on'], 'reference' => $data['reference'] ?? null, 'recorded_by' => $request->user()->id]);
            $advanceService->recover($advances, $salary['advance_cents']);
        });

        return back()->with('success', 'Salary payment recorded.');
    }

    public function attendance(Request $request)
    {
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d|before_or_equal:today', 'search' => 'nullable|string|max:255']);
        $date = $data['date'] ?? today()->toDateString();
        $teachers = User::where('role', 'teacher')->when($request->filled('search'), fn ($q) => $q->where(fn ($q) =>
            $q->where('name', 'like', '%'.$request->search.'%')->orWhere('profile_number', 'like', '%'.$request->search.'%')))
            ->orderBy('name')->paginate(20)->withQueryString();
        $daily = TeacherAttendance::whereIn('teacher_id', $teachers->modelKeys())->whereDate('date', $date)->get()->keyBy('teacher_id');
        $scans = TeacherSessionAttendance::whereIn('teacher_id', $teachers->modelKeys())->whereDate('date', $date)->get()->groupBy('teacher_id');
        $overrides = EmployeeSalaryAttendance::whereIn('teacher_id', $teachers->modelKeys())->whereDate('date', $date)->get()->groupBy('teacher_id');

        return view('school.salaries.attendance', compact('teachers', 'date', 'daily', 'scans', 'overrides'));
    }

    public function saveAttendance(Request $request, User $teacher)
    {
        $this->teacher($teacher);
        $data = $request->validate(['date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'session' => 'required|in:All,Morning,Afternoon,Evening', 'status' => 'required|in:present,absent,late,excused',
            'notes' => 'nullable|string|max:1000']);
        app(SchoolHolidayClosure::class)->ensureOpen($data['date']);
        DB::transaction(function () use ($teacher, $data, $request) {
            User::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $month = Carbon::parse($data['date'])->startOfMonth();
            if (EmployeeSalaryPayment::where('teacher_id', $teacher->id)->whereDate('salary_month', $month)->exists()) {
                throw ValidationException::withMessages(['attendance' => 'Salary is already recorded for this month. Its attendance deductions are locked.']);
            }
            EmployeeSalaryAttendance::updateOrCreate(['teacher_id' => $teacher->id, 'date' => $data['date'], 'session' => $data['session']],
                ['status' => $data['status'], 'notes' => $data['notes'] ?? null, 'recorded_by' => $request->user()->id]);
            if ($data['session'] === 'All') {
                EmployeeSalaryAttendance::where('teacher_id', $teacher->id)->whereDate('date', $data['date'])->where('session', '!=', 'All')->delete();
            }
        });

        return back()->with('success', 'Teacher attendance saved. Salary deductions updated.');
    }
}
