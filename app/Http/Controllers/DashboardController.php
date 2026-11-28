<?php

namespace App\Http\Controllers;

use App\Services\SchoolHolidayClosure;
use App\Services\TeachingAttendance;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\ClassAttendance;
use App\Models\ClassGroup;
use App\Models\Fee;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentExcuse;
use App\Models\User;
use App\Services\FamilyAnnouncementNotices;
use App\Services\EmployeeSalaryAdvances;
use App\Services\MonthlyStudentAttendance;
use App\Services\TuitionPaymentCoverage;
use App\Support\SchoolModules;
use App\Support\AttendanceSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function teachingSchedule(Request $request, TeachingAttendance $attendance, SchoolHolidayClosure $holidays)
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $date = Carbon::parse($data['date']);
        abort_if($date->isWeekend() || $holidays->calendarHolidayKey($date), 404);

        return response()->view('school.partials.today-teaching-periods', [
            'teachingScheduleDate' => $date,
            'todayTeachingPeriods' => $attendance->periods($request->user(), $date)->get(),
            'todayTeacherExcuse' => null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function saveTeachingAttendance(Request $request, int $schedule, Student $student, TeachingAttendance $attendance)
    {
        $data = $request->validate([
            'attendance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.today()->toDateString()],
            'status' => ['required', 'in:'.implode(',', TeachingAttendance::STATUSES)],
        ]);
        $date = Carbon::parse($data['attendance_date']);
        app(SchoolHolidayClosure::class)->ensureOpen($date);
        $period = $attendance->periods($request->user(), $date)->findOrFail($schedule);
        $attendance->save($request->user(), $period, $student, $data['attendance_date'], $data['status']);

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->to(route('dashboard', [
            'class_attendance_date' => $data['attendance_date'],
            'class_attendance_period' => $period->id,
        ]).'#class-attendance-roster')->with('success', __('Class attendance saved.'));
    }

    private function teachingAttendanceData(Request $request, $today, bool $teachingHoliday): array
    {
        if ($request->user()->role !== 'teacher') {
            return ['todayTeachingPeriods' => collect(), 'todayTeacherExcuse' => null];
        }

        $attendance = app(TeachingAttendance::class);
        $filters = $request->validate([
            'class_attendance_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:'.$today->toDateString()],
            'class_attendance_period' => ['nullable', 'integer'],
        ]);
        $date = isset($filters['class_attendance_date']) ? Carbon::parse($filters['class_attendance_date']) : $today;
        $todayPeriods = $teachingHoliday ? collect() : $attendance->periods($request->user(), $today)->get();
        $todayTeacherExcuse = \App\Models\TeacherAttendance::excusedOn($today->toDateString())
            ->where('teacher_id', $request->user()->id)->first();
        $periods = $date->isSameDay($today) ? $todayPeriods : (
            app(SchoolHolidayClosure::class)->holiday($date) ? collect() : $attendance->periods($request->user(), $date)->get()
        );
        $periods = $periods->filter(fn (Schedule $period) => $period->schoolClass || $period->subject?->schoolClass);
        $time = now()->format('H:i:s');
        $current = $date->isSameDay($today) && ! $todayTeacherExcuse ? $periods->first(
            fn (Schedule $period) => str_pad($period->starts_at, 8, ':00') <= $time
                && $time < str_pad($period->ends_at, 8, ':00')
        ) : null;
        $period = $request->filled('class_attendance_period')
            ? $periods->firstWhere('id', $request->integer('class_attendance_period'))
            : ($current ?? ($date->isSameDay($today) ? null : $periods->first()));
        $students = $period ? $attendance->roster($period)->with('user:id,gender,profile_photo_path')
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'student_number', 'gender', 'photo', 'user_id']) : collect();
        $profileIds = $students->isEmpty() ? collect() : Student::visibleTo($request->user())
            ->whereKey($students->modelKeys())->pluck('id')->flip();
        $dateString = $date->toDateString();

        return [
            'todayTeachingPeriods' => $todayPeriods,
            'todayTeacherExcuse' => $todayTeacherExcuse,
            'classAttendanceDate' => $dateString,
            'classAttendancePeriods' => $periods,
            'classAttendancePeriod' => $period,
            'classAttendanceStudents' => $students,
            'classAttendanceProfileIds' => $profileIds,
            'classAttendanceStatuses' => TeachingAttendance::STATUSES,
            'classAttendanceRecords' => $period ? $attendance->records($period, $students, $dateString) : collect(),
            'classAttendanceExcuses' => $period && ! $date->isWeekend()
                ? $attendance->excuses($period, $dateString)->whereIn('student_id', $students->modelKeys())->pluck('student_id')->flip()
                : collect(),
        ];
    }
    public function randomAttendance(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $session = AttendanceSession::selected($request);
        app(SchoolHolidayClosure::class)->ensureOpen(today());
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

            $students->chunk(500)->each(fn ($ids) => StudentExcuse::where('session', StudentExcuse::RANDOM_SESSION)
                ->where('starts_on', $date)->whereIn('student_id', $ids->all())->delete());
            $reasons = collect(StudentExcuse::RANDOM_REASONS)->shuffle()->values();
            $students->slice($present + $late + $absent)->values()->map(fn ($id, $index) => [
                'student_id' => $id,
                'session' => StudentExcuse::RANDOM_SESSION,
                'starts_on' => $date,
                'ends_on' => $date,
                'reason' => $reasons[$index % $reasons->count()],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])->chunk(100)->each(fn ($rows) => StudentExcuse::insert($rows->all()));

            return ['present' => $present, 'late' => $late, 'absent' => $absent, 'excused' => $total - $present - $late - $absent];
        }, 3);

        return redirect()->route('dashboard', array_filter(['session' => $session]))->with('success', "Random attendance saved for today: {$counts['present']} present, {$counts['late']} late, {$counts['absent']} absent, {$counts['excused']} excused.");
    }

    public function markAllPresent(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $session = AttendanceSession::selected($request);
        app(SchoolHolidayClosure::class)->ensureOpen(today());
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
        if ($request->user()->role === 'student') {
            return app(StudentWorkspaceController::class)->show($request);
        }

        $data = $this->dashboardData($request);
        if ($request->user()->role === 'teacher') {
            $data['earlySalaryMonth'] = today()->startOfMonth()->addMonth();
            $data['earlySalaryCents'] = app(EmployeeSalaryAdvances::class)->availableEarlySalary($request->user()->id, $data['earlySalaryMonth']);
        }

        return view('school.dashboard', $data);
    }

    public function data(Request $request)
    {
        if ($request->user()->role === 'student') {
            return app(StudentWorkspaceController::class)->show($request, fragment: true);
        }

        $data = $this->dashboardData($request);

        return response()->json([
            'html' => view('school.partials.dashboard-content', $data)->render(),
            'charts' => $data['charts'],
            'showRevenue' => $request->user()->role === 'administrator',
            'summary' => collect($data)->only(['studentCount', 'teacherCount', 'classCount', 'subjectCount', 'presentCount', 'absentCount', 'lateCount', 'attendanceCount', 'collected', 'pending']),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function attendanceHistory(Request $request)
    {
        $validated = $request->validate([
            'week' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'attendance_scope' => 'nullable|in:students,children',
        ]);
        $weekStart = Carbon::parse($validated['week'] ?? today()->toDateString())->startOfWeek(Carbon::MONDAY);
        $attendanceScope = $validated['attendance_scope'] ?? 'students';
        $records = $this->attendanceMessages($request->user(), $weekStart, $attendanceScope);

        return response()->view('school.attendance.weekly-history', compact('weekStart', 'records', 'attendanceScope'))
            ->header('Cache-Control', 'no-store, private');
    }

    private function attendanceMessages(User $teacher, Carbon $weekStart, string $scope): \Illuminate\Support\Collection
    {
        $weekEnd = $weekStart->copy()->addDays(4)->min(today());
        $from = $weekStart->toDateString();
        $until = $weekEnd->copy()->addDay()->toDateString();
        $students = $scope === 'children'
            ? $teacher->children()->select('students.id')
            : Student::visibleForAttendance($teacher)->select('students.id');
        $daily = Attendance::with('student:id,name')->whereIn('student_id', clone $students)
            ->where('date', '>=', $from)->where('date', '<', $until)->whereIn('status', MonthlyStudentAttendance::STATUSES)->get();
        $classRecords = ClassAttendance::with(['student:id,name', 'schoolClass.classGroup'])
            ->whereIn('student_id', $students)
            ->when($scope === 'students', fn ($records) => $records->whereIn('school_class_id', SchoolClass::scheduledForTeacher($teacher)->select('school_classes.id')))
            ->where('date', '>=', $from)->where('date', '<', $until)->whereIn('status', MonthlyStudentAttendance::STATUSES)->get();

        return $daily->toBase()->concat($classRecords)
            ->sortBy([['date', 'desc'], ['updated_at', 'desc'], ['id', 'desc']])->values();
    }

    private function dashboardData(Request $request): array
    {
        $user = $request->user();
        $isParent = $user->role === 'parent';
        $validated = $request->validate(['date' => 'nullable|date_format:Y-m-d', 'attendance_scope' => 'nullable|in:students,children']);
        $attendanceScope = $validated['attendance_scope'] ?? 'students';
        $today = today();
        $teachingHoliday = $user->role === 'teacher' ? app(SchoolHolidayClosure::class)->calendarHolidayKey($today) : null;
        $attendanceWeekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
        $reportDate = Carbon::parse($validated['date'] ?? today()->toDateString())->startOfDay();
        $students = Student::visibleTo($user);
        $dashboardChildren = $isParent ? (clone $students)->with(['schoolClass.classGroup', 'studyClasses', 'user', 'fees.adjustments'])->orderBy('name')->orderBy('id')->get() : collect();
        $familyFeeTotals = app(TuitionPaymentCoverage::class)->feeTotals($dashboardChildren);
        $familyNotices = $isParent ? app(FamilyAnnouncementNotices::class) : null;
        $noticeDate = \Carbon\CarbonImmutable::today('Asia/Phnom_Penh');
        $attendanceSession = AttendanceSession::selected($request);
        $monthlyAttendance = app(MonthlyStudentAttendance::class)->dashboard($request);
        $attendanceStudents = AttendanceSession::students(Student::visibleForAttendance($user), $attendanceSession);
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
            'enrollment' => $isParent ? [] : $months->map(fn ($date) => (clone $students)->whereBetween('enrollment_date', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])->count())->values(),
            // Chart conversion only; ledger arithmetic uses integer minor units.
            'revenue' => $user->role === 'administrator' ? $months->map(fn ($date) => ($finance['trend'][$date->format('Y-m')] ?? 0) / 100)->values() : [],
            'gender' => $isParent ? [] : collect(['female', 'male', 'other'])->map(fn ($gender) => (clone $students)->where('gender', $gender)->count()),
            'teacherAttendance' => collect(['present', 'absent', 'late', 'excused'])->map(fn ($status) => (int) ($teacherAttendanceCounts[$status] ?? 0))->values(),
            'attendance' => collect(['present', 'absent', 'late', 'excused'])->map(fn ($status) => (clone $attendance)->where('status', $status)->count()),
            'monthlyAttendance' => $monthlyAttendance['days'],
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
            'attendanceWeekStart' => $attendanceWeekStart,
            'attendanceScope' => $attendanceScope,
            'attendanceMessages' => $user->role === 'teacher' ? $this->attendanceMessages($user, $attendanceWeekStart, $attendanceScope) : collect(),
            'monthlyAttendance' => $monthlyAttendance,
            'attendanceStudentCount' => (clone $attendanceStudents)->count(),
            'activeTeacherCount' => $activeTeacherIds->count(),
            'teacherRecordedCount' => $activeTeacherIds->isEmpty() ? 0 : \App\Models\TeacherAttendance::whereDate('date', $reportDate)->whereIn('teacher_id', $activeTeacherIds)->count(),
            'parentChildNames' => $user->role === 'parent' ? (clone $students)->limit(2)->pluck('name') : collect(),
            'staffChildCount' => in_array($user->role, User::STAFF_ROLES, true) ? $user->children()->count() : 0,
            'reportDate' => $reportDate,
            'programBreakdowns' => $programBreakdowns,
            'todayClasses' => $user->role === 'student' && ! app(SchoolHolidayClosure::class)->holiday(today()) ? SchoolModules::query('schedules', $user)->with('subject')->where('day', today()->format('l'))->orderBy('starts_at')->get() : collect(),
            'teachingHoliday' => $teachingHoliday,
            'teachingHolidayArtwork' => match ($teachingHoliday) {
                null => [],
                'labour_and_visak' => ['labour', 'visak'],
                default => [$teachingHoliday],
            },
            ...$this->teachingAttendanceData($request, $today, (bool) $teachingHoliday),
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
            'recent' => $isParent ? collect() : (clone $students)->with(['schoolClass', 'user', 'parent'])->latest()->latest('id')->limit(5)->get(),
            'dashboardChildren' => $dashboardChildren,
            'familyFeeTotals' => $familyFeeTotals,
            'paymentToday' => $today,
            'familyHolidayNotices' => $familyNotices?->holidays($noticeDate) ?? [],
            'familyAttendanceNotices' => $familyNotices?->attendance($dashboardChildren, $noticeDate) ?? collect(),
            'paymentCoverage' => $dashboardChildren->isNotEmpty()
                ? app(TuitionPaymentCoverage::class)->forChildren($dashboardChildren, $today->toImmutable())
                : collect(),
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
            $students = AttendanceSession::students(Student::visibleForAttendance($user), $session)
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
        if ($request->user()->role === 'teacher') {
            return redirect()->route('teacher-attendance.monthly', $request->only(['date', 'month', 'session']));
        }

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
        $withdrawalTotals = app(EmployeeSalaryController::class)->withdrawalTotals($cashReport['month']);
        $dailyExpenses = \App\Models\SchoolExpense::with('recorder')->whereDate('spent_on', $date)->latest('id')->get();
        return view('school.reports', compact('classes', 'date', 'attendance', 'summary', 'studentsByProgram', 'reportDetails', 'reportYears', 'cashReport', 'dailyExpenses', 'withdrawalTotals'));
    }
}
