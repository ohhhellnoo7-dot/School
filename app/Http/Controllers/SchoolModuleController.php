<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Examination;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\SchoolDependencies;
use App\Support\SchoolModules;
use App\Support\TeacherTimetable;
use App\Support\TimetableConflicts;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SchoolModuleController extends Controller
{
    public function index(Request $request, string $module)
    {
        $meta = SchoolModules::get($module);
        $query = SchoolModules::query($module, $request->user());
        $subjectSummary = null;
        if ($module === 'subjects') {
            $subjectSummary = [
                'total' => (clone $query)->count(),
                'assigned' => (clone $query)->whereHas('teacher')->count(),
                'unassigned' => (clone $query)->whereDoesntHave('teacher')->count(),
                'classes' => (clone $query)->whereNotNull('school_class_id')->distinct()->count('school_class_id'),
            ];
            $request->validate(['class' => 'nullable|integer|exists:school_classes,id']);
            if ($request->filled('class')) {
                $query->where('school_class_id', $request->input('class'));
            }
            if ($request->filled('teacher')) {
                $query->where('teacher_id', $request->input('teacher'));
            }
        }
        $classSummary = null;
        if ($module === 'classes') {
            $query->where(fn ($class) => $class->whereNull('program')
                ->orWhere('program', '!=', 'Chinese')
                ->orWhere('session', 'Evening'))
                ->where(fn ($class) => $class->whereNull('program')
                    ->orWhere('program', '!=', 'Khmer')
                    ->orWhereNull('session')
                    ->orWhereIn('session', ['Morning', 'Afternoon']));
            $classSummary = [
                'total' => (clone $query)->select('name', 'section')->distinct()->get()->count(),
                'students' => Student::whereIn('school_class_id', (clone $query)->select('school_classes.id'))->count(),
                'teachers' => (clone $query)->whereNotNull('teacher_id')->distinct()->count('teacher_id'),
                'occupied' => (clone $query)->whereHas('students')->count(),
            ];
            $request->validate([
                'session' => 'nullable|in:Morning,Afternoon,Evening',
                'filter' => 'nullable|in:Khmer,Chinese,English',
            ]);
            if ($request->input('filter') === 'Chinese') {
                $request->merge(['session' => 'Evening']);
            } elseif ($request->input('filter') === 'Khmer' && $request->input('session') === 'Evening') {
                $request->merge(['session' => null]);
            }
            if ($request->filled('session')) {
                $query->where('session', $request->session);
            }
        }
        $teacherSummary = null;
        if ($module === 'teachers') {
            $teacherSummary = [
                'total' => (clone $query)->count(),
                'male' => (clone $query)->where('gender', 'male')->count(),
                'female' => (clone $query)->where('gender', 'female')->count(),
                'subjects' => Subject::whereHas('teacher', fn ($q) => $q->whereIn('id', (clone $query)->select('users.id')))->distinct()->count('name'),
            ];
        }
        $parentSummary = null;
        if ($module === 'parents') {
            $parentSummary = [
                'total' => (clone $query)->count(),
                'male' => (clone $query)->where('gender', 'male')->count(),
                'female' => (clone $query)->where('gender', 'female')->count(),
                'unspecified' => (clone $query)->where(fn ($q) => $q->whereNull('gender')->orWhere('gender', '')->orWhereNotIn('gender', ['male', 'female']))->count(),
            ];
            $request->validate(['gender' => 'nullable|in:male,female,other,unspecified']);
            if ($request->gender === 'unspecified') {
                $query->where(fn ($q) => $q->whereNull('gender')->orWhere('gender', '')->orWhereNotIn('gender', ['male', 'female']));
            } elseif ($request->filled('gender')) {
                $query->where('gender', $request->gender);
            }
        }
        $parentGrades = collect();
        $teacherFilterClasses = collect();
        if ($module === 'parents') {
            $request->validate(['program' => 'nullable|in:Khmer,English,Chinese', 'session' => 'nullable|in:Morning,Afternoon,Evening', 'grade' => 'nullable|string|max:255']);
            if ($request->input('program') === 'Chinese') {
                $request->merge(['session' => 'Evening']);
            } elseif ($request->input('program') === 'Khmer' && $request->input('session') === 'Evening') {
                $request->merge(['session' => null]);
            }
            $parentGrades = SchoolClass::whereNotNull('name')->get(['name', 'section', 'program', 'session'])
                ->filter(fn ($class) => $class->program !== 'Khmer' || in_array($class->name, array_map(fn ($grade) => 'Grade '.$grade, range(7, 12)), true))
                ->sort(fn ($a, $b) => strnatcasecmp($a->name.$a->section, $b->name.$b->section))->values();
            [$gradeName, $gradeSection] = array_pad(explode('::', $request->input('grade', '') ?? '', 2), 2, null);
            if ($request->filled('program') || $request->filled('session') || $request->filled('grade')) {
                $query->whereHas('children.schoolClass', fn ($q) => $q
                    ->when($request->program, fn ($q, $program) => $q->where('program', $program))
                    ->when($request->session, fn ($q, $session) => $q->where('session', $session))
                    ->when($gradeName, fn ($q) => $q->where('name', $gradeName))
                    ->when($gradeSection !== null, fn ($q) => $q->where('section', $gradeSection)));
            }
        }
        $request->validate(['search' => 'nullable|string|max:255', 'direction' => 'nullable|in:asc,desc', 'sort' => 'nullable|string', 'filter' => 'nullable|string|max:255', 'date' => 'nullable|date', 'teacher' => 'nullable|integer|exists:users,id']);
        $fields = array_keys(array_filter($meta['fields'], fn ($type) => in_array($type, ['text', 'email', 'textarea'], true)));
        if ($module === 'teachers') {
            $request->validate(['program' => 'nullable|in:Khmer,English,Chinese', 'session' => 'nullable|in:Morning,Afternoon,Evening', 'class' => 'nullable|integer|exists:school_classes,id']);
            if ($request->program === 'Chinese' && $request->filled('session')) {
                $request->merge(['session' => 'Evening']);
            } elseif ($request->program === 'Khmer' && $request->session === 'Evening') {
                $request->merge(['session' => null]);
            }
            $teacherFilterClasses = SchoolClass::orderBy('name')->orderBy('section')->get();
            if ($request->filled('class') && ! SchoolClass::whereKey($request->input('class'))
                ->when($request->program, fn ($q, $program) => $q->where('program', $program))
                ->when($request->session, fn ($q, $session) => $q->where(fn ($q) => $q->where('session', $session)->orWhereNull('session')))->exists()) {
                $request->merge(['class' => null]);
            }
            if ($request->filled('program')) {
                $query->where('program', $request->program);
            }
            if ($request->filled('session') || $request->filled('class')) {
                $matchingClass = fn ($q) => $q
                    ->when($request->program, fn ($q, $program) => $q->where('program', $program))
                    ->when($request->class, fn ($q, $class) => $q->whereKey($class))
                    ->when($request->session, fn ($q, $session) => $q->where('session', $session));
                $query->where(fn ($q) => $q->whereHas('classes', $matchingClass)
                    ->orWhereHas('subjects.schoolClass', $matchingClass));
            }
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($fields, $request, $module) {
                $search = trim($request->search);
                // Names must start with the typed letters, never match email/address contents.
                if (in_array($module, ['teachers', 'parents', 'users']) && preg_match("/^[\\p{L}\\p{M}]+(?:[\\s'\\x{2019}-][\\p{L}\\p{M}]+)*$/u", $search)) {
                    $prefix = mb_strtolower(preg_replace('/\\s+/u', ' ', $search)).'%';
                    $q->whereRaw('LOWER(name) LIKE ?', [$prefix]);
                    return;
                }
                foreach ($fields as $field) {
                    $q->orWhere($field, 'like', '%'.$request->search.'%');
                }
                if ($module === 'subjects') {
                    $q->orWhereHas('teacher', fn ($teacher) => $teacher->where('name', 'like', '%'.$search.'%'));
                }
                if ($module === 'classes') {
                    $q->orWhereRaw('LOWER(program) LIKE ?', ['%'.strtolower($request->search).'%']);
                }
                if (in_array($module, ['fees', 'attendance', 'grades'])) {
                    $q->orWhereHas('student', fn ($s) => $s->where('name', 'like', '%'.$request->search.'%')->orWhere('student_number', 'like', '%'.$request->search.'%'));
                }
            });
        }
        $filterFields = isset($meta['fields']['status']) ? ['status' => $meta['fields']['status']] : $meta['fields'];
        foreach ($filterFields as $field => $type) {
            if (is_array($type) && $request->filled('filter')) {
                $query->where($field, $request->filter);
                break;
            }
        }
        if ($module === 'attendance' && $request->filled('date')) {
            $query->whereDate('date', $request->date);
        }
        if ($module === 'classes' && $request->filled('teacher')) {
            $query->where('teacher_id', $request->teacher);
        }
        if ($module === 'fees' && $request->filled('filter')) {
            match ($request->filter) {
                'paid' => $query->whereColumn('paid', '>=', 'amount'),
                'overdue' => $query->whereColumn('paid', '<', 'amount')->whereDate('due_date', '<', today()),
                'partially_paid' => $query->where('paid', '>', 0)->whereColumn('paid', '<', 'amount')->whereDate('due_date', '>=', today()),
                'pending' => $query->where('paid', 0)->whereColumn('paid', '<', 'amount')->whereDate('due_date', '>=', today()),
                default => null,
            };
        }
        if (in_array($module, ['teachers', 'parents'])) {
            $query->with(['subjects', 'children.user']);
        }
        if ($module === 'parents') {
            $query->with(['children.attendances' => fn ($q) => $q->whereDate('date', today())]);
        }
        if (in_array($module, ['attendance', 'grades', 'fees'])) {
            $query->with('student.user');
        }
        if ($module === 'subjects') {
            $query->with(['teacher', 'schoolClass']);
        }
        if ($module === 'classes') {
            $query->with('teacher')->withCount('students');
        }
        if ($module === 'grades') {
            $query->with('examination');
        }
        $sortable = array_merge(['id'], array_keys(array_filter($meta['fields'], fn ($type) => $type !== 'password')));
        $sort = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';

        $uniqueClassCount = $module === 'classes' ? (clone $query)->select('name', 'section')->distinct()->get()->count() : null;

        return view('school.modules.index', ['module' => $module, 'meta' => $meta,
            'uniqueClassCount' => $uniqueClassCount,
            'parentGrades' => $parentGrades,
            'parentSummary' => $parentSummary,
            'subjectSummary' => $subjectSummary,
            'classSummary' => $classSummary,
            'teacherSummary' => $teacherSummary,
            'teacherFilterClasses' => $teacherFilterClasses,
            'records' => $query->orderBy($sort, $request->input('direction', 'desc'))->orderBy('id')->paginate(15)->withQueryString(),
            'options' => SchoolModules::options($request->user())]);
    }

    public function show(Request $request, string $module, int $id)
    {
        $meta = SchoolModules::get($module);
        $record = SchoolModules::query($module, $request->user())->findOrFail($id);

        if ($module === 'classes') {
            return $this->classTimetable($request, $record);
        }

        return view('school.modules.show', compact('module', 'meta', 'record') + ['options' => SchoolModules::options($request->user())]);
    }

    public function teacherTimetable(Request $request, User $teacher)
    {
        abort_unless($teacher->role === 'teacher' && ($request->user()->role === 'administrator' || ($request->user()->role === 'teacher' && $request->user()->id === $teacher->id)), 403);
        $slots = Schedule::with('subject.schoolClass')->whereHas('subject', fn ($q) => $q->where('teacher_id', $teacher->id))->orderBy('starts_at')->get()->groupBy('day');

        return view('school.teachers.timetable', compact('teacher', 'slots'));
    }

    public function classTimetable(Request $request, SchoolClass $schoolClass)
    {
        return app(TimetableController::class)->show($request, $schoolClass);
    }

    public function create(Request $request, string $module)
    {
        $meta = SchoolModules::get($module);
        $record = new $meta['model'];
        Gate::authorize('manage', [$record, $module]);

        return view('school.modules.form', compact('module', 'meta', 'record') + ['options' => SchoolModules::options($request->user())]);
    }

    public function edit(Request $request, string $module, int $id)
    {
        $meta = SchoolModules::get($module);
        $record = SchoolModules::query($module, $request->user())->findOrFail($id);
        Gate::authorize('manage', [$record, $module]);
        $this->authorizeTeacherGrade($request, $record);

        return view('school.modules.form', compact('module', 'meta', 'record') + ['options' => SchoolModules::options($request->user())]);
    }

    public function store(Request $request, string $module)
    {
        $meta = SchoolModules::get($module);
        $record = new $meta['model'];
        Gate::authorize('manage', [$record, $module]);
        $data = $this->validated($request, $module, $record);
        DB::transaction(function () use ($record, $data, $module) {
            $record->fill($data);
            if (in_array($module, ['teachers', 'parents'])) {
                $record->role = $module === 'teachers' ? 'teacher' : 'parent';
            }
            if ($module === 'users') {
                $record->role = $data['role'];
            }
            if ($module === 'announcements') {
                $record->created_by = auth()->id();
            }
            $record->save();
            if ($record instanceof User) $record->ensureParentProfileNumber();
        });

        return redirect()->route('modules.index', $module)->with('success', 'Record created successfully.');
    }

    public function update(Request $request, string $module, int $id)
    {
        $record = SchoolModules::query($module, $request->user())->findOrFail($id);
        Gate::authorize('manage', [$record, $module]);
        $this->authorizeTeacherGrade($request, $record);
        $data = $this->validated($request, $module, $record);
        if ($record instanceof User && isset($data['role']) && $data['role'] !== $record->role) {
            // Linked records must be explicitly unlinked before an account changes purpose.
            if (Student::where('user_id', $record->id)->orWhere('parent_id', $record->id)->exists()
                || Subject::where('teacher_id', $record->id)->exists()
                || SchoolClass::where('teacher_id', $record->id)->exists()) {
                throw ValidationException::withMessages(['role' => 'Unlink this account from students and teaching assignments before changing its role.']);
            }
            if ($record->id === $request->user()->id) {
                throw ValidationException::withMessages(['role' => 'You cannot remove your own administrator access.']);
            }
        }
        if ($module === 'users') {
            $record->role = $data['role'];
        }
        $record->fill($data)->save();
        if ($record instanceof User) $record->ensureParentProfileNumber();

        return redirect()->route('modules.index', $module)->with('success', 'Record updated successfully.');
    }

    public function destroy(Request $request, string $module, int $id)
    {
        $record = SchoolModules::query($module, $request->user())->findOrFail($id);
        Gate::authorize('manage', [$record, $module]);
        $this->authorizeTeacherGrade($request, $record);
        if ($record instanceof User && $record->id === $request->user()->id) {
            return back()->withErrors(['record' => 'You cannot delete your own account.']);
        }
        if (SchoolDependencies::hasDependents($record)) {
            return back()->withErrors(['record' => 'This record has linked school records. Reassign or remove those records before deleting it.']);
        }
        try {
            $record->delete();
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23503'], true)) {
                return back()->withErrors(['record' => 'This record is in use. Remove or reassign its dependent records first.']);
            }
            throw $exception;
        }

        return back()->with('success', 'Record deleted.');
    }

    private function authorizeTeacherGrade(Request $request, $record): void
    {
        if ($request->user()->role === 'teacher' && $record instanceof Grade) {
            abort_unless(SchoolModules::subjects($request->user())->whereKey($record->examination->subject_id)->exists(), 403);
        }
    }

    private function validated(Request $request, string $module, $record): array
    {
        $rules = [];
        $meta = SchoolModules::get($module);
        $optional = ['phone', 'notes', 'remarks', 'paid_at', 'teacher_id', 'profile_number', 'gender', 'address', 'qualification', 'hire_date', 'salary', 'status', 'program', 'session'];
        $options = SchoolModules::options($request->user());
        foreach ($meta['fields'] as $field => $type) {
            $rules[$field] = [in_array($field, $optional) || ($field === 'password' && $record->exists) ? 'nullable' : 'required'];
            if (is_array($type)) {
                $rules[$field][] = Rule::in($type);
            } elseif (in_array($type, ['teacher', 'class', 'student', 'subject', 'exam'])) {
                $rules[$field][] = Rule::in($options[$type]->keys()->all());
            } else {
                $rules[$field] = array_merge($rules[$field], match ($type) {
                    'number' => ['numeric', 'min:0', 'max:99999999'],
                    'email' => ['email', 'max:255', Rule::unique('users', 'email')->ignore($record->id)],
                    'date' => ['date'], 'time' => ['date_format:H:i'],
                    'password' => ['string', 'max:255', (in_array($module, ['parents', 'users']) && ($module === 'parents' || in_array($request->input('role'), ['student', 'parent'])) ? Password::min(3) : Password::min(10)->mixedCase()->numbers())],
                    'textarea' => ['string', 'max:10000'], default => ['string', 'max:255'],
                });
            }
        }
        if (in_array($module, ['teachers', 'parents', 'users']) && isset($rules['profile_number'])) {
            $rules['profile_number'][] = Rule::unique('users', 'profile_number')->ignore($record->id);
        }
        if (isset($rules['salary'])) {
            $rules['salary'][] = 'decimal:0,2';
        }
        if ($module === 'subjects') {
            $rules['code'][] = Rule::unique('subjects', 'code')->ignore($record->id);
        }
        if ($module === 'classes') {
            $rules['capacity'] = ['required', 'integer', 'min:1', 'max:1000'];
            $rules['section'][] = Rule::unique('school_classes')
                ->where('name', $request->input('name'))
                ->where('program', $request->input('program'))
                ->where('session', $request->input('session'))
                ->ignore($record->id);
        }
        if ($module === 'examinations') {
            $rules['total_marks'] = ['required', 'integer', 'min:1', 'max:10000'];
        }
        if ($module === 'attendance') {
            $rules['date'][] = 'before_or_equal:today';
            $rules['date'][] = function ($attribute, $value, $fail) use ($request, $record) {
                if (Attendance::where('student_id', $request->input('student_id'))->whereDate('date', $value)->when($record->exists, fn ($q) => $q->where('id', '!=', $record->id))->exists()) {
                    $fail('Attendance has already been recorded for this student on this date.');
                }
            };
        }
        if ($module === 'grades') {
            $rules['examination_id'][] = Rule::unique('grades')->where('student_id', $request->input('student_id'))->ignore($record->id);
        }
        if ($module === 'fees') {
            $rules['paid'][] = 'lte:amount';
            $rules['amount'][] = 'decimal:0,2';
            $rules['paid'][] = 'decimal:0,2';
            $rules['paid_at'][] = 'before_or_equal:today';
            $rules['paid_at'][] = Rule::requiredIf(fn () => (float) $request->input('paid', 0) > 0);
        }
        if ($module === 'schedules') {
            $rules['ends_at'][] = 'after:starts_at';
        }
        $data = $request->validate($rules);
        if ($module === 'classes' && ($data['program'] ?? null) === 'Khmer' && ($data['session'] ?? null) === 'Evening') {
            throw ValidationException::withMessages(['session' => 'Khmer classes study in Morning or Afternoon only.']);
        }
        if ($module === 'classes' && ($data['program'] ?? null) === 'Chinese' && ($data['session'] ?? null) !== 'Evening') {
            throw ValidationException::withMessages(['session' => 'Chinese classes study in the Evening only.']);
        }
        if ($module === 'classes' && $record->exists && ! empty($data['session'])) {
            [$start, $end] = SchoolClass::SESSION_HOURS[$data['session']];
            $outsideSession = Schedule::where(fn ($q) => $q->where('school_class_id', $record->id)
                ->orWhereHas('subject', fn ($subject) => $subject->where('school_class_id', $record->id)))
                ->where(fn ($q) => $q->where('starts_at', '<', $start)->orWhere('ends_at', '>', $end))->exists();
            if ($outsideSession) {
                throw ValidationException::withMessages(['session' => 'Move existing lessons into the selected session hours before changing the class session.']);
            }
        }
        if ($module === 'schedules') {
            $subject = Subject::findOrFail($data['subject_id']);
            if (! TeacherTimetable::withinHours($subject, $data['starts_at'], $data['ends_at']) || TimetableConflicts::exists($subject, $data['day'], $data['starts_at'], $data['ends_at'], $data['room'], $record->id)) {
                throw ValidationException::withMessages(['starts_at' => 'Use Morning 07:00-11:00, Afternoon 13:00-17:00, or Evening 16:30-18:30 within the class session, without teacher, class or room overlaps. Chinese teachers teach in the evening only.']);
            }
        }
        if ($module === 'attendance') {
            $date = Carbon::parse($data['date']);
            if ($date->isWeekend() || $date->lt(Student::findOrFail($data['student_id'])->enrollment_date)) {
                throw ValidationException::withMessages(['date' => 'Choose a weekday on or after the student enrollment date.']);
            }
        }
        if ($record->exists && $module === 'subjects' && ((int) ($data['teacher_id'] ?? 0) !== (int) $record->teacher_id || (int) $data['school_class_id'] !== $record->school_class_id) && Schedule::where('subject_id', $record->id)->exists()) {
            $candidate = clone $record;
            $candidate->teacher_id = $data['teacher_id'] ?? null;
            $candidate->school_class_id = $data['school_class_id'];
            foreach (Schedule::where('subject_id', $record->id)->get() as $slot) {
                if (! TeacherTimetable::withinHours($candidate, $slot->starts_at, $slot->ends_at) || TimetableConflicts::exists($candidate, $slot->day, $slot->starts_at, $slot->ends_at, $slot->room, $slot->id)) {
                    throw ValidationException::withMessages(['teacher_id' => 'The teacher already has another class during this subject timetable.']);
                }
            }
        }
        if ($record->exists && $module === 'examinations' && Grade::where('examination_id', $record->id)->exists()) {
            if ($data['total_marks'] < Grade::where('examination_id', $record->id)->max('marks')) {
                throw ValidationException::withMessages(['total_marks' => 'The examination total cannot be lower than marks already awarded.']);
            }
            if ((int) $data['subject_id'] !== $record->subject_id) {
                throw ValidationException::withMessages(['subject_id' => 'An examination with published results cannot be moved to another subject.']);
            }
        }
        if ($record->exists && $module === 'subjects' && (int) $data['school_class_id'] !== $record->school_class_id && Examination::where('subject_id', $record->id)->exists()) {
            throw ValidationException::withMessages(['school_class_id' => 'A subject with examinations cannot be moved to another class.']);
        }
        if ($module === 'grades') {
            $exam = Examination::with('subject')->findOrFail($data['examination_id']);
            $student = Student::findOrFail($data['student_id']);
            if ($exam->subject->school_class_id !== $student->school_class_id || $data['marks'] > $exam->total_marks) {
                throw ValidationException::withMessages(['marks' => 'Choose an exam for this student’s class and marks within the examination total.']);
            }
        }
        if ($record instanceof User && ($data['program'] ?? null) === 'Chinese') {
            if (Schedule::whereHas('subject', fn ($q) => $q->where('teacher_id', $record->id))->where(fn ($q) => $q->where('starts_at', '<', '16:30')->orWhere('ends_at', '>', '18:30'))->exists()) {
                throw ValidationException::withMessages(['program' => 'Move this teacher?s lessons into the evening before selecting Chinese.']);
            }
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
