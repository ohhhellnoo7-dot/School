<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Support\SchoolDependencies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        if ($request->input('program') === 'Chinese') {
            $request->merge(['session' => 'Evening']);
        }
        if ($request->input('program') === 'Khmer' && $request->input('session') === 'Evening') {
            $request->merge(['session' => null]);
        }
        $request->validate(['program' => ['nullable', Rule::in(\App\Models\Program::names())], 'session' => 'nullable|in:Morning,Afternoon,Evening', 'search' => 'nullable|string|max:255', 'class' => 'nullable|integer|exists:school_classes,id', 'status' => 'nullable|in:active,inactive,graduated', 'sort' => 'nullable|in:name,student_number,gender,date_of_birth,enrollment_date,email,phone,status,school_class_id', 'direction' => 'nullable|in:asc,desc']);
        // A class from a previous program/session selection must not hide matching students.
        if ($request->filled('class') && ! SchoolClass::whereKey($request->input('class'))
            ->when($request->program, fn ($q, $program) => $q->where('program', $program))
            ->when($request->session, fn ($q, $session) => $q->where('session', $session))->exists()) {
            $request->merge(['class' => null]);
        }
        $request->validate(['attendance_date' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'attendance_status' => 'nullable|in:present,absent,late,excused,unrecorded']);
        $attendanceDate = $request->input('attendance_date', today()->toDateString());
        $studentQuery = Student::visibleTo($request->user())->with(['schoolClass', 'studyClasses', 'user', 'parent',
            'attendances' => fn ($query) => $query->whereDate('date', $attendanceDate)])
            ->search($request->input('search'))
            ->when($request->class || $request->program || $request->session, fn ($q) => $q->whereHas('studyClasses', fn ($classes) => $classes
                ->when($request->class, fn ($c, $id) => $c->where('school_classes.id', $id))
                ->when($request->program, fn ($c, $program) => $c->where('program', $program))
                ->when($request->session, fn ($c, $session) => $c->where('session', $session))))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status));
        // Counts span every matching student, independent of pagination and the status tab.
        $attendanceCounts = array_fill_keys(['total', 'present', 'absent', 'late', 'excused', 'unrecorded'], 0);
        $attendanceCounts['total'] = (clone $studentQuery)->count();
        $dailyCounts = Attendance::whereDate('date', $attendanceDate)
            ->whereIn('student_id', (clone $studentQuery)->select('students.id'))
            ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        foreach (['present', 'absent', 'late', 'excused'] as $status) $attendanceCounts[$status] = (int) $dailyCounts->get($status, 0);
        $attendanceCounts['unrecorded'] = $attendanceCounts['total'] - array_sum(array_intersect_key($attendanceCounts, array_flip(['present', 'absent', 'late', 'excused'])));
        $students = $studentQuery
            ->when($request->attendance_status, function ($query, $status) use ($attendanceDate) {
                return $status === 'unrecorded'
                    ? $query->whereDoesntHave('attendances', fn ($q) => $q->whereDate('date', $attendanceDate))
                    : $query->whereHas('attendances', fn ($q) => $q->whereDate('date', $attendanceDate)->where('status', $status));
            })
            ->orderBy($request->input('sort', 'name'), $request->input('direction', 'asc'))->orderBy('id')->paginate(12)->withQueryString();

        $visibleStudents = Student::visibleTo($request->user());
        $studentSummary = [
            'total' => (clone $visibleStudents)->count(),
            'male' => (clone $visibleStudents)->where('gender', 'male')->count(),
            'female' => (clone $visibleStudents)->where('gender', 'female')->count(),
            'new' => (clone $visibleStudents)->whereBetween('enrollment_date', [now()->startOfMonth()->toDateString(), now()->toDateString()])->count(),
        ];

        return view('school.students.index', ['students' => $students, 'attendanceDate' => $attendanceDate, 'attendanceCounts' => $attendanceCounts, 'studentSummary' => $studentSummary, 'classes' => SchoolClass::orderBy('name')->get()]);
    }

    public function saveAttendance(Request $request, Student $student)
    {
        Gate::authorize('update', $student);
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'status' => 'required|in:present,absent,late,excused',
        ]);
        DB::transaction(function () use ($student, $data) {
            Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $attendance = Attendance::where('student_id', $student->id)->whereDate('date', $data['date'])->first()
                ?? new Attendance(['student_id' => $student->id, 'date' => $data['date']]);
            $attendance->fill(['status' => $data['status']])->save();
        });
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'status' => $data['status'], 'message' => 'Attendance saved.']);
        }

        return back()->with('success', 'Student attendance saved.');
    }

    public function create()
    {
        Gate::authorize('create', Student::class);

        return $this->form(new Student);
    }

    public function edit(Student $student)
    {
        Gate::authorize('update', $student);

        return $this->form($student);
    }

    private function form(Student $student)
    {
        return view('school.students.form', ['student' => $student, 'classes' => SchoolClass::where(fn ($q) => $q->whereNull('program')->orWhere('program', '!=', 'Chinese')->orWhere('session', 'Evening'))->orderBy('name')->get(), 'parents' => User::where('role', 'parent')->get(), 'accounts' => User::where('role', 'student')->get()]);
    }

    public function show(Student $student)
    {
        Gate::authorize('view', $student);

        return view('school.students.show', ['student' => $student->load(['schoolClass', 'studyClasses', 'user', 'parent', 'attendances' => fn ($q) => $q->latest('date')->limit(30), 'grades.examination.subject', 'fees']),
            'classes' => SchoolClass::orderBy('program')->orderBy('name')->get()]);
    }

    public function registerSession(Request $request, Student $student)
    {
        Gate::authorize('registerSession', $student);
        $data = $request->validate(['additional_class_id' => 'required|integer|exists:school_classes,id']);
        app(\App\Services\TeacherManagementService::class)->locked(function () use ($student, $data) {
            $additional = $student->studyClasses()->where('school_classes.id', '!=', $student->school_class_id)->pluck('school_classes.id')->all();
            $additional[] = $data['additional_class_id'];
            app(\App\Services\StudentStudySessionService::class)->sync($student, $additional);
        });
        return redirect()->route('students.show', $student)->with('success', 'Additional study session registered. Your existing classes are unchanged.');
    }

    private function syncStudySessions(Request $request, Student $student): void
    {
        if (!$request->has('study_sessions_submitted')) {
            $existing = $student->studyClasses()->where('school_classes.id', '!=', $student->school_class_id)->pluck('school_classes.id')->all();
            if ($existing) app(\App\Services\StudentStudySessionService::class)->sync($student, $existing);
            return;
        }
        $data = $request->validate([
            'additional_class_ids' => 'nullable|array|max:2',
            'additional_class_ids.*' => 'required|integer|distinct|exists:school_classes,id',
        ]);
        app(\App\Services\StudentStudySessionService::class)->sync($student, $data['additional_class_ids'] ?? []);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Student::class);
        $data = $this->validated($request);
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('students', 'public');
        }
        $request->validate(['create_test_fee' => 'nullable|boolean']);
        $accountData = $request->validate([
            'guardian_email' => 'nullable|required_if:create_accounts,1|email|max:255|different:email',
            'student_password' => 'nullable|string|min:3|max:255',
            'parent_password' => 'nullable|string|min:3|max:255',
            'guardian_gender' => 'nullable|in:male,female,other',
            'guardian_address' => 'nullable|string|max:2000',
        ]);
        $credentials = [];
        $student = app(\App\Services\TeacherManagementService::class)->locked(function () use ($data, $request, $accountData, &$credentials) {
            $data['user_id'] = $this->enrollmentAccount($data['user_id'] ?? null, $data['email'], $data['name'], 'student', $accountData['student_password'] ?? null, $credentials);
            $data['parent_id'] = $this->enrollmentAccount($data['parent_id'] ?? null, $accountData['guardian_email'] ?? null, $data['guardian_name'], 'parent', $accountData['parent_password'] ?? null, $credentials);
            $this->saveParentDetails($data['parent_id'], $data['guardian_phone'], $accountData);
            $sequence = DB::table('student_number_sequences')->where('id', 1)->lockForUpdate()->first();
            $number = $sequence->last_number;
            do {
                $data['student_number'] = 'STU'.str_pad(++$number, 3, '0', STR_PAD_LEFT);
            } while (Student::where('student_number', $data['student_number'])->exists());
            DB::table('student_number_sequences')->where('id', 1)->update(['last_number' => $number]);
            $student = Student::create($data);
            $this->syncStudySessions($request, $student);
            if ($request->boolean('create_test_fee')) {
                $student->fees()->create(['description' => 'QR payment test (USD 0.10)', 'amount' => '0.10', 'paid' => 0, 'due_date' => today()]);
            }

            return $student;
        });

        $this->saveParentPhoto($request, $student);

        if ($request->boolean('create_test_fee')) {
            return redirect()->route('fees.pay', $student->fees()->latest('id')->first()->id)->with('success', 'Student enrolled. Test fee created: $0.10 USD, awaiting payment.')->with('enrollment_accounts', $credentials);
        }

        return redirect()->route('students.show', $student)->with('success', 'Student enrolled successfully.')->with('enrollment_accounts', $credentials);
    }

    private function saveParentDetails(?int $parentId, string $phone, array $details): void
    {
        if (! $parentId) return;
        $parent = User::findOrFail($parentId);
        $parent->phone = $phone;
        // Blank optional fields preserve details on an already linked account.
        if (! empty($details['guardian_gender'])) $parent->gender = $details['guardian_gender'];
        if (! empty($details['guardian_address'])) $parent->address = $details['guardian_address'];
        $parent->save();
        $parent->ensureParentProfileNumber();
    }

    private function saveParentPhoto(Request $request, Student $student): void
    {
        if (! $request->hasFile('parent_photo')) {
            return;
        }
        $parent = $student->parent;
        $oldPath = $parent->profile_photo_path;
        $path = $request->file('parent_photo')->store('profile-photos', 'local');
        abort_unless($path, 500, 'Unable to save the parent photo. Please try again.');
        try {
            $parent->forceFill(['profile_photo_path' => $path])->save();
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }
    }

    private function enrollmentAccount(?int $id, ?string $email, string $name, string $role, ?string $password, array &$credentials): ?int
    {
        $field = $role === 'student' ? 'email' : 'guardian_email';
        $account = $id ? User::findOrFail($id) : ($email ? User::whereRaw('LOWER(email) = ?', [strtolower($email)])->first() : null);
        if (! $account && ! $email) {
            return null;
        }
        if ($account && $email && strcasecmp($account->email, $email) !== 0) {
            throw ValidationException::withMessages([$field => 'The selected account must match this email.']);
        }
        if ($account && ($account->role !== $role || ($role === 'student' && Student::where('user_id', $account->id)->exists()))) {
            throw ValidationException::withMessages([$field => 'This email or account is already used by another role or student.']);
        }
        $created = ! $account;
        if ($created) {
            $password = $password ?: (string) random_int(100000, 999999);
            $account = new User(['name' => $name, 'email' => strtolower($email), 'password' => $password]);
            $account->role = $role;
            $account->save();
        }
        $credentials[] = ['role' => ucfirst($role), 'email' => $account->email, 'password' => $created ? $password : null,
            'url' => route('login', ['email' => $account->email])];

        return $account->id;
    }

    public function update(Request $request, Student $student)
    {
        Gate::authorize('update', $student);
        $data = $this->validated($request, $student);
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('students', 'public');
            if ($student->photo) {
                Storage::disk('public')->delete($student->photo);
            }
        } else {
            unset($data['photo']);
        }
        $parentDetails = $request->validate(['guardian_gender' => 'nullable|in:male,female,other', 'guardian_address' => 'nullable|string|max:2000']);
        app(\App\Services\TeacherManagementService::class)->locked(function () use ($student, $data, $parentDetails, $request) {
            $student->update($data);
            $this->syncStudySessions($request, $student);
            $this->saveParentDetails($student->parent_id, $data['guardian_phone'], $parentDetails);
        });
        $this->saveParentPhoto($request, $student);

        return redirect()->route('students.show', $student)->with('success', 'Student updated.');
    }

    public function destroy(Student $student)
    {
        Gate::authorize('delete', $student);
        if (SchoolDependencies::hasDependents($student)) {
            return back()->withErrors(['record' => 'This student has attendance, grades or fees. Reassign or remove those records before deleting the student.']);
        }
        if ($student->photo) {
            Storage::disk('public')->delete($student->photo);
        }
        $student->delete();

        return redirect()->route('students.index')->with('success', 'Student and related records deleted.');
    }

    private function validated(Request $request, ?Student $student = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255', 'gender' => 'required|in:male,female,other',
            'date_of_birth' => 'required|date|before:today',
            'email' => ['required', 'email', 'max:255', Rule::unique('students')->ignore($student)],
            'phone' => 'nullable|string|max:30', 'address' => 'nullable|string|max:2000',
            'school_class_id' => 'required|exists:school_classes,id',
            'parent_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'parent')],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'student'), Rule::unique('students')->ignore($student)],
            'guardian_relationship' => 'nullable|in:father,mother,guardian',
            'guardian_name' => 'required|string|max:255', 'guardian_phone' => 'required|string|max:30',
            'enrollment_date' => 'required|date', 'status' => 'required|in:active,inactive,graduated',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'parent_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        unset($data['parent_photo']);
        if ($request->hasFile('parent_photo') && ! $request->filled('parent_id') && ($student || ! $request->filled('guardian_email'))) {
            throw ValidationException::withMessages(['parent_photo' => 'Enter a parent email or select a linked parent account before uploading their photo.']);
        }
        $class = SchoolClass::findOrFail($data['school_class_id']);
        if ($class->program === 'Chinese' && $class->session !== 'Evening') {
            throw ValidationException::withMessages(['school_class_id' => 'Chinese students must enroll in an Evening class.']);
        }
        if ($student && (int) $data['school_class_id'] !== $student->school_class_id && $student->grades()->exists()) {
            throw ValidationException::withMessages(['school_class_id' => 'A student with examination results cannot be moved to another class without archiving those results.']);
        }

        return $data;
    }
}
