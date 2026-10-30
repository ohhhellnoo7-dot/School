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
        ]);
        $moment = $this->moment($filters);
        if ($request->boolean('live')) $moment = CarbonImmutable::now(config('app.timezone'));
        $academicYear = $filters['academic_year'] ?? $moment->year.'-'.($moment->year+1);
        $snapshot = collect($availabilityService->forMoment($moment, ['search' => $classroom->room_number,'academic_year'=>$academicYear]))
            ->first(fn ($entry) => (int) data_get($entry, 'classroom.id') === (int) $classroom->id);

        $classroom->load(['bookings' => fn ($query) => $query
            ->with(['classGroup.teacher:id,name', 'classSession', 'teacher:id,name'])
            ->orderByRaw("CASE day_of_week WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 ELSE 6 END")
            ->orderBy('starts_at')]);

        $visibleCohorts = in_array($request->user()->role, ['student', 'parent'], true)
            ? \App\Models\SchoolClass::whereIn('id', \App\Models\Student::visibleTo($request->user())->select('school_class_id'))->get() : null;
        if ($visibleCohorts !== null) $classroom->setRelation('bookings', $classroom->bookings->filter(fn ($b) => $visibleCohorts->contains(fn ($c) => $c->class_group_id === $b->class_group_id && $c->session === $b->classSession?->session)));

        $weekly = collect(self::SESSIONS)->mapWithKeys(function (string $session) use ($classroom) {
            return [$session => collect(self::DAYS)->mapWithKeys(fn (string $day) => [
                $day => $classroom->bookings->filter(fn (ClassroomBooking $booking) => $booking->day_of_week === $day && $booking->classSession?->session === $session),
            ])];
        });

        $lessons = app(\App\Services\RoomLessonService::class)->lessons($classroom, \App\Models\Schedule::with(['schoolClass', 'subject', 'teacher'])->where(fn ($q)=>$q->where('academic_year',$academicYear)->orWhereNull('academic_year'))->when($visibleCohorts !== null, fn ($q) => $q->whereIn('school_class_id', $visibleCohorts->modelKeys()))->get());
        return view('school.classrooms.show', [
            'classroom' => $classroom,
            'snapshot' => $snapshot,
            'moment' => $moment,
            'weekly' => $weekly,
            'days' => self::DAYS,
            'sessions' => self::SESSIONS,
            'lessons' => $lessons,
        ]);
    }

    public function lessonStatus(Request $request, Classroom $classroom, ClassroomAvailabilityService $availabilityService): JsonResponse
    {
        abort_unless($this->visibleClassrooms($request->user())->whereKey($classroom)->exists(), 403);
        $filters = $request->validate(['date' => 'nullable|date_format:Y-m-d', 'time' => 'nullable|date_format:H:i']);
        $moment = $request->boolean('live') ? CarbonImmutable::now(config('app.timezone')) : $this->moment($filters);
        $snapshot = $availabilityService->forMoment($moment)->first(fn ($row) => $row['classroom']->id === $classroom->id);
        return response()->json(['html' => view('school.classrooms._lesson-status', compact('snapshot'))->render(), 'label' => $moment->format('D, M j, Y H:i')])->header('Cache-Control', 'no-store');
    }

    public function create(): View
    {
        return view('school.classrooms.form', [
            'classroom' => new Classroom(['capacity' => 30, 'status' => 'active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $classroom = Classroom::create($this->validatedClassroom($request));

        return redirect()->route('classrooms.show', $classroom)
            ->with('success', "Room {$classroom->room_number} was created.");
    }

    public function edit(Classroom $classroom): View
    {
        return view('school.classrooms.form', compact('classroom'));
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        app(\App\Services\TeacherManagementService::class)->locked(fn () => $classroom->update($this->validatedClassroom($request, $classroom)));

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
            'program' => ['nullable', Rule::in(['Khmer', 'English', 'Chinese'])],
            'grade' => ['nullable', 'integer', 'between:1,12'],
            'session' => ['nullable', Rule::in(self::SESSIONS)],
            'status' => ['nullable', Rule::in(['available', 'busy', 'upcoming', 'out_of_service'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'room' => ['nullable', 'string', 'max:100'],
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
                $rooms->whereHas('lessons.subject', fn ($q) => $q->where('teacher_id', $user->id))
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
