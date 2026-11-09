<?php
namespace App\Http\Controllers;
use App\Models\Program;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class TeacherAttendanceController extends Controller
{
    public function bulk(Request $request, string $action) {
        abort_unless($request->user()->role === 'administrator', 403);
        abort_unless(in_array($action, ['all-present', 'random'], true), 404);
        $count = DB::transaction(function () use ($request, $action) {
            $teachers = User::where('role', 'teacher')
                ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
                ->lockForUpdate()->get()->shuffle()->values();
            $total = $teachers->count();
            $present = (int) round($total * .7);
            $late = min($total - $present, (int) round($total * .15));
            $absent = min($total - $present - $late, (int) round($total * .1));
            foreach ($teachers as $index => $teacher) {
                $existing = TeacherAttendance::where('teacher_id', $teacher->id)->whereDate('date', today())->first();
                $status = $action === 'all-present' || $index < $present ? 'present'
                    : ($index < $present + $late ? 'late' : ($index < $present + $late + $absent ? 'absent' : 'excused'));
                $record = $existing ?? new TeacherAttendance(['teacher_id' => $teacher->id, 'date' => today()->toDateString()]);
                $record->fill([
                    'program' => $existing?->program ?? $teacher->program,
                    'status' => $status, 'recorded_by' => $request->user()->id,
                ])->save();
            }
            return $total;
        });
        return redirect()->route('dashboard')->with('success', $count.' teacher attendance records saved for today.');
    }

    public function index(Request $request) {
        abort_unless(in_array($request->user()->role, ['administrator', 'teacher']), 403);
        return view('school.attendance.teachers', $this->data($request));
    }

    public function data(Request $request, bool $embedded = false): array {
        abort_unless(in_array($request->user()->role, ['administrator', 'teacher']), 403);
        $prefix = $embedded ? 'attendance_' : '';
        $filters = \Illuminate\Support\Facades\Validator::make([
            'date' => $request->input($prefix.'date'),
            'program' => $request->input($prefix.'program'),
            'search' => $request->input($prefix.'search'),
        ], ['date' => 'nullable|date_format:Y-m-d', 'program' => ['nullable', Rule::in(Program::names())], 'search' => 'nullable|string|max:100'])->validate();
        $date = $filters['date'] ?? today()->toDateString();
        $records = TeacherAttendance::whereDate('date', $date)
            ->when($request->user()->role === 'teacher', fn ($q) => $q->where('teacher_id', $request->user()->id))
            ->when($filters['program'] ?? null, fn ($q, $program) => $q->where('program', $program))->get()->toBase()->keyBy('teacher_id');
        $teachers = User::where('role', 'teacher')
            ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status')->orWhereIn('id', $records->keys()))
            ->when($request->user()->role === 'teacher', fn ($q) => $q->whereKey($request->user()->id))
            ->when($filters['program'] ?? null, fn ($q, $program) => $q->where(fn ($scope) => $scope->where('program', $program)->orWhereIn('id', $records->keys())))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->orderBy('name')->get();
        $records = $records->only($teachers->pluck('id')->all());
        $counts = $records->countBy('status');
        $total = $teachers->count();
        $insights = null;
        if ($embedded && $request->user()->role === 'administrator') {
            $end = \Illuminate\Support\Carbon::parse($date);
            $history = TeacherAttendance::whereDate('date', '>=', $end->copy()->subDays(6)->toDateString())->whereDate('date', '<=', $date)
                ->when($filters['program'] ?? null, fn ($q, $program) => $q->where('program', $program))
                ->when($filters['search'] ?? null, fn ($q, $search) => $q->whereHas('teacher', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'))))
                ->get()->groupBy(fn ($record) => $record->date->toDateString());
            $trend = collect(range(6, 0))->map(function ($offset) use ($end, $history) {
                $day = $end->copy()->subDays($offset);
                $daily = $history->get($day->toDateString(), collect());
                return ['date' => $day->toDateString(), 'label' => $day->format('D'), 'recorded' => $daily->count(),
                    'rate' => $daily->isEmpty() ? null : (int) round($daily->where('status', 'present')->count() / $daily->count() * 100)];
            });
            $programs = $teachers->groupBy(fn ($teacher) => $records->get($teacher->id)?->program ?? $teacher->program ?? 'Unassigned')
                ->map(function ($members, $program) use ($records) {
                    $marked = $records->only($members->pluck('id')->all());
                    return ['name' => $program, 'total' => $members->count(), 'recorded' => $marked->count(),
                        'rate' => $marked->isEmpty() ? null : (int) round($marked->where('status', 'present')->count() / $marked->count() * 100)];
                })->values();
            $recent = $records->sortByDesc('updated_at')->take(3);
            $recent->each(fn ($record) => $record->loadMissing('teacher'));
            $insights = compact('trend', 'programs', 'recent');
        }
        $teachers = new \Illuminate\Pagination\LengthAwarePaginator($teachers->forPage(max(1, $request->integer($prefix.'page', 1)), 50)->values(), $total, 50, max(1, $request->integer($prefix.'page', 1)), ['path' => $request->url(), 'query' => $request->query(), 'pageName' => $prefix.'page', 'fragment' => 'teacher-attendance']);
        $workingHours = DB::table('teacher_working_periods')->whereIn('teacher_id', $teachers->pluck('id'))->where('unavailable', false)->get()->groupBy('teacher_id')->map(fn ($periods) => $periods->map(fn ($p) => substr($p->start_time, 0, 5).'?'.substr($p->end_time, 0, 5))->unique()->join(', '));
        return compact('teachers', 'records', 'counts', 'total', 'date', 'workingHours', 'filters', 'embedded', 'prefix', 'insights');
    }
    public function store(Request $request) {
        abort_unless($request->user()->role === 'administrator', 403);
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'return_to' => 'nullable|in:teachers',
            'attendance_page' => 'nullable|integer|min:1',
            'program' => ['nullable', Rule::in(Program::names())],
            'records' => 'required|array|max:50',
            'records.*' => 'required|array:teacher_id,status,notes',
            'records.*.teacher_id' => 'required|integer|distinct',
            'records.*.status' => 'nullable|in:present,absent,late,excused',
            'records.*.notes' => 'nullable|string|max:1000',
        ]);
        $saved = DB::transaction(function () use ($data, $request) {
            $teachers = User::where('role', 'teacher')->whereIn('id', array_column($data['records'], 'teacher_id'))->lockForUpdate()->get()->keyBy('id');
            $saved = 0;
            foreach ($data['records'] as $row) {
                $teacher = $teachers->get($row['teacher_id']);
                $existing = TeacherAttendance::where('teacher_id', $row['teacher_id'])->whereDate('date', $data['date'])->first();
                if (!$teacher || (!in_array($teacher->status, [null, 'active'], true) && !$existing) || (!empty($data['program']) && ($existing?->program ?? $teacher->program) !== $data['program'])) {
                    throw ValidationException::withMessages(['records' => 'Choose active teachers belonging to the selected program.']);
                }
                if (empty($row['status'])) continue;
                TeacherAttendance::updateOrCreate(['teacher_id' => $teacher->id, 'date' => $data['date']], ['program' => $existing?->program ?? $teacher->program, 'status' => $row['status'], 'notes' => $row['notes'] ?? null, 'recorded_by' => $request->user()->id]);
                $saved++;
            }
            return $saved;
        });
        if (($data['return_to'] ?? null) === 'teachers') {
            return redirect()->to(route('modules.index', array_filter(['module' => 'teachers', 'attendance_date' => $data['date'], 'attendance_program' => $data['program'] ?? null, 'attendance_page' => $data['attendance_page'] ?? null])).'#teacher-attendance')->with('success', $saved.' teacher attendance records saved.');
        }
        return redirect()->route('teacher-attendance.index', array_filter(['date' => $data['date'], 'program' => $data['program'] ?? null]))->with('success', $saved.' teacher attendance records saved.');
    }
}
