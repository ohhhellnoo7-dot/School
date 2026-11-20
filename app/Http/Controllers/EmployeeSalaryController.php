<?php

namespace App\Http\Controllers;

use App\Models\{EmployeeSalaryAttendance, EmployeeSalaryPayment, EmployeeSalaryProfile, TeacherAttendance, TeacherSessionAttendance, User};
use App\Services\EmployeeSalaryCalculator;
use App\Services\TeacherManagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    private function salary(EmployeeSalaryProfile $profile, Carbon $month, EmployeeSalaryCalculator $calculator): array
    {
        $range = [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()];
        $assignedMinutes = $calculator->assignedMinutes([$profile->teacher_id])->get($profile->teacher_id, 0);

        return app(\App\Services\EmployeeSalaryDeductions::class)->apply(
            $calculator->calculate($profile, $this->assessmentDate($month), $assignedMinutes), $profile,
            TeacherAttendance::where('teacher_id', $profile->teacher_id)->whereBetween('date', $range)->get(),
            TeacherSessionAttendance::where('teacher_id', $profile->teacher_id)->whereBetween('date', $range)->get(),
            EmployeeSalaryAttendance::where('teacher_id', $profile->teacher_id)->whereBetween('date', $range)->get(),
            $this->assessmentDate($month)
        );
    }

    public function index(Request $request, EmployeeSalaryCalculator $calculator, TeacherManagementService $teaching)
    {
        $month = $this->month($request);
        $asOf = $this->assessmentDate($month);
        $request->validate(['search' => 'nullable|string|max:255']);
        $teacherPopulation = User::where('role', 'teacher');
        $teachers = (clone $teacherPopulation)
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q
                ->whereRaw('LOWER(name) LIKE ?', [mb_strtolower($request->search).'%'])
                ->orWhere('profile_number', 'like', '%'.$request->search.'%')))
            ->orderBy('name')->paginate(20)->withQueryString();
        $profiles = EmployeeSalaryProfile::whereIn('teacher_id', $teachers->modelKeys())->get()->keyBy('teacher_id');
        $payments = EmployeeSalaryPayment::whereIn('teacher_id', $teachers->modelKeys())->whereDate('salary_month', $month)->get()->keyBy('teacher_id');
        $attendance = TeacherAttendance::whereIn('teacher_id', $teachers->modelKeys())
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get()->groupBy('teacher_id');
        $range = [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()];
        $scans = TeacherSessionAttendance::whereIn('teacher_id', $teachers->modelKeys())->whereBetween('date', $range)->get()->groupBy('teacher_id');
        $overrides = EmployeeSalaryAttendance::whereIn('teacher_id', $teachers->modelKeys())->whereBetween('date', $range)->get()->groupBy('teacher_id');
        $teachingMinutes = $calculator->assignedMinutes($teachers->modelKeys());
        $weeklyLimits = DB::table('teacher_profiles')->whereIn('teacher_id', $teachers->modelKeys())->pluck('weekly_minutes', 'teacher_id');
        $rows = $teachers->getCollection()->map(function ($teacher) use ($profiles, $payments, $attendance, $calculator, $asOf, $scans, $overrides, $teaching, $teachingMinutes, $weeklyLimits) {
            $profile = $profiles->get($teacher->id);
            $payment = $payments->get($teacher->id);
            $assignedMinutes = $teachingMinutes->get($teacher->id, 0);
            $workSessions = $payment ?? $profile;
            $weeklyHours = $workSessions
                ? 5 * (4 * $workSessions->day_sessions + $workSessions->evening_sessions)
                : null;

            return ['teacher' => $teacher, 'profile' => $profile, 'payment' => $payment,
                'weekly_hours' => $assignedMinutes / 60,
                'weekly_work_hours' => $weeklyHours,
                'full_salary_cents' => $weeklyHours === null ? null : $calculator->baseCents($weeklyHours * 60),
                'weekly_limit' => $weeklyHours ?? ($weeklyLimits->get($teacher->id) ?? $teaching->defaultLimit($teacher->program)) / 60,
                'salary' => $payment ? $payment->only(['base_cents', 'raise_cents', 'total_cents', 'deduction_cents']) : ($profile ? app(\App\Services\EmployeeSalaryDeductions::class)->apply(
                    $calculator->calculate($profile, $asOf, $assignedMinutes), $profile, $attendance->get($teacher->id, collect()),
                    $scans->get($teacher->id, collect()), $overrides->get($teacher->id, collect()), $asOf
                ) : null),
                'attendance' => app(\App\Services\EmployeeSalaryDeductions::class)->counts($attendance->get($teacher->id, collect()), $scans->get($teacher->id, collect()), $overrides->get($teacher->id, collect()))];
        });

        $teacherCount = $request->filled('search') ? $teacherPopulation->count() : $teachers->total();
        $recorded = EmployeeSalaryPayment::whereIn('teacher_id', $teacherPopulation->select('id'))
            ->whereDate('salary_month', $month)
            ->selectRaw('COUNT(*) AS paid_count, COALESCE(SUM(total_cents), 0) AS paid_cents')
            ->first();

        return view('school.salaries.index', compact('month', 'teachers', 'rows', 'asOf', 'teacherCount', 'recorded') + [
            'salaryHourlyCents' => $calculator->baseCents(60),
        ]);
    }

    public function show(Request $request, User $teacher, EmployeeSalaryCalculator $calculator)
    {
        $this->teacher($teacher);
        $month = $this->month($request);
        $asOf = $this->assessmentDate($month);
        $profile = EmployeeSalaryProfile::where('teacher_id', $teacher->id)->first();
        $payment = EmployeeSalaryPayment::where('teacher_id', $teacher->id)->whereDate('salary_month', $month)->first();
        $salary = $profile ? $this->salary($profile, $month, $calculator) : null;
        $history = EmployeeSalaryPayment::where('teacher_id', $teacher->id)->latest('salary_month')->limit(12)->get();
        $attendance = TeacherAttendance::where('teacher_id', $teacher->id)
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get()->keyBy(fn ($record) => $record->date->toDateString());
        $scans = TeacherSessionAttendance::where('teacher_id', $teacher->id)
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->orderBy('scanned_at')->get()->groupBy(fn ($record) => $record->date->toDateString());
        $overrides = EmployeeSalaryAttendance::where('teacher_id', $teacher->id)->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get()->groupBy(fn ($record) => $record->date->toDateString());
        $counts = app(\App\Services\EmployeeSalaryDeductions::class)->counts($attendance->values(), $scans->flatten(1), $overrides->flatten(1));

        return view('school.salaries.show', compact('teacher', 'month', 'profile', 'salary', 'payment', 'history', 'attendance', 'scans', 'asOf', 'overrides', 'counts'));
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
            'expected_total_cents' => 'required|integer|min:1']);
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
            $salary = $this->salary($profile, $month, $calculator);
            if ($salary['total_cents'] <= 0 || Carbon::parse($data['paid_on'])->lt($month) || Carbon::parse($data['paid_on'])->lt($profile->service_started_on) || $salary['total_cents'] !== (int) $data['expected_total_cents']) {
                throw ValidationException::withMessages(['salary' => 'Check the salary amount and payment date, then refresh before recording payment.']);
            }
            EmployeeSalaryPayment::create(['teacher_id' => $teacher->id, 'salary_month' => $month,
                'day_sessions' => $profile->day_sessions, 'evening_sessions' => $profile->evening_sessions,
                'service_started_on' => $profile->service_started_on,
                'base_cents' => $salary['base_cents'], 'raise_cents' => $salary['raise_cents'], 'total_cents' => $salary['total_cents'],
                'deduction_cents' => $salary['deduction_cents'], 'deduction_details' => $salary['deduction_details'],
                'paid_on' => $data['paid_on'], 'reference' => $data['reference'] ?? null, 'recorded_by' => $request->user()->id]);
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
