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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function randomAttendance(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $counts = DB::transaction(function () {
            $students = Student::pluck('id')->shuffle()->values();
            $total = $students->count();
            $present = (int) round($total * 0.7);
            $late = min($total - $present, (int) round($total * 0.2));
            $date = today()->toDateString();
            $timestamp = now();
            $students->map(fn ($id, $index) => [
                'student_id' => $id, 'date' => $date,
                'status' => $index < $present ? 'present' : ($index < $present + $late ? 'late' : 'absent'),
                'created_at' => $timestamp, 'updated_at' => $timestamp,
            ])->chunk(100)->each(fn ($rows) => Attendance::upsert($rows->all(), ['student_id', 'date'], ['status', 'updated_at']));

            return ['present' => $present, 'late' => $late, 'absent' => $total - $present - $late];
        });

        return redirect()->route('dashboard')->with('success', "Random attendance saved for today: {$counts['present']} present, {$counts['late']} late, {$counts['absent']} absent.");
    }

    public function markAllPresent(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $count = DB::transaction(function () {
            $date = today()->toDateString();
            $count = Student::count();
            Attendance::whereDate('date', $date)->update(['status' => 'present', 'updated_at' => now()]);
            Student::whereDoesntHave('attendances', fn ($query) => $query->whereDate('date', $date))
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

        return redirect()->route('dashboard')->with('success', "All {$count} students marked present for today.");
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
        $ids = (clone $students)->select('id');
        $attendance = Attendance::whereIn('student_id', $ids)->whereDate('date', $reportDate);
        $fees = Fee::whereIn('student_id', $ids);
        $canViewFees = in_array($user->role, ['administrator', 'parent'], true);
        $months = collect(range(5, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $charts = [
            'labels' => $months->map(fn ($date) => $date->format('M'))->values(),
            'enrollment' => $months->map(fn ($date) => (clone $students)->whereBetween('enrollment_date', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])->count())->values(),
            // Revenue is grouped by the date payment was recorded on each fee record.
            'revenue' => $canViewFees ? $months->map(fn ($date) => (float) (clone $fees)->whereBetween('paid_at', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])->sum('paid'))->values() : [],
            'gender' => collect(['female', 'male', 'other'])->map(fn ($gender) => (clone $students)->where('gender', $gender)->count()),
            'attendance' => collect(['present', 'absent', 'late', 'excused'])->map(fn ($status) => (clone $attendance)->where('status', $status)->count()),
        ];

        $programBreakdowns = [];
        $normalizedClassCount = ClassGroup::query()->count();
        if ($user->role === 'administrator') {
            foreach (['Khmer', 'English', 'Chinese'] as $program) {
                $programBreakdowns['people'][$program] = (clone $students)
                    ->whereHas('schoolClass', fn ($q) => $q->where('program', $program))->count();
                $programBreakdowns['person-workspace'][$program] = User::where('role', 'teacher')->where('program', $program)->count();
                $programBreakdowns['building'][$program] = $normalizedClassCount > 0
                    ? ClassGroup::where('program', $program)->count()
                    : SchoolClass::where('program', $program)
                        ->select('name', 'section')->distinct()->get()->count();
                $programBreakdowns['book'][$program] = SchoolModules::subjects($user)
                    ->whereHas('schoolClass', fn ($q) => $q->where('program', $program))->distinct()->count('name');
            }
        }

        return [
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
                        : SchoolClass::whereIn('id', (clone $students)->select('school_class_id'))))
                    ->select('name', 'section')->distinct()->get()->count(),
            'subjectCount' => SchoolModules::subjects($user)->distinct()->count('name'),
            'presentCount' => (clone $attendance)->where('status', 'present')->count(),
            'absentCount' => (clone $attendance)->where('status', 'absent')->count(),
            'lateCount' => (clone $attendance)->where('status', 'late')->count(),
            'attendanceCount' => (clone $attendance)->count(), 'collected' => $canViewFees ? (clone $fees)->sum('paid') : null,
            'pending' => $canViewFees ? (float) (clone $fees)->sum('amount') - (float) (clone $fees)->sum('paid') : null,
            'recent' => (clone $students)->with(['schoolClass', 'user', 'parent'])->latest()->latest('id')->limit(5)->get(),
            'announcements' => Announcement::whereIn('audience', ['all', $user->role])->whereDate('published_at', '<=', today())->latest('published_at')->limit(4)->get(),
            'charts' => $charts,
        ];
    }

    public function reports(Request $request)
    {
        abort_unless($request->user()->role === 'administrator', 403);
        $classes = SchoolClass::withCount('students')->with('teacher')->get();
        $date = $request->validate(['date' => 'nullable|date'])['date'] ?? today()->toDateString();
        $attendance = Attendance::whereDate('date', $date)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $totals = ['billed' => Fee::sum('amount'), 'paid' => Fee::sum('paid')];

        return view('school.reports', compact('classes', 'date', 'attendance', 'totals'));
    }
}
