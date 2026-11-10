<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\ClassroomBooking;
use App\Models\User;
use App\Services\AutomaticClassroomAssignmentService;
use App\Services\ClassroomAvailabilityService;
use App\Services\ClassroomBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    private const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    private const SESSIONS = ['Morning', 'Afternoon', 'Evening'];

    public function index(Request $request, ClassroomAvailabilityService $availabilityService): View
    {
        $payload = $this->availabilityPayload($request, $availabilityService);

        return view('school.classrooms.index', $payload + [
            'roomNames' => $this->visibleClassrooms($request->user())
                ->orderBy('room_number')
                ->pluck('name', 'room_number'),
            'buildings' => \App\Models\Building::orderBy('name')->get(),
            'programs' => \App\Models\Program::names(),
        ]);
    }

    public function data(Request $request, ClassroomAvailabilityService $availabilityService): JsonResponse
    {
        $payload = $this->availabilityPayload($request, $availabilityService);

        return response()->json([
            'html' => view('school.classrooms._availability-results', $payload)->render(),
            'refreshed_at' => now()->timezone(config('app.timezone'))->toIso8601String(),
            'label' => $payload['moment']->format('D, M j, Y · H:i'),
        ]);
    }

    public function show(Request $request, Classroom $classroom, ClassroomAvailabilityService $availabilityService): View
    {
        abort_unless($this->visibleClassrooms($request->user())->whereKey($classroom)->exists(), 403);

        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'academic_year'=>['nullable','regex:/^\d{4}-\d{4}$/'],
            'program' => ['nullable', Rule::in(\App\Models\Program::names())],
            'session' => ['nullable', Rule::in(self::SESSIONS)],
            'attendance_month' => ['nullable', 'date_format:Y-m'],
            'attendance_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $moment = $this->moment($filters);
        if ($request->boolean('live')) $moment = CarbonImmutable::now(config('app.timezone'));
        $academicYear = $filters['academic_year'] ?? $moment->year.'-'.($moment->year+1);
        $snapshot = collect($availabilityService->forMoment($moment, ['search' => $classroom->room_number,'academic_year'=>$academicYear]))
            ->first(fn ($entry) => (int) data_get($entry, 'classroom.id') === (int) $classroom->id);

        $programOptions = collect(data_get($snapshot, 'programDetails', []))->keys();
        $selectedProgram = $programOptions->contains($filters['program'] ?? null)
            ? $filters['program'] : $programOptions->first();
        $sessionOptions = match ($selectedProgram) {
            'Chinese' => ['Evening'],
            'Khmer' => ['Morning', 'Afternoon'],
            default => self::SESSIONS,
        };
        $selectedSession = in_array($filters['session'] ?? null, $sessionOptions, true)
            ? $filters['session'] : $sessionOptions[0];
        $snapshot = $availabilityService->forMoment($moment, [
            'search' => $classroom->room_number, 'academic_year' => $academicYear,
            'detail_program' => $selectedProgram, 'detail_session' => $selectedSession,
        ])->first(fn ($entry) => (int) $entry['classroom']->id === (int) $classroom->id);

        $classroom->load(['bookings' => fn ($query) => $query
            ->with(['classGroup.teacher:id,name', 'classSession', 'teacher:id,name'])
            ->orderByRaw("CASE day_of_week WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 ELSE 6 END")
            ->orderBy('starts_at')]);

        $visibleCohorts = in_array($request->user()->role, ['student', 'parent'], true)
            ? \App\Models\SchoolClass::whereIn('id', \App\Models\SchoolClass::enrolledFor($request->user())->select('school_classes.id'))->get() : null;
        if ($visibleCohorts !== null) $classroom->setRelation('bookings', $classroom->bookings->filter(fn ($b) => $visibleCohorts->contains(fn ($c) => $c->class_group_id === $b->class_group_id && $c->session === $b->classSession?->session)));

        $classroom->setRelation('bookings', $classroom->bookings->filter(fn ($booking) =>
            $booking->classGroup?->program === $selectedProgram && $booking->classSession?->session === $selectedSession));

        $weekly = collect(self::SESSIONS)->mapWithKeys(function (string $session) use ($classroom) {
            return [$session => collect(self::DAYS)->mapWithKeys(fn (string $day) => [
                $day => $classroom->bookings->filter(fn (ClassroomBooking $booking) => $booking->day_of_week === $day && $booking->classSession?->session === $session),
            ])];
        });

        $lessons = app(\App\Services\RoomLessonService::class)->lessons($classroom, \App\Models\Schedule::with(['schoolClass', 'subject', 'teacher'])->where(fn ($q)=>$q->where('academic_year',$academicYear)->orWhereNull('academic_year'))->when($visibleCohorts !== null, fn ($q) => $q->whereIn('school_class_id', $visibleCohorts->map->timetableOwnerId()->unique()->all()))->get());
        $lessons = $lessons->filter(fn ($lesson) => $lesson->schoolClass?->program === $selectedProgram && $lesson->schoolClass?->session === $selectedSession);
        $roomClassIds = collect(data_get($snapshot, 'programDetails', []))->flatMap(fn ($details) => $details['class_ids'] ?? [])->unique()->values();
        $roomStudents = \App\Models\Student::visibleTo($request->user())->where('status', 'active')
            ->enrolledIn($roomClassIds)->with(['user', 'parent',
                'studyClasses' => fn ($q) => $q->whereIn('school_classes.id', $roomClassIds),
            ])->orderBy('name')->orderBy('id')->get();
        $attendanceMonth = CarbonImmutable::createFromFormat('!Y-m', $filters['attendance_month'] ?? substr($filters['attendance_date'] ?? $moment->toDateString(), 0, 7));
        $attendanceDate = $filters['attendance_date'] ?? $attendanceMonth->setDay(min($moment->day, $attendanceMonth->daysInMonth))->toDateString();
        $attendanceClasses = \App\Models\SchoolClass::whereIn('id', $roomClassIds)->with('teacher')->get();
        $classAttendance = app(\App\Services\ClassAttendanceReportService::class)
            ->forMonth($attendanceClasses, $roomStudents, $attendanceMonth);

        return view('school.classrooms.show', [
            'attendanceMonth' => $attendanceMonth,
            'attendanceDate' => $attendanceDate,
            'attendanceClasses' => $attendanceClasses,
            'classAttendance' => $classAttendance,
            'classroom' => $classroom,
            'snapshot' => $snapshot,
            'roomStudents' => $roomStudents,
            'moment' => $moment,
            'weekly' => $weekly,
            'days' => self::DAYS,
            'sessions' => [$selectedSession],
            'programOptions' => $programOptions,
            'sessionOptions' => $sessionOptions,
            'selectedProgram' => $selectedProgram,
            'selectedSession' => $selectedSession,
            'lessons' => $lessons,
            'assignedClassGroups' => $this->assignedClassGroups($classroom)->where('program', $selectedProgram),
        ]);
    }

    public function saveAttendance(Request $request, Classroom $classroom, ClassroomAvailabilityService $availabilityService): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['administrator', 'teacher'], true), 403);
        abort_unless($this->visibleClassrooms($request->user())->whereKey($classroom)->exists(), 403);
        $data = $request->validate([
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'attendance_date' => ['required', 'date_format:Y-m-d'],
            'academic_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'statuses' => ['required', 'array', 'max:500'],
            'statuses.*' => ['nullable', Rule::in(['present', 'absent', 'late', 'excused'])],
        ]);
        $class = \App\Models\SchoolClass::findOrFail($data['school_class_id']);
        $teacherId = $request->user()->id;
        abort_unless($request->user()->role === 'administrator' || $class->teacher_id === $teacherId
            || \App\Models\Subject::where('school_class_id', $class->timetableOwnerId())->where('teacher_id', $teacherId)->exists()
            || \App\Models\Schedule::where('school_class_id', $class->timetableOwnerId())->where('teacher_id', $teacherId)->exists(), 403);
        $snapshot = $availabilityService->forMoment($data['attendance_date'], [
            'academic_year' => $data['academic_year'] ?? substr($data['attendance_date'], 0, 4).'-'.((int) substr($data['attendance_date'], 0, 4) + 1),
            'detail_program' => $class->program, 'detail_session' => $class->session,
        ])->first(fn ($entry) => $entry['classroom']->id === $classroom->id);
        abort_unless(collect(data_get($snapshot, 'programDetails.'.$class->program.'.class_ids', []))->contains($class->id), 403);
        $studentIds = \App\Models\Student::visibleTo($request->user())->where('status', 'active')->enrolledIn([$class->id])->pluck('id');
        foreach (array_keys($data['statuses']) as $id) {
            if (! ctype_digit((string) $id) || ! $studentIds->contains((int) $id)) {
                throw ValidationException::withMessages(['statuses' => 'Only enrolled students visible to your account can be marked.']);
            }
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($data, $class, $teacherId) {
            foreach ($data['statuses'] as $studentId => $status) {
                $key = ['school_class_id' => $class->id, 'student_id' => $studentId, 'date' => $data['attendance_date']];
                if ($status === null || $status === '') {
                    \App\Models\ClassAttendance::where($key)->delete();
                } else {
                    \App\Models\ClassAttendance::updateOrCreate($key, ['status' => $status, 'recorded_by' => $teacherId]);
                }
            }
        });

        return redirect()->route('classrooms.show', [
            'classroom' => $classroom, 'program' => $class->program, 'session' => $class->session,
            'academic_year' => $data['academic_year'] ?? null,
            'attendance_month' => substr($data['attendance_date'], 0, 7), 'attendance_date' => $data['attendance_date'],
        ])->with('success', 'Class attendance saved.');
    }

    public function matchingClass(Request $request, Classroom $classroom, ClassroomAvailabilityService $availabilityService): View
    {
        abort_unless(in_array($request->user()->role, ['administrator', 'teacher'], true), 403);
        abort_unless($this->visibleClassrooms($request->user())->whereKey($classroom)->exists(), 403);
        $filters = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'academic_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'match_mode' => ['nullable', 'in:standard,any-level'],
        ]);
        $moment = $request->boolean('live') ? CarbonImmutable::now(config('app.timezone')) : $this->moment($filters);
        $entries = $availabilityService->forMoment($moment, array_filter(['academic_year' => $filters['academic_year'] ?? null]));
        $source = $entries->first(fn ($entry) => $entry['classroom']->id === $classroom->id);
        abort_unless($source, 404);
        $anyLevel = ($filters['match_mode'] ?? 'standard') === 'any-level';
        $matches = app(\App\Services\MatchingClassService::class)->find($source, $entries, $anyLevel);
        return view('school.classrooms.matching-class', compact('classroom', 'moment', 'source', 'matches', 'anyLevel'));
    }

    public function studyWith(Request $request, Classroom $classroom, ClassroomAvailabilityService $availabilityService): RedirectResponse
    {
        abort_unless(in_array($request->user()->role, ['administrator', 'teacher'], true), 403);
        abort_unless($this->visibleClassrooms($request->user())->whereKey($classroom)->exists(), 403);
        $filters = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i'],
            'academic_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'],
            'match_mode' => ['nullable', 'in:standard,any-level'],
            'source_schedule_id' => ['required', 'integer'],
            'target_classroom_id' => ['required', 'integer', 'different:classroom'],
            'target_schedule_id' => ['required', 'integer'],
        ]);
        $moment = $request->boolean('live') ? CarbonImmutable::now(config('app.timezone')) : $this->moment($filters);
        $filters['date'] = $moment->toDateString();
        $filters['time'] = $moment->format('H:i');
        $study = \Illuminate\Support\Facades\DB::transaction(function () use ($classroom, $filters, $moment, $request, $availabilityService) {
            // Serialize choices involving either room, including simultaneous reciprocal requests.
            Classroom::whereIn('id', [$classroom->id, $filters['target_classroom_id']])->orderBy('id')->lockForUpdate()->get();
            \App\Models\Schedule::whereIn('id', [$filters['source_schedule_id'], $filters['target_schedule_id']])->orderBy('id')->lockForUpdate()->get();
            $entries = $availabilityService->forMoment($moment, array_filter(['academic_year' => $filters['academic_year'] ?? null]));
            $source = $entries->first(fn ($entry) => $entry['classroom']->id === $classroom->id);
            $lesson = $source['currentLesson'] ?? null;
            if (! $lesson || $lesson->id !== (int) $filters['source_schedule_id']) {
                throw ValidationException::withMessages(['match' => 'The source lesson has changed. Find matching classes again.']);
            }
            $existing = \App\Models\TemporaryClassStudy::where('source_schedule_id', $lesson->id)->whereDate('date', $filters['date'])->first();
            if ($existing) {
                if ($existing->target_schedule_id === (int) $filters['target_schedule_id']) return $existing;
                throw ValidationException::withMessages(['match' => 'This class already has a study arrangement for this lesson.']);
            }
            $match = app(\App\Services\MatchingClassService::class)->find($source, $entries, ($filters['match_mode'] ?? 'standard') === 'any-level')
                ->first(fn ($entry) => $entry['classroom']->id === (int) $filters['target_classroom_id']
                    && $entry['currentLesson']->id === (int) $filters['target_schedule_id']);
            if (! $match) {
                throw ValidationException::withMessages(['match' => 'This class is no longer a matching option. Find matching classes again.']);
            }
            $receiving = $match['currentLesson'];
            $overlap = \App\Models\TemporaryClassStudy::whereDate('date', $filters['date'])
                ->where('starts_at', '<', substr($lesson->ends_at, 0, 8))
                ->where('ends_at', '>', $moment->format('H:i:s'))
                ->where(fn ($q) => $q->whereIn('source_classroom_id', [$classroom->id, $match['classroom']->id])
                    ->orWhere('target_classroom_id', $classroom->id))->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['match' => 'A class already has an overlapping study arrangement. Refresh the matching list.']);
            }
            $label = fn ($class) => trim(preg_replace('/^Grade\s*/i', '', $class->name).' '.$class->section);
            return \App\Models\TemporaryClassStudy::create([
                'source_schedule_id' => $lesson->id, 'target_schedule_id' => $receiving->id,
                'source_classroom_id' => $classroom->id, 'target_classroom_id' => $match['classroom']->id,
                'created_by' => $request->user()->id, 'date' => $filters['date'],
                'starts_at' => $moment->format('H:i:s'), 'ends_at' => $lesson->ends_at,
                'source_class' => $label($lesson->schoolClass), 'target_class' => $label($receiving->schoolClass),
                'subject' => $lesson->subject->name, 'teacher_name' => $receiving->teacher->name,
                'source_room' => $classroom->room_number, 'target_room' => $match['classroom']->room_number,
            ]);
        });
        return redirect()->route('classrooms.index', array_filter([
            'date' => $request->boolean('live') ? null : $filters['date'],
            'time' => $request->boolean('live') ? null : $filters['time'],
            'live' => $request->boolean('live') ? 1 : null, 'academic_year' => $filters['academic_year'] ?? null,
        ]))->with('success', $study->source_class.' is studying with '.$study->target_class.' for '.$study->subject.'. Return to Room '.$study->source_room.' at '.substr($study->ends_at, 0, 5).'.');
    }

    public function lessonStatus(Request $request, Classroom $classroom, ClassroomAvailabilityService $availabilityService): JsonResponse
    {
        abort_unless($this->visibleClassrooms($request->user())->whereKey($classroom)->exists(), 403);
        $filters = $request->validate(['date' => 'nullable|date_format:Y-m-d', 'time' => 'nullable|date_format:H:i', 'academic_year' => ['nullable', 'regex:/^\d{4}-\d{4}$/'], 'program' => ['nullable', Rule::in(\App\Models\Program::names())], 'session' => ['nullable', Rule::in(self::SESSIONS)]]);
        $moment = $request->boolean('live') ? CarbonImmutable::now(config('app.timezone')) : $this->moment($filters);
        $snapshot = $availabilityService->forMoment($moment, ['academic_year' => $filters['academic_year'] ?? $moment->year.'-'.($moment->year+1), 'detail_program' => $filters['program'] ?? null, 'detail_session' => ($filters['program'] ?? null) === 'Chinese' ? 'Evening' : ($filters['session'] ?? null)])->first(fn ($row) => $row['classroom']->id === $classroom->id);
        return response()->json(['html' => view('school.classrooms._lesson-status', compact('snapshot'))->render(), 'label' => $moment->format('D, M j, Y H:i')])->header('Cache-Control', 'no-store');
    }

    public function create(): View
    {
        return view('school.classrooms.form', [
            'classroom' => new Classroom(['capacity' => 30, 'status' => 'active']),
            ...app(\App\Services\ClassroomSetupService::class)->formData(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validatedClassroom($request);
        $classroom = app(\App\Services\ClassroomSetupService::class)->save($attributes, $request);

        return redirect()->route('classrooms.show', $classroom)
            ->with('success', "Room {$classroom->room_number} was created.");
    }

    public function edit(Classroom $classroom): View
    {
        return view('school.classrooms.form', [
            'classroom' => $classroom,
            'assignedClassGroups' => $this->assignedClassGroups($classroom),
        ] + app(\App\Services\ClassroomSetupService::class)->formData());
    }

    private function assignedClassGroups(Classroom $classroom): \Illuminate\Database\Eloquent\Collection
    {
        return ClassGroup::query()
            ->where(fn ($query) => $query
                ->whereHas('bookings', fn ($bookings) => $bookings->where('classroom_id', $classroom->id))
                ->orWhereHas('schoolClasses.schedules', fn ($lessons) => $lessons->where('classroom_id', $classroom->id)))
            ->orderBy('program')->orderBy('grade')->get();
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        $attributes = $this->validatedClassroom($request, $classroom);
        app(\App\Services\ClassroomSetupService::class)->save($attributes, $request, $classroom);

        return redirect()->route('classrooms.show', $classroom)
            ->with('success', "Room {$classroom->room_number} was updated.");
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        if ($classroom->bookings()->exists() || $classroom->lessons()->withoutGlobalScope('active')->exists()) {
            throw ValidationException::withMessages([
                'classroom' => 'This classroom has timetable bookings. Reassign or remove them before deleting the room.',
            ]);
        }

        $number = $classroom->room_number;
        $classroom->delete();

        return redirect()->route('classrooms.index')->with('success', "Room {$number} was deleted.");
    }

    public function bookingCreate(Request $request): View
    {
        $booking = new ClassroomBooking([
            'class_group_id' => $request->integer('class_group_id') ?: null,
            'classroom_id' => $request->integer('classroom_id') ?: null,
            'day_of_week' => 'Monday',
        ]);

        return view('school.classrooms.booking-form', $this->bookingFormData($booking));
    }

    public function bookingStore(Request $request, ClassroomBookingService $bookingService): RedirectResponse
    {
        $booking = $bookingService->create($this->validatedBooking($request));

        return redirect()->route('classrooms.show', $booking->classroom_id)
            ->with('success', 'The classroom booking was created without scheduling conflicts.');
    }

    public function bookingEdit(ClassroomBooking $classroomBooking): View
    {
        $classroomBooking->load(['classGroup', 'classSession', 'classroom', 'teacher']);

        return view('school.classrooms.booking-form', $this->bookingFormData($classroomBooking));
    }

    public function bookingUpdate(Request $request, ClassroomBooking $classroomBooking, ClassroomBookingService $bookingService): RedirectResponse
    {
        $booking = $bookingService->update($classroomBooking, $this->validatedBooking($request));

        return redirect()->route('classrooms.show', $booking->classroom_id)
            ->with('success', 'The classroom booking was updated without scheduling conflicts.');
    }

    public function bookingDestroy(ClassroomBooking $classroomBooking, ClassroomBookingService $bookingService): RedirectResponse
    {
        $classroomId = $classroomBooking->classroom_id;
        $bookingService->cancel($classroomBooking);

        return redirect()->route('classrooms.show', $classroomId)
            ->with('success', 'The classroom booking was removed.');
    }

    public function autoAssign(AutomaticClassroomAssignmentService $assignmentService): RedirectResponse
    {
        $result = $assignmentService->assignAll();
        $assigned = data_get($result, 'assigned', is_countable($result) ? count($result) : 0);
        $unassigned = data_get($result, 'unassigned', 0);
        $message = "Automatic assignment created {$assigned} classroom ".str('booking')->plural($assigned).'.';

        if ($unassigned) {
            $message .= " {$unassigned} session(s) could not be assigned because no conflict-free room had enough capacity.";
        }

        return redirect()->route('classrooms.index')->with('success', $message);
    }

    /** @return array<string, mixed> */
    private function availabilityPayload(Request $request, ClassroomAvailabilityService $availabilityService): array
    {
        $filters = $request->validate([
            'program' => ['nullable', Rule::in(\App\Models\Program::names())],
            'grade' => ['nullable', 'integer', 'between:1,12'],
            'session' => ['nullable', Rule::in(self::SESSIONS)],
            'room_sessions' => ['sometimes', 'array', 'max:500'],
            'room_sessions.*' => ['array', 'max:20'],
            'room_sessions.*.*' => ['string', Rule::in(self::SESSIONS)],
            'status' => ['nullable', Rule::in(['available', 'busy', 'upcoming', 'out_of_service'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'room' => ['nullable', 'string', 'max:100'],
            'building' => ['nullable', Rule::exists('buildings', 'code')],
        ]);
        $moment = $this->moment($filters);
        $allowedRoomIds = $this->visibleClassrooms($request->user())->pluck('id')->map(fn ($id) => (int) $id);
        if ($request->boolean('live')) $moment = CarbonImmutable::now(config('app.timezone'));
        $serviceFilters = $filters;
        $serviceFilters['search'] = $filters['room'] ?? null;
        $availability = collect($availabilityService->forMoment($moment, $serviceFilters))
            ->filter(fn ($entry) => $allowedRoomIds->contains((int) data_get($entry, 'classroom.id')))
            ->values();
        $stats = $availabilityService->summary($availability);

        return compact('availability', 'stats', 'filters', 'moment');
    }

    /** @param array<string, mixed> $filters */
    private function moment(array $filters): CarbonImmutable
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $date = $filters['date'] ?? $now->format('Y-m-d');
        $time = $filters['time'] ?? $now->format('H:i');

        return CarbonImmutable::createFromFormat('!Y-m-d H:i', "{$date} {$time}", config('app.timezone'));
    }

    /** @return array<string, mixed> */
    private function validatedClassroom(Request $request, ?Classroom $classroom = null): array
    {
        $attributes = $request->validate([
            'room_number' => [
                'required', 'string', 'max:30',
                Rule::unique('classrooms', 'room_number')->ignore($classroom),
            ],
            'name' => ['required', 'string', 'max:100'],
            'building' => ['required', 'string', Rule::in(\App\Models\Building::pluck('code')->push('__new')->all())],
            'new_building_name' => ['exclude_unless:building,__new', 'required', 'string', 'max:100', Rule::unique('buildings', 'name')],
            'capacity' => ['required', 'integer', 'between:1,500'],
            'status' => ['required', Rule::in(['active', 'out_of_service'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $attributes['room_number'] = strtoupper(trim($attributes['room_number']));
        $attributes['name'] = trim($attributes['name']);

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function validatedBooking(Request $request): array
    {
        $attributes = $request->validate([
            'class_group_id' => ['required', 'integer', Rule::exists('class_groups', 'id')],
            'class_session_id' => ['required', 'integer', Rule::exists('class_sessions', 'id')],
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')],
            'teacher_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'teacher')),
            ],
            'day_of_week' => ['required', Rule::in(self::DAYS)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
        ]);

        $session = ClassSession::query()
            ->whereKey($attributes['class_session_id'])
            ->where('class_group_id', $attributes['class_group_id'])
            ->first();
        if (! $session) {
            throw ValidationException::withMessages([
                'class_session_id' => 'The selected session does not belong to that class group.',
            ]);
        }

        $attributes['teacher_id'] ??= null;

        return $attributes;
    }

    /** @return array<string, mixed> */
    private function bookingFormData(ClassroomBooking $booking): array
    {
        return [
            'booking' => $booking,
            'classGroups' => ClassGroup::query()
                ->with(['sessions' => fn ($query) => $query->orderBy('starts_at'), 'teacher:id,name'])
                ->where('status', 'active')
                ->orderBy('program')
                ->orderBy('grade')
                ->orderBy('section')
                ->get(),
            'classrooms' => Classroom::query()->orderBy('room_number')->get(),
            'teachers' => User::query()->where('role', 'teacher')->orderBy('name')->get(['id', 'name', 'program']),
            'days' => self::DAYS,
        ];
    }

    private function visibleClassrooms(User $user): Builder
    {
        $query = Classroom::query();

        return match ($user->role) {
            'administrator' => $query,
            'teacher' => $query->where(function ($rooms) use ($user) {
                $rooms->whereHas('lessons', fn ($q) => $q->where('teacher_id', $user->id))
                    ->orWhereHas('lessons.subject', fn ($q) => $q->where('teacher_id', $user->id))
                    ->orWhereHas('bookings', function ($bookings) use ($user) {
                        $bookings->where('teacher_id', $user->id)
                            ->orWhereHas('classGroup', fn ($group) => $group->where('teacher_id', $user->id))
                            ->orWhereHas('classGroup.schoolClasses.subjects', fn ($q) => $q->where('teacher_id', $user->id)->whereHas('schedules'));
                    });
            }),
            'student' => $query->whereHas('bookings.classGroup.students', fn ($students) => $students->where('students.user_id', $user->id)),
            'parent' => $query->whereHas('bookings.classGroup.students', fn ($students) => $students->where('students.parent_id', $user->id)),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
