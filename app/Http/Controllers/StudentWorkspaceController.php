<?php

namespace App\Http\Controllers;

use App\Models\ClassAttendance;
use App\Models\FeePayment;
use App\Models\Student;
use App\Services\KhmerCurriculumService;
use App\Support\AcademicYear;
use App\Support\Money;
use App\Support\SchoolModules;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentWorkspaceController extends Controller
{
    private const SCHOOL_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    private const WEEK_DAYS = [...self::SCHOOL_DAYS, 'Saturday', 'Sunday'];

    public const SECTIONS = [
        'classes' => ['My classes', 'book'],
        'timetable' => ['My timetable', 'calendar-week'],
        'attendance' => ['My attendance', 'calendar-check'],
        'coursework' => ['Coursework & results', 'journal-check'],
        'announcements' => ['Announcements', 'megaphone'],
        'fees' => ['My fees', 'wallet2'],
        'record' => ['My student record', 'person-vcard'],
    ];

    public function show(Request $request, bool $fragment = false)
    {
        $data = $request->validate([
            'section' => ['sometimes', Rule::in(array_keys(self::SECTIONS))],
            'day' => ['sometimes', Rule::in(self::WEEK_DAYS)],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'view' => ['sometimes', Rule::in(['weekly'])],
            'month' => ['sometimes', 'date_format:Y-m'],
            'trend_days' => ['sometimes', Rule::in([8, 16])],
            'fees_tab' => ['sometimes', Rule::in(['invoices', 'payments', 'transactions'])],
            'assignment_filter' => ['sometimes', Rule::in(['upcoming', 'overdue'])],
            'coursework_panel' => ['sometimes', Rule::in(['exams', 'results'])],
            'announcement_view' => ['sometimes', Rule::in(['all'])],
            'record_activity' => ['sometimes', Rule::in(['all'])],
            'search' => ['nullable', 'string', 'max:100'],
            'class_session' => ['nullable', Rule::in(['Morning', 'Afternoon', 'Evening'])],
        ]);
        $section = $data['section'] ?? 'overview';
        $user = $request->user();
        $student = Student::visibleTo($user)->first();
        $query = fn (string $module) => SchoolModules::query($module, $user);

        if ($section === 'overview') {
            $attendance = $query('attendance')->whereDate('date', '<=', today())
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
            $upcoming = $query('assignments')->whereDate('due_date', '>=', today());
            $published = $query('announcements');
        }

        $records = match ($section) {
            'overview' => [
                'studentSessions' => $query('classes')->with('classGroup')->orderBy('name')->get(),
                'attendanceRate' => $attendance->sum() ? round(100 * ($attendance->get('present', 0) + $attendance->get('late', 0)) / $attendance->sum()) : null,
                'lessons' => $query('schedules')->with(['subject', 'teacher', 'classroom'])->forAcademicYear(AcademicYear::current())
                    ->where('day', today()->format('l'))->orderBy('starts_at')->get(),
                'assignmentCount' => (clone $upcoming)->count(),
                'assignments' => $upcoming->with('subject')->orderBy('due_date')->orderBy('id')->limit(3)->get(),
                'announcementCount' => (clone $published)->whereDate('published_at', '>=', today()->subDays(6))->count(),
                'announcement' => $published->latest('published_at')->latest('id')->first(),
            ],
            'classes' => [
                'classes' => $query('classes')->with([
                    'classGroup', 'teacher',
                    'subjects' => fn ($subjects) => $subjects->forTeaching()->with('teacher'),
                ])->orderBy('name')->get()->each(function ($class) {
                    $profile = app(KhmerCurriculumService::class)->profileFor($class);
                    if (! $profile) {
                        return;
                    }

                    $requirements = $profile->requirements->keyBy('name');
                    $curriculumName = fn ($subject) => KhmerCurriculumService::ALIASES[$subject->name] ?? $subject->name;
                    $class->setRelation('subjects', $class->subjects
                        ->filter(fn ($subject) => $requirements->has($curriculumName($subject)))
                        ->sortBy(fn ($subject) => [
                            (int) $subject->curriculum_requirement_id !== $requirements[$curriculumName($subject)]->id,
                            $subject->id,
                        ])
                        ->unique($curriculumName)->values());
                }),
                'classSession' => $data['class_session'] ?? null,
            ],
            'timetable' => $this->timetable($data, $query('schedules')),
            'coursework' => $this->coursework($data, $query),
            'attendance' => $this->attendanceSummary($data, $query('attendance')) + [
                'classAttendance' => ClassAttendance::with('schoolClass.classGroup')
                    ->whereIn('student_id', Student::visibleTo($user)->select('id'))
                    ->latest('date')->latest('id')->paginate(20, ['*'], 'class_attendance_page')->withQueryString(),
            ],
            'announcements' => $this->announcements($data, $query('announcements')),
            'fees' => $this->fees($data['fees_tab'] ?? 'invoices', $query('fees')),
            'record' => $this->record($query, isset($data['record_activity'])),
        };

        $view = view('school.student-workspace', compact('section', 'student') + $records);
        $response = $fragment
            ? response()->json([
                'html' => $view->fragment('student-content'),
                'summary' => ['studentCount' => $student ? 1 : 0],
                'charts' => ['revenue' => []],
                'showRevenue' => false,
            ])
            : response($view);

        return $response->header('Cache-Control', 'private, no-store');
    }

    private function timetable(array $data, Builder $schedules): array
    {
        $date = isset($data['date']) ? CarbonImmutable::parse($data['date']) : CarbonImmutable::today();
        $weekStart = $date->startOfWeek(CarbonInterface::MONDAY);
        if (isset($data['day'])) {
            $date = $weekStart->addDays(array_search($data['day'], self::WEEK_DAYS, true));
        }

        $weekly = ($data['view'] ?? null) === 'weekly';
        $days = $weekly ? self::SCHOOL_DAYS : [$date->format('l')];
        $lessonsByDay = $schedules->with(['subject', 'teacher', 'classroom'])
            ->forAcademicYear(AcademicYear::current())
            ->whereIn('day', $days)
            ->orderBy('starts_at')->orderBy('id')
            ->get()->groupBy('day');

        return compact('date', 'weekStart', 'weekly', 'days', 'lessonsByDay') + ['weekdays' => self::WEEK_DAYS];
    }

    private function attendanceSummary(array $data, Builder $attendance): array
    {
        $month = isset($data['month'])
            ? CarbonImmutable::parse($data['month'].'-01')
            : CarbonImmutable::today()->startOfMonth();
        $records = $attendance->whereBetween('date', [$month->toDateString(), $month->endOfMonth()->toDateString()])
            ->orderBy('date')->orderBy('id')->get();
        $statuses = ['present' => 'check-lg', 'late' => 'clock', 'absent' => 'x-lg', 'excused' => 'calendar2-check'];
        $byDate = $records->keyBy(fn ($record) => $record->date->toDateString());
        $counts = $byDate->countBy('status');
        $total = $counts->only(array_keys($statuses))->sum();
        $percentages = $counts->map(fn ($count) => $total ? round(100 * $count / $total) : 0);
        $rate = $total ? $percentages->get('present', 0) : null;
        $trendDays = (int) ($data['trend_days'] ?? 8);
        $present = 0;
        $trend = $byDate->whereIn('status', array_keys($statuses))->values()->map(function ($record, $index) use (&$present) {
            $present += (int) ($record->status === 'present');

            return ['date' => $record->date, 'status' => $record->status, 'rate' => round(100 * $present / ($index + 1))];
        })->take(-$trendDays)->values();

        return compact('month', 'byDate', 'statuses', 'counts', 'percentages', 'rate', 'trendDays', 'trend');
    }

    private function fees(string $feesTab, Builder $query): array
    {
        $invoices = (clone $query)->with('adjustments')->get();
        $totals = $invoices->groupBy('currency')->sortKeys()
            ->map(fn ($invoices) => [
                'billed' => $invoices->sum(fn ($fee) => $fee->netCents()),
                'paid' => $invoices->sum(fn ($fee) => Money::cents($fee->paid)),
                'pending' => $invoices->sum(fn ($fee) => $fee->checkout_submitted_at ? max(0, $fee->balanceCents()) : 0),
                'balance' => $invoices->sum(fn ($fee) => max(0, $fee->balanceCents())),
            ]);

        $fees = match ($feesTab) {
            'payments' => $query->where(fn ($fees) => $fees->where('paid', '>', 0)->orWhereHas('payments'))
                ->with(['payments' => fn ($payments) => $payments->latest('paid_on')->latest('id')])
                ->latest('paid_at'),
            'transactions' => $query->whereKey($invoices->filter(fn ($fee) => $fee->checkout_submitted_at && $fee->balanceCents() > 0)->modelKeys())
                ->latest('checkout_submitted_at'),
            default => $query->latest('due_date'),
        };
        $fees = $fees->with(['adjustments', 'items'])->latest('id')->paginate(15)->withQueryString();

        return compact('feesTab', 'totals', 'fees');
    }

    private function coursework(array $data, callable $query): array
    {
        $search = trim($data['search'] ?? '');
        $pattern = '%'.$search.'%';
        $assignmentFilter = $data['assignment_filter'] ?? 'all';
        $courseworkPanel = $data['coursework_panel'] ?? null;
        $today = today();
        $assignments = $query('assignments')->with('subject')
            ->when($search !== '', fn ($assignments) => $assignments->where(fn ($matches) => $matches
                ->where('title', 'like', $pattern)->orWhere('description', 'like', $pattern)
                ->orWhereHas('subject', fn ($subject) => $subject->where('name', 'like', $pattern))));
        $assignmentCounts = [
            'all' => (clone $assignments)->count(),
            'upcoming' => (clone $assignments)->whereDate('due_date', '>=', $today)->count(),
            'overdue' => (clone $assignments)->whereDate('due_date', '<', $today)->count(),
        ];
        $assignments = $assignments
            ->when($assignmentFilter !== 'all', fn ($assignments) => $assignments
                ->whereDate('due_date', $assignmentFilter === 'overdue' ? '<' : '>=', $today))
            ->latest('due_date')->latest('id')->paginate(10, ['*'], 'assignments_page')->withQueryString();

        $examMatches = fn ($exams) => $exams->where(fn ($matches) => $matches
            ->where('name', 'like', $pattern)->orWhereHas('subject', fn ($subject) => $subject->where('name', 'like', $pattern)));
        $exams = $query('examinations')->with('subject')->whereDate('date', '>=', $today)
            ->when($search !== '', $examMatches)->orderBy('date')->orderBy('id')
            ->paginate($courseworkPanel === 'exams' ? 15 : 3, ['*'], 'exams_page')->withQueryString();
        $results = $query('grades')->whereHas('examination', fn ($exams) => $exams->when($search !== '', $examMatches));
        $average = (clone $results)->join('examinations', 'examinations.id', '=', 'grades.examination_id')
            ->selectRaw('AVG(grades.marks * 100.0 / CASE WHEN examinations.total_marks > 0 THEN examinations.total_marks ELSE 1 END) AS average')
            ->toBase()->first()->average;
        $grades = $results->with('examination.subject')->latest('id')
            ->paginate($courseworkPanel === 'results' ? 15 : 3, ['*'], 'results_page')->withQueryString();
        $announcementCount = $query('announcements')->whereDate('published_at', '>=', $today->copy()->subDays(6))->count();

        return compact('assignments', 'assignmentCounts', 'assignmentFilter', 'courseworkPanel', 'exams', 'grades', 'average', 'announcementCount');
    }

    private function announcements(array $data, Builder $query): array
    {
        $announcementCount = (clone $query)->whereDate('published_at', '>=', today()->subDays(6))->count();
        $search = trim($data['search'] ?? '');
        $showAllAnnouncements = isset($data['announcement_view']) || $search !== '';
        $query->latest('published_at')->latest('id')
            ->when($search !== '', fn ($announcements) => $announcements->where(fn ($matches) => $matches
                ->where('title', 'like', '%'.$search.'%')->orWhere('body', 'like', '%'.$search.'%')));
        $announcements = $showAllAnnouncements
            ? $query->paginate(15)->withQueryString()
            : $query->limit(6)->get();

        return compact('announcements', 'announcementCount', 'showAllAnnouncements', 'search');
    }

    private function record(callable $query, bool $showAllActivity): array
    {
        $today = today();
        $classes = $query('classes')->with('classGroup')->orderBy('name')->get();
        $daily = $query('attendance')->whereDate('date', '<=', $today)->whereIn('status', ['present', 'late', 'absent', 'excused'])
            ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $attendanceRate = $daily->sum() ? round(100 * $daily->get('present', 0) / $daily->sum()) : null;

        $results = $query('grades')->with('examination.subject')->whereHas('examination')->latest('created_at')->latest('id')->get();
        $average = $results->avg('percentage');
        $progress = $results->groupBy('examination.subject_id')
            ->map(fn ($grades) => [
                'subject' => $grades->first()->examination->subject?->name ?? __('Subject'),
                'score' => round($grades->avg('percentage')),
            ])->sortBy('subject')->values();

        $fees = $query('fees');
        $balances = (clone $fees)->with('adjustments')->get()->groupBy('currency')->sortKeys()
            ->map(fn ($invoices) => $invoices->sum(fn ($fee) => max(0, $fee->balanceCents())));
        $payments = FeePayment::query()->with('fee')->whereIn('fee_id', $fees->select('id'))
            ->whereDate('paid_on', '<=', $today)->latest('paid_on')->latest('id')
            ->when(! $showAllActivity, fn ($payments) => $payments->limit(3))->get();
        $assignments = $query('assignments');
        $assignmentCount = (clone $assignments)->whereDate('due_date', '>=', $today)->count();
        $activity = collect()
            ->concat($assignments->latest('created_at')->latest('id')
                ->when(! $showAllActivity, fn ($assignments) => $assignments->limit(3))->get()->map(fn ($assignment) => [
                'date' => $assignment->created_at, 'kind' => 'assignment', 'title' => __('Assignment posted'),
                'detail' => $assignment->title,
            ]))
            ->concat(($showAllActivity ? $results : $results->take(3))->map(fn ($grade) => [
                'date' => $grade->created_at, 'kind' => 'grade', 'title' => __('Exam result recorded'),
                'detail' => $grade->examination->name.' ('.round($grade->percentage).'%)',
            ]))
            ->concat($payments->map(fn ($payment) => [
                'date' => $payment->paid_on, 'kind' => 'payment', 'title' => __('Fee payment recorded'),
                'detail' => $payment->fee->description.' ('.$payment->amount.' '.$payment->fee->currency.')',
            ]))->sortByDesc('date');
        $activity = $showAllActivity ? $activity : $activity->take(3);
        $announcementCount = $query('announcements')->whereDate('published_at', '>=', $today->copy()->subDays(6))->count();

        return compact('classes', 'attendanceRate', 'average', 'progress', 'balances', 'assignmentCount', 'activity', 'announcementCount', 'showAllActivity');
    }
}
