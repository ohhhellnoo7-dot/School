<?php

namespace App\Http\Controllers;

use App\Models\{EmployeeSalaryPayment, EmployeeSalaryProfile, TeacherAttendance, TeacherSessionAttendance, User};
use App\Services\EmployeeSalaryCalculator;
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

    public function index(Request $request, EmployeeSalaryCalculator $calculator)
    {
        $month = $this->month($request);
        $asOf = $this->assessmentDate($month);
        $request->validate(['search' => 'nullable|string|max:255']);
        $teachers = User::where('role', 'teacher')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('profile_number', 'like', '%'.$request->search.'%')))
            ->orderBy('name')->paginate(20)->withQueryString();
        $profiles = EmployeeSalaryProfile::whereIn('teacher_id', $teachers->modelKeys())->get()->keyBy('teacher_id');
        $payments = EmployeeSalaryPayment::whereIn('teacher_id', $teachers->modelKeys())->whereDate('salary_month', $month)->get()->keyBy('teacher_id');
        $attendance = TeacherAttendance::whereIn('teacher_id', $teachers->modelKeys())
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get()->groupBy('teacher_id');
        $rows = $teachers->getCollection()->map(function ($teacher) use ($profiles, $payments, $attendance, $calculator, $asOf) {
            $profile = $profiles->get($teacher->id);
            $payment = $payments->get($teacher->id);

            return ['teacher' => $teacher, 'profile' => $profile, 'payment' => $payment,
                'salary' => $payment ? $payment->only(['base_cents', 'raise_cents', 'total_cents']) : ($profile ? $calculator->calculate($profile, $asOf) : null),
                'attendance' => ($attendance->get($teacher->id) ?? collect())->countBy('status')];
        });

        return view('school.salaries.index', compact('month', 'teachers', 'rows', 'asOf'));
    }

    public function show(Request $request, User $teacher, EmployeeSalaryCalculator $calculator)
    {
        $this->teacher($teacher);
        $month = $this->month($request);
        $asOf = $this->assessmentDate($month);
        $profile = EmployeeSalaryProfile::where('teacher_id', $teacher->id)->first();
        $payment = EmployeeSalaryPayment::where('teacher_id', $teacher->id)->whereDate('salary_month', $month)->first();
        $salary = $profile ? $calculator->calculate($profile, $asOf) : null;
        $history = EmployeeSalaryPayment::where('teacher_id', $teacher->id)->latest('salary_month')->limit(12)->get();
        $attendance = TeacherAttendance::where('teacher_id', $teacher->id)
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->get()->keyBy(fn ($record) => $record->date->toDateString());
        $scans = TeacherSessionAttendance::where('teacher_id', $teacher->id)
            ->whereBetween('date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->orderBy('scanned_at')->get()->groupBy(fn ($record) => $record->date->toDateString());

        return view('school.salaries.show', compact('teacher', 'month', 'profile', 'salary', 'payment', 'history', 'attendance', 'scans', 'asOf'));
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
            $salary = $calculator->calculate($profile, $this->assessmentDate($month));
            if ($salary['total_cents'] <= 0 || Carbon::parse($data['paid_on'])->lt($month) || Carbon::parse($data['paid_on'])->lt($profile->service_started_on) || $salary['total_cents'] !== (int) $data['expected_total_cents']) {
                throw ValidationException::withMessages(['salary' => 'Check the salary amount and payment date, then refresh before recording payment.']);
            }
            EmployeeSalaryPayment::create(['teacher_id' => $teacher->id, 'salary_month' => $month,
                'day_sessions' => $profile->day_sessions, 'evening_sessions' => $profile->evening_sessions,
                'service_started_on' => $profile->service_started_on,
                'base_cents' => $salary['base_cents'], 'raise_cents' => $salary['raise_cents'], 'total_cents' => $salary['total_cents'],
                'paid_on' => $data['paid_on'], 'reference' => $data['reference'] ?? null, 'recorded_by' => $request->user()->id]);
        });

        return back()->with('success', 'Salary payment recorded.');
    }
}
