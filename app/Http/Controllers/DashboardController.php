<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\ClassGroup;
use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolModules;
use App\Support\AttendanceSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function randomAttendance(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $session = AttendanceSession::selected($request);
        $counts = DB::transaction(function () use ($session) {
            $students = AttendanceSession::students(Student::query(), $session)->pluck('id')->shuffle()->values();
            $total = $students->count();
            $present = (int) round($total * 0.7);
            $late = min($total - $present, (int) round($total * 0.15));
            $absent = min($total - $present - $late, (int) round($total * 0.1));
            $date = today()->toDateString();
            $timestamp = now();
            $students->map(fn ($id, $index) => [
                'student_id' => $id, 'date' => $date,
                'status' => $index < $present ? 'present' : ($index < $present + $late ? 'late' : ($index < $present + $late + $absent ? 'absent' : 'excused')),
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ])->chunk(100)->each(fn ($rows) => Attendance::upsert($rows->all(), ['student_id', 'date'], ['status', 'updated_at']));

            return ['present' => $present, 'late' => $late, 'absent' => $absent, 'excused' => $total - $present - $late - $absent];
        });

        return redirect()->route('dashboard', array_filter(['session' => $session]))->with('success', "Random attendance saved for today: {$counts['present']} present, {$counts['late']} late, {$counts['absent']} absent, {$counts['excused']} excused.");
    }

    public function markAllPresent(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $session = AttendanceSession::selected($request);
        $count = DB::transaction(function () use ($session) {
            $date = today()->toDateString();
            $students = AttendanceSession::students(Student::query(), $session);
            $count = (clone $students)->count();
            Attendance::whereIn('student_id', (clone $students)->select('id'))->whereDate('date', $date)
                ->whereNotIn('status', ['present', 'late'])->update(['status' => 'present', 'updated_at' => now()]);
            (clone $students)->whereDoesntHave('attendances', fn ($query) => $query->whereDate('date', $date))
                ->select('id')->chunkById(500, function ($students) use ($date) {
                    $now = now();
                    $rows = $students->map(fn ($student) => [
                        'student_id' => $student->id, 'date' => $date, 'status' => 'present',
                        'created_at' => $now, 'updated_at' => $now,
                    ])->all();
                    Attendance::upsert($rows, ['student_id', 'date'], ['status', 'updated_at']);
                });

            return $count;
        });

        return redirect()->route('dashboard', array_filter(['session' => $session]))->with('success', "Attendance saved for {$count} students today. Existing present and late records preserved.");
    }

    public function __invoke(Request $request)
    {
        return view('school.dashboard', $this->dashboardData($request));
    }

    public function data(Request $request)
    {
        $data = $this->dashboardData($request);

        return response()->json([
            'html' => view('school.partials.dashboard-content', $data)->render(),
            'charts' => $data['charts'],
            'showRevenue' => in_array($request->user()->role, ['administrator', 'parent'], true),
            'summary' => collect($data)->only(['studentCount', 'teacherCount', 'classCount', 'subjectCount', 'presentCount', 'absentCount', 'lateCount', 'attendanceCount', 'collected', 'pending']),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function dashboardData(Request $request): array
    {
        $user = $request->user();
        $validated = $request->validate(['date' => 'nullable|date_format:Y-m-d']);
        $reportDate = Carbon::parse($validated['date'] ?? today()->toDateString())->startOfDay();
        $students = Student::visibleTo($user);
        $attendanceSession = AttendanceSession::selected($request);
        $attendanceStudents = AttendanceSession::students(clone $students, $attendanceSession);
        $ids = (clone $attendanceStudents)->select('id');
        $attendance = Attendance::whereIn('student_id', $ids)->whereDate('date', $reportDate);
        $fees = Fee::whereIn('student_id', (clone $students)->select('id'))->where('currency', 'USD');
        $canViewFees = in_array($user->role, ['administrator', 'parent'], true);
        $finance = $canViewFees ? (app(\App\Services\SchoolFinanceService::class)->summary((clone $fees)->with(['adjustments', 'payments'])->get())['USD'] ?? []) : [];
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $dashboardAttendance = $user->role === 'administrator'
            ? app(TeacherAttendanceController::class)->data($request->duplicate(['attendance_date' => $reportDate->toDateString(), 'attendance_session' => $attendanceSession]), true)
            : null;
        $teacherAttendanceCounts = $dashboardAttendance['counts'] ?? collect();
        $teacherAttendanceTotal = $dashboardAttendance['total'] ?? 0;
        $charts = [
            'labels' => $months->map(fn ($date) => $date->format('M'))->values(),
            'enrollment' => $months->map(fn ($date) => (clone $students)->whereBetween('enrollment_date', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])->count())->values(),
            // Chart conversion only; ledger arithmetic uses integer minor units.
            'revenue' => $canViewFees ? $months->map(fn ($date) => ($finance['trend'][$date->format('Y-m')] ?? 0) / 100)->values() : [],
            'gender' => collect(['female', 'male', 'other'])->map(fn ($gender) => (clone $students)->where('gender', $gender)->count()),
            'teacherAttendance' => collect(['present', 'absent', 'late', 'excused'])->map(fn ($status) => (int) ($teacherAttendanceCounts[$status] ?? 0))->values(),
            'attendance' => collect(['present', 'absent', 'late', 'excused'])->map(fn ($status) => (clone $attendance)->where('status', $status)->count()),
        ];

        $programBreakdowns = [];
        $normalizedClassCount = ClassGroup::query()->count();
        if ($user->role === 'administrator') {
            foreach (\App\Models\Program::names() as $program) {
                $programBreakdowns['people'][$program] = (clone $students)
                    ->whereHas('studyClasses', fn ($q) => $q->where('program', $program))->count();
                $programBreakdowns['building'][$program] = $normalizedClassCount > 0
                    ? ClassGroup::where('program', $program)->count()
                    : SchoolClass::where('program', $program)
                        ->select('name', 'section')->distinct()->get()->count();
                $programBreakdowns['book'][$program] = SchoolModules::subjects($user)
                    ->whereHas('schoolClass', fn ($q) => $q->where('program', $program))->distinct()->count('name');
            }
            $teacherCounts = User::where('role', 'teacher')->selectRaw('program, COUNT(*) as total')
                ->groupBy('program')->pluck('total', 'program');
            $programBreakdowns['person-workspace'] = array_fill_keys(\App\Models\Program::names(), 0);
            foreach ($teacherCounts as $program => $count) {
                $label = trim((string) $program) !== '' ? $program : 'Unassigned';
                $programBreakdowns['person-workspace'][$label] = (int) $count;
            }
        }

        $activeTeacherIds = $user->role === 'administrator' && $reportDate->isToday()
            ? AttendanceSession::teachers(User::where('role', 'teacher'), $attendanceSession)->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))->pluck('id')
            : collect();

        return [
            'attendanceSession' => $attendanceSession,
            'attendanceStudentCount' => (clone $attendanceStudents)->count(),
            'activeTeacherCount' => $activeTeacherIds->count(),
            'teacherRecordedCount' => $activeTeacherIds->isEmpty() ? 0 : \App\Models\TeacherAttendance::whereDate('date', $reportDate)->whereIn('teacher_id', $activeTeacherIds)->count(),
            'parentChildNames' => $user->role === 'parent' ? (clone $students)->limit(2)->pluck('name') : collect(),
            'reportDate' => $reportDate,
            'programBreakdowns' => $programBreakdowns,
            'todayClasses' => $user->role === 'student' ? SchoolModules::query('schedules', $user)->with('subject')->where('day', today()->format('l'))->orderBy('starts_at')->get() : collect(),
            'upcomingAssignments' => $user->role === 'student' ? SchoolModules::query('assignments', $user)->with('subject')->whereDate('due_date', '>=', today())->orderBy('due_date')->orderBy('id')->limit(5)->get() : collect(),
            'upcomingExams' => $user->role === 'student' ? SchoolModules::query('examinations', $user)->with('subject')->whereDate('date', '>=', today())->orderBy('date')->orderBy('id')->limit(3)->get() : collect(),
            'studentCount' => (clone $students)->count(),
            'teacherCount' => $user->role === 'administrator' ? User::where('role', 'teacher')->count() : SchoolModules::subjects($user)->whereNotNull('teacher_id')->distinct()->count('teacher_id'),
            // Normalized class groups count each academic identity once even
            // when the legacy cohort records span several study sessions.
            'classCount' => $user->role === 'administrator' && $normalizedClassCount > 0
                ? $normalizedClassCount
                : ($user->role === 'administrator'
                    ? SchoolClass::query()
                    : ($user->role === 'teacher'
                        ? SchoolModules::query('classes', $user)
                        : SchoolClass::enrolledFor($user)))
                    ->select('name', 'section')->distinct()->get()->count(),
            'subjectCount' => SchoolModules::subjects($user)->distinct()->count('name'),
            'presentCount' => (clone $attendance)->where('status', 'present')->count(),
            'absentCount' => (clone $attendance)->where('status', 'absent')->count(),
            'lateCount' => (clone $attendance)->where('status', 'late')->count(),
            'attendanceCount' => (clone $attendance)->count(), 'collected' => $canViewFees ? \App\Support\Money::decimal($finance['paid'] ?? 0) : null,
            'pending' => $canViewFees ? \App\Support\Money::decimal($finance['balance'] ?? 0) : null,
            'recent' => (clone $students)->with(['schoolClass', 'user', 'parent'])->latest()->latest('id')->limit(5)->get(),
            'announcements' => Announcement::whereIn('audience', ['all', $user->role])->whereDate('published_at', '<=', today())->latest('published_at')->limit(4)->get(),
            'teacherAttendanceCounts' => $teacherAttendanceCounts,
            'teacherAttendanceTotal' => $teacherAttendanceTotal,
            'dashboardAttendance' => $dashboardAttendance,
            'charts' => $charts,
        ];
    }

    public function attendancePeople(Request $request, string $kind, string $status)
    {
        $date = $request->validate(['date' => 'nullable|date_format:Y-m-d'])['date'] ?? today()->toDateString();
        $session = AttendanceSession::selected($request);
        $user = $request->user();
        $filters = $request->validate([
            'program' => ['nullable', \Illuminate\Validation\Rule::in(\App\Models\Program::names())],
            'search' => 'nullable|string|max:100',
            'name_part' => ['nullable', \Illuminate\Validation\Rule::in(['first', 'last'])],
            'letter' => ['nullable', 'regex:/^[A-Z]$/'],
        ]);
        // Names are stored as one field; last-name search uses its final word.
        $nameExpression = ($filters['name_part'] ?? 'first') === 'last'
            ? match (\Illuminate\Support\Facades\DB::connection()->getDriverName()) {
                'sqlite' => "SUBSTR(TRIM(name), LENGTH(RTRIM(TRIM(name), REPLACE(TRIM(name), ' ', ''))) + 1)",
                'pgsql' => "REVERSE(SPLIT_PART(REVERSE(TRIM(name)), ' ', 1))",
                default => "SUBSTRING_INDEX(TRIM(name), ' ', -1)",
            }
            : 'LTRIM(name)';
        $nameMatch = fn ($query, $search) => $query->whereRaw('LOWER('.$nameExpression.') LIKE ?', [mb_strtolower(trim($search)).'%']);
        if ($kind === 'teachers') {
            abort_unless(in_array($user->role, ['administrator', 'teacher'], true), 403);
            $teachers = AttendanceSession::teachers(User::where('role', 'teacher'), $session)
                ->when($user->role === 'teacher', fn ($q) => $q->whereKey($user->id));
            $records = \App\Models\TeacherAttendance::with('teacher')->whereIn('teacher_id', $teachers->select('users.id'))
                ->when($filters['program'] ?? null, fn ($q, $program) => $q->where('program', $program))
                ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas('teacher', fn ($teacher) => $nameMatch($teacher, $search)));
        } else {
            $students = AttendanceSession::students(Student::visibleTo($user), $session)
                ->when($filters['program'] ?? null, fn ($q, $program) => $q->whereHas('studyClasses', fn ($classes) => $classes->where('program', $program)))
                ->when($filters['search'] ?? null, $nameMatch)
                ->when($filters['letter'] ?? null, fn ($q, $letter) => $q->whereRaw('UPPER(LTRIM(name)) LIKE ?', [$letter.'%']));
            $records = Attendance::with('student.schoolClass')->whereIn('student_id', $students->select('id'));
        }
        $records = $records->whereDate('date', $date)->where('status', $status)->orderBy('id')->paginate(50)->withQueryString();
        return view('school.attendance.people', compact('records', 'kind', 'status', 'date', 'session'));
    }

    public function reports(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $classes = SchoolClass::withCount('students')->with('teacher')->get();
        $date = $request->validate(['date' => 'nullable|date'])['date'] ?? today()->toDateString();
        $attendance = Attendance::whereDate('date', $date)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $reportFilters = $request->validate(['academic_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/']]);
        $reportYears = Fee::whereNotNull('academic_year')->distinct()->orderByDesc('academic_year')->pluck('academic_year');
        $reportFees = Fee::with(['adjustments', 'payments', 'items'])->when($reportFilters['academic_year'] ?? null, fn ($q, $year) => $q->where('academic_year', $year))->get();
        $summary = app(\App\Services\SchoolFinanceService::class)->summary($reportFees);
        $reportDetails = $reportFees->groupBy('currency')->map(function ($fees) {
            return [
                'statuses' => ['paid' => $fees->filter(fn ($fee) => in_array($fee->payment_status, ['paid', 'waived']))->count(), 'partial' => $fees->filter(fn ($fee) => $fee->balanceCents() > 0 && \App\Support\Money::cents($fee->paid) > 0)->count(), 'unpaid' => $fees->filter(fn ($fee) => $fee->balanceCents() > 0 && \App\Support\Money::cents($fee->paid) === 0)->count()],
                'outstanding' => $fees->groupBy(fn ($fee) => $fee->program ?: 'Legacy / unclassified')->map(fn ($group) => $group->sum(fn ($fee) => max(0, $fee->balanceCents()))),
            ];
        });

        $studentsByProgram = collect(['Khmer', 'Chinese', 'English', 'Japan'])->merge(\App\Models\Program::names())->unique()->mapWithKeys(fn ($program) => [$program => Student::where('status', 'active')->whereDate('enrollment_date', '<=', today())->whereHas('studyClasses', fn ($q) => $q->where('program', $program))->count()]);

        $cashReport = app(\App\Services\SchoolCashReport::class)->month($date);
        $dailyExpenses = \App\Models\SchoolExpense::with('recorder')->whereDate('spent_on', $date)->latest('id')->get();
        return view('school.reports', compact('classes', 'date', 'attendance', 'summary', 'studentsByProgram', 'reportDetails', 'reportYears', 'cashReport', 'dailyExpenses'));
    }
}
