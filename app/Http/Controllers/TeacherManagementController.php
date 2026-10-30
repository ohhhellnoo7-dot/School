<?php

namespace App\Http\Controllers;

use App\Models\{ClassGroup, SchoolClass, Subject, Schedule, User};
use App\Services\TeacherManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherManagementController extends Controller
{
    public function previewSchedule(Request $request, User $teacher, \App\Services\TeacherScheduleGenerator $generator, TeacherManagementService $service)
    {
        abort_unless($teacher->role === 'teacher', 404);
        $plan = $generator->preview($teacher);
        $token = (string) Str::uuid();
        $request->session()->put('teacher_schedule_preview', ['plan' => $plan, 'token' => $token, 'created' => time()]);
        return view('school.teachers.schedule-preview', [
            'teacher' => $teacher, 'plan' => $plan, 'token' => $token, 'service' => $service,
            'lessons' => $service->lessons($teacher), 'breaks' => $service->breaks($teacher), 'periods' => $service->periods($teacher),
        ]);
    }

    public function confirmSchedule(Request $request, \App\Services\TeacherScheduleGenerator $generator, TeacherManagementService $service)
    {
        $request->validate(['confirm' => 'required|accepted', 'replace' => 'sometimes|accepted', 'token' => 'required|string']);
        $preview = $request->session()->get('teacher_schedule_preview');
        if (! $preview || time() - $preview['created'] > 1800 || ! hash_equals($preview['token'], $request->string('token')->toString())) $service->fail('This schedule preview expired or changed. Generate a new preview.');
        $generator->confirm($preview['plan'], $request->boolean('replace'));
        $request->session()->forget('teacher_schedule_preview');
        return redirect()->route('teachers.schedule', $preview['plan']['teacher_id'])->with('success', 'Teacher schedule saved. Class timetables use the same lessons.');
    }

    public function index(Request $request, TeacherManagementService $service)
    {
        $request->validate(['search' => 'nullable|string|max:255', 'qualified_subject' => 'nullable|string|max:255', 'program' => 'nullable|in:Khmer,English,Chinese', 'workload' => 'nullable|in:AVAILABLE,FULL,OVERLOADED,UNASSIGNED', 'class' => 'nullable|integer|exists:school_classes,id', 'session' => 'nullable|in:Morning,Afternoon,Evening', 'filter' => 'nullable|in:active,inactive']);
        $teachers = User::where('role', 'teacher')->orderBy('name')->get();
        $all = $teachers->map(fn ($t) => ['teacher' => $t, 'workload' => $service->workload($t)]);
        $rows = $all->filter(function ($r) use ($request, $service) {
            $t = $r['teacher'];
            if ($request->filled('search')) {
                $term = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $request->search)));
                if (preg_match("/^[\p{L}\p{M}]+(?:[\s'’-][\p{L}\p{M}]+)*$/u", $term)) {
                    if (! str_starts_with(mb_strtolower($t->name), $term)) return false;
                } elseif (! str_contains(mb_strtolower($t->name.' '.$t->id.' '.$t->profile_number.' '.$t->email), $term)) return false;
            }
            if ($request->filled('program') && ($t->program ?: 'Khmer') !== $request->program) return false;
            if ($request->filled('workload') && $r['workload']['status'] !== $request->workload) return false;
            if ($request->filled('filter') && $t->status !== $request->filter) return false;
            if ($request->filled('qualified_subject') && ($t->status !== 'active' || $r['workload']['remaining'] <= 0 || ! $service->qualifications($t)->contains($request->input('qualified_subject')) || $service->periods($t)->where('unavailable', false)->isEmpty())) return false;
            if ($request->filled('class') || $request->filled('session')) {
                $matching = fn ($q) => $q->when($request->input('class'), fn ($q, $id) => $q->whereKey($id))->when($request->input('session'), fn ($q, $s) => $q->where('session', $s));
                if (! $t->classes()->where($matching)->exists() && ! $t->subjects()->whereHas('schoolClass', $matching)->exists()) return false;
            }
            return true;
        });
        $records = new \Illuminate\Pagination\LengthAwarePaginator(new \Illuminate\Database\Eloquent\Collection($rows->pluck('teacher')->values()->all()), $rows->count(), max(1, $rows->count()), 1);
        return view('school.teachers.index', ['rows' => $rows, 'records' => $records, 'demand' => $service->demand(),
            'total' => $teachers->count(), 'available' => $all->filter(fn ($r) => $r['teacher']->status === 'active' && in_array($r['workload']['status'], ['AVAILABLE', 'UNASSIGNED']))->count(),
            'full' => $all->filter(fn ($r) => $r['teacher']->status === 'active' && $r['workload']['status'] === 'FULL')->count(),
            'classes' => SchoolClass::orderBy('name')->get()]);
    }

    public function form(?User $teacher = null)
    {
        return view('school.teachers.form', ['teacher' => $teacher ?? new User, 'catalog' => Subject::distinct()->orderBy('name')->pluck('name'), 'service' => app(TeacherManagementService::class)]);
    }

    public function save(Request $request, TeacherManagementService $service, ?User $teacher = null)
    {
        if ($teacher) abort_unless($teacher->role === 'teacher', 404);
        $data = $request->validate(['name' => 'required|string|max:255', 'gender' => 'required|in:male,female,other', 'phone' => 'nullable|string|max:50', 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($teacher?->id)],
            'program' => 'required|in:Khmer,English,Chinese', 'subject1' => ['required', Rule::exists('subjects', 'name')], 'subject2' => ['nullable', 'different:subject1', Rule::exists('subjects', 'name')],
            'weekly_hours' => 'nullable|numeric|min:1|max:40', 'working_sessions' => 'required|in:Morning,Afternoon,Both,Evening,Custom', 'status' => 'required|in:active,inactive',
            'periods' => 'nullable|array|max:25', 'periods.*.weekday' => ['required', Rule::in(TeacherManagementService::DAYS)], 'periods.*.start_time' => 'required|date_format:H:i', 'periods.*.end_time' => 'required|date_format:H:i|after:periods.*.start_time', 'periods.*.unavailable' => 'required|boolean']);
        $teacher = $service->locked(function () use ($teacher, $data, $service) {
            $t = $teacher ?: new User;
            $t->fill(collect($data)->only(['name', 'gender', 'phone', 'email', 'program', 'status'])->all());
            if (! $t->exists) { $t->role = 'teacher'; $t->password = Str::random(40); }
            $t->save();
            $periods = $data['periods'] ?? [];
            if ($data['working_sessions'] !== 'Custom') {
                $periods = [];
                if ($data['program'] === 'Khmer' && $data['working_sessions'] === 'Evening') $service->fail('Khmer teachers work morning or afternoon sessions.');
                $selected = $data['working_sessions'] === 'Both' ? ['Morning', 'Afternoon'] : [$data['working_sessions']];
                foreach (TeacherManagementService::DAYS as $day) foreach ($selected as $session) {
                    [$start, $end] = TeacherManagementService::SESSIONS[$session];
                    $periods[] = ['weekday' => $day, 'start_time' => $start, 'end_time' => $end, 'unavailable' => false];
                }
            }
            if (! collect($periods)->contains(fn ($p) => ! $p['unavailable'])) $service->fail('Confirm at least one on-duty working period.');
            $service->saveProfile($t, array_values(array_filter([$data['subject1'], $data['subject2'] ?? null])), isset($data['weekly_hours']) ? (int) round($data['weekly_hours'] * 60) : null, $periods);
            return $t;
        });
        return redirect()->route('teacher-management.show', $teacher)->with('success', 'Teacher information, qualifications, and working hours saved. Existing lessons were preserved.');
    }

    public function show(User $teacher, TeacherManagementService $service)
    {
        abort_unless($teacher->role === 'teacher', 404);
        return view('school.teachers.manage', ['teacher' => $teacher, 'service' => $service, 'workload' => $service->workload($teacher),
            'lessons' => $service->lessons($teacher), 'breaks' => $service->breaks($teacher), 'periods' => $service->periods($teacher),
            'subjects' => Subject::with('schoolClass')->orderBy('name')->get(), 'classes' => SchoolClass::orderBy('name')->get()]);
    }

    public function assign(Request $request, User $teacher, TeacherManagementService $service)
    {
        abort_unless($teacher->role === 'teacher', 404);
        $data = $request->validate(['subject_id' => 'required|integer|exists:subjects,id', 'day' => ['required', Rule::in(TeacherManagementService::DAYS)], 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i|after:starts_at', 'room' => 'required|string|max:255', 'replace' => 'sometimes|accepted']);
        $service->locked(function () use ($teacher, $data, $service) {
            $subject = Subject::findOrFail($data['subject_id']);
            if ($subject->teacher_id && $subject->teacher_id !== $teacher->id && empty($data['replace'])) $service->fail('Confirm reassignment before replacing this subject’s existing teacher.');
            $subject->teacher_id = $teacher->id;
            $service->validateSubject($subject);
            $subject->save();
            Schedule::where('subject_id', $subject->id)->update(['teacher_id' => $teacher->id]);
            $slot = new Schedule(collect($data)->only(['subject_id', 'day', 'starts_at', 'ends_at', 'room'])->all());
            $service->validateLesson($subject, $slot);
            $slot->save();
        });
        return back()->with('success', 'Class lesson assigned.');
    }

    public function availableTimes(Request $request, User $teacher, TeacherManagementService $service)
    {
        abort_unless($teacher->role === 'teacher', 404);
        $data = $request->validate(['subject_id' => 'required|integer|exists:subjects,id', 'day' => ['required', Rule::in(TeacherManagementService::DAYS)]]);
        $subject = Subject::findOrFail($data['subject_id']);
        $extra = $subject->teacher_id === $teacher->id ? 0 : Schedule::where('subject_id', $subject->id)->get()->sum(fn ($s) => TeacherManagementService::duration($s->starts_at, $s->ends_at));
        $subject->teacher_id = $teacher->id;
        $service->validateSubject($subject);
        $times = [];
        if ($service->workload($teacher)['remaining'] * 60 - $extra < 60) return response()->json(['times' => []]);
        foreach (TeacherManagementService::SESSIONS as $session => [$from, $to]) {
            if ($subject->schoolClass->session && $subject->schoolClass->session !== $session) continue;
            for ($m = TeacherManagementService::minute($from); $m + 60 <= TeacherManagementService::minute($to); $m += 60) {
                $start = sprintf('%02d:%02d', intdiv($m, 60), $m % 60); $end = sprintf('%02d:%02d', intdiv($m+60, 60), ($m+60)%60);
                $slot = new Schedule(['day' => $data['day'], 'starts_at' => $start, 'ends_at' => $end, 'room' => $subject->schoolClass->room ?: '']);
                try { $service->validateLesson($subject, $slot); $times[] = ['start' => $start, 'end' => $end]; } catch (ValidationException $e) { /* Exclude invalid times from the picker. */ }
            }
        }
        return response()->json(['times' => $times]);
    }

    public function previewBreaks(Request $request, TeacherManagementService $service, ?User $teacher = null)
    {
        if ($teacher) abort_unless($teacher->role === 'teacher', 404);
        $teachers = $teacher ? collect([$teacher]) : User::where('role', 'teacher')->where('status', 'active')->get();
        $plans = []; $missing = [];
        foreach ($teachers as $t) {
            try { $plans[$t->id] = ['name' => $t->name, 'breaks' => $service->proposeBreaks($t), 'replaces' => $service->breaks($t)->isNotEmpty(), 'original' => hash('sha256', $service->breaks($t)->toJson())]; }
            catch (ValidationException $e) { $missing[] = ['id' => $t->id, 'name' => $t->name, 'message' => $e->getMessage()]; }
        }
        $token = (string) Str::uuid();
        $request->session()->put('teacher_break_preview', ['plans' => $plans, 'created' => time(), 'token' => $token]);
        return view('school.teachers.break-preview', compact('plans', 'missing', 'token'));
    }

    public function confirmBreaks(Request $request, TeacherManagementService $service)
    {
        $request->validate(['confirm' => 'required|accepted', 'replace' => 'sometimes|accepted', 'token' => 'required|string']);
        $preview = $request->session()->get('teacher_break_preview');
        if (! $preview || time() - $preview['created'] > 1800 || ! hash_equals($preview['token'], $request->input('token'))) $service->fail('The break preview expired or changed. Please generate another preview.');
        $service->locked(function () use ($preview, $service, $request) {
            foreach ($preview['plans'] as $id => $plan) {
                $teacher = User::findOrFail($id);
                if (! hash_equals($plan['original'], hash('sha256', $service->breaks($teacher)->toJson()))) $service->fail('Existing breaks changed after preview. Please generate a new preview.');
                $service->saveBreaks($teacher, $plan['breaks'], $request->boolean('replace'));
            }
        });
        $request->session()->forget('teacher_break_preview');
        return redirect()->route('modules.index', 'teachers')->with('success', 'Weekly teacher breaks saved. No student lessons were changed.');
    }

    public function editBreak(Request $request, User $teacher, int $break, TeacherManagementService $service)
    {
        abort_unless($teacher->role === 'teacher', 404);
        $data = $request->validate(['weekday' => ['required', Rule::in(TeacherManagementService::DAYS)], 'start_time' => 'required|date_format:H:i', 'confirm' => 'required|accepted']);
        $service->locked(function () use ($teacher, $break, $data, $service) {
            $existing = $service->breaks($teacher); abort_unless($existing->contains('id', $break), 404);
            $plans = $existing->map(function ($b) use ($break, $data) {
                if ($b->id !== $break) return (array) $b;
                return ['weekday' => $data['weekday'], 'start_time' => $data['start_time'], 'end_time' => \Carbon\Carbon::createFromFormat('H:i', $data['start_time'])->addHour()->format('H:i')];
            })->all();
            $service->saveBreaks($teacher, $plans, true);
        });
        return back()->with('success', 'Break schedule updated.');
    }

    public function assignments(Request $request, TeacherManagementService $service)
    {
        $preview = $service->autoAssignments();
        $token = (string) Str::uuid();
        $request->session()->put('teacher_assignment_preview', ['assignments' => $preview['assignments'], 'created' => time(), 'token' => $token]);
        return view('school.teachers.assignment-preview', $preview + ['token' => $token]);
    }

    public function confirmAssignments(Request $request, TeacherManagementService $service)
    {
        $request->validate(['confirm' => 'required|accepted', 'token' => 'required|string']);
        $preview = $request->session()->get('teacher_assignment_preview');
        if (! $preview || time() - $preview['created'] > 1800 || ! hash_equals($preview['token'], $request->input('token'))) $service->fail('Generate a new assignment preview first.');
        $service->confirmAssignments($preview['assignments']);
        $request->session()->forget('teacher_assignment_preview');
        return redirect()->route('modules.index', 'teachers')->with('success', 'Confirmed teacher assignments saved.');
    }

    public function reports(TeacherManagementService $service)
    {
        return view('school.teachers.reports', ['service' => $service, 'demand' => $service->demand(), 'coverage' => $service->coverage(),
            'rows' => User::where('role', 'teacher')->orderBy('name')->get()->map(fn ($t) => ['teacher' => $t, 'workload' => $service->workload($t)])]);
    }

    public function settings(Request $request, TeacherManagementService $service)
    {
        $data = $request->validate(['target' => 'required|integer|min:1|max:1000', 'weekly_hours' => 'required|numeric|min:1|max:40']);
        $service->locked(fn () => DB::table('teacher_management_settings')->where('id', 1)->update(['target' => $data['target'], 'weekly_minutes' => (int) round($data['weekly_hours'] * 60)]));
        return back()->with('success', 'Staffing settings updated. Existing lessons were preserved.');
    }

    public function curriculum(Request $request, Subject $subject, TeacherManagementService $service)
    {
        $data = $request->validate(['weekly_hours' => 'required|numeric|min:0|max:40']);
        $service->locked(fn () => $subject->fill(['required_weekly_minutes' => (int) round($data['weekly_hours'] * 60)])->save());
        return back()->with('success', 'Approved curriculum hours saved. No lessons were changed.');
    }

    public function samplePreview(Request $request, TeacherManagementService $service)
    {
        $data = $request->validate(['count' => 'required|integer|min:1|max:100']);
        $gap = max(0, $service->settings()->target - $service->demand()['active']);
        if ($data['count'] > $gap) $service->fail('Choose no more than '.$gap.' sample teachers to reach the current Khmer staffing target.');
        $samples = [];
        for ($i = 1; count($samples) < $data['count']; $i++) {
            $email = sprintf('fictional-khmer-%03d@sample.scholar.invalid', $i);
            if (User::where('email', $email)->exists()) continue;
            $surnames = ['Sok', 'Chan', 'Chea', 'Heng', 'Lim']; $names = ['Dara', 'Sophea', 'Vanna', 'Bopha', 'Piseth'];
            $samples[] = ['name' => 'Fictional '.$surnames[($i-1)%5].' '.$names[intdiv($i-1,5)%5].' '.sprintf('%03d', $i), 'email' => $email];
        }
        $token = (string) Str::uuid();
        $request->session()->put('teacher_sample_preview', ['samples' => $samples, 'created' => time(), 'token' => $token]);
        return view('school.teachers.sample-preview', compact('samples', 'token'));
    }

    public function sampleConfirm(Request $request, TeacherManagementService $service)
    {
        $request->validate(['confirm' => 'required|accepted', 'token' => 'required|string']);
        $preview = $request->session()->get('teacher_sample_preview');
        if (! $preview || time() - $preview['created'] > 1800 || ! hash_equals($preview['token'], $request->input('token'))) $service->fail('Generate a new sample teacher preview first.');
        $service->locked(function () use ($service, $preview) {
            if (count($preview['samples']) > max(0, $service->settings()->target - $service->demand()['active'])) $service->fail('The staffing gap changed. Generate a new sample preview.');
            foreach ($preview['samples'] as $row) {
                if (User::where('email', $row['email'])->exists()) $service->fail('A preview identifier is already in use. Existing records were preserved. Generate a new preview.');
                $teacher = new User($row + ['program' => 'Khmer', 'status' => 'active', 'qualification' => 'Fictional planning record; subject qualifications are unverified', 'password' => Str::random(40)]);
                $teacher->role = 'teacher'; $teacher->save();
                DB::table('teacher_profiles')->insert(['teacher_id' => $teacher->id, 'weekly_minutes' => null]);
            }
        });
        $request->session()->forget('teacher_sample_preview');
        return redirect()->route('modules.index', 'teachers')->with('success', 'Confirmed fictional teacher records created. Verify subjects and working hours before scheduling.');
    }
}
