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
    private function filters(Request $request): array {
        return $request->validate(['date' => 'nullable|date_format:Y-m-d', 'program' => ['nullable', Rule::in(Program::names())], 'search' => 'nullable|string|max:100']);
    }
    public function index(Request $request) {
        abort_unless(in_array($request->user()->role, ['administrator', 'teacher']), 403);
        $filters = $this->filters($request);
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
        $teachers = new \Illuminate\Pagination\LengthAwarePaginator($teachers->forPage(max(1, $request->integer('page', 1)), 50)->values(), $total, 50, max(1, $request->integer('page', 1)), ['path' => $request->url(), 'query' => $request->query()]);
        $workingHours = DB::table('teacher_working_periods')->whereIn('teacher_id', $teachers->pluck('id'))->where('unavailable', false)->get()->groupBy('teacher_id')->map(fn ($periods) => $periods->map(fn ($p) => substr($p->start_time, 0, 5).'?'.substr($p->end_time, 0, 5))->unique()->join(', '));
        return view('school.attendance.teachers', compact('teachers', 'records', 'counts', 'total', 'date', 'workingHours'));
    }
    public function store(Request $request) {
        abort_unless($request->user()->role === 'administrator', 403);
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
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
        return redirect()->route('teacher-attendance.index', array_filter(['date' => $data['date'], 'program' => $data['program'] ?? null]))->with('success', $saved.' teacher attendance records saved.');
    }
}
