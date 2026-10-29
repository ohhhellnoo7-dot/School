<?php

namespace Tests\Feature;

use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\Classroom;
use App\Models\ClassroomBooking;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\AutomaticClassroomAssignmentService;
use App\Services\ClassroomAvailabilityService;
use App\Services\ClassroomBookingService;
use Database\Seeders\ClassroomManagementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClassroomManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_is_idempotent_builds_the_exact_school_structure_and_preserves_students(): void
    {
        $morning = SchoolClass::create([
            'name' => 'Grade 4',
            'section' => 'A',
            'program' => 'English',
            'session' => 'Morning',
        ]);
        $afternoon = SchoolClass::create([
            'name' => 'Grade 4',
            'section' => 'A',
            'program' => 'English',
            'session' => 'Afternoon',
        ]);
        $student = Student::factory()->create(['school_class_id' => $morning->id]);
        $studentCount = Student::count();

        $this->seed(ClassroomManagementSeeder::class);

        $this->assertSame(54, ClassGroup::count());
        $this->assertSame(108, ClassSession::count());
        $this->assertSame(42, Classroom::count());
        $this->assertSame(540, ClassroomBooking::count());
        $this->assertSame($studentCount, Student::count());
        $this->assertSame($morning->id, $student->fresh()->school_class_id);
        $this->assertNotNull($morning->fresh()->class_group_id);
        $this->assertSame($morning->fresh()->class_group_id, $afternoon->fresh()->class_group_id);

        $khmer = ClassGroup::where('program', 'Khmer')->with('sessions')->get();
        $english = ClassGroup::where('program', 'English')->with('sessions')->get();
        $chinese = ClassGroup::where('program', 'Chinese')->with('sessions')->get();

        $this->assertCount(30, $khmer);
        $this->assertCount(12, $english);
        $this->assertCount(12, $chinese);
        $this->assertEqualsCanonicalizing(range(7, 12), $khmer->pluck('grade')->unique()->all());
        foreach (range(7, 12) as $grade) {
            $this->assertEqualsCanonicalizing(
                ['A', 'B', 'C', 'D', 'E'],
                $khmer->where('grade', $grade)->pluck('section')->all(),
            );
        }
        $this->assertEqualsCanonicalizing(range(1, 12), $english->pluck('grade')->all());
        $this->assertEqualsCanonicalizing(range(1, 12), $chinese->pluck('grade')->all());
        $this->assertTrue($english->every(fn (ClassGroup $group) => $group->section === null));
        $this->assertTrue($chinese->every(fn (ClassGroup $group) => $group->section === null));

        foreach ($khmer as $group) {
            $this->assertSessionDefinitions($group, [
                'Morning' => ['07:00', '11:00'],
                'Afternoon' => ['13:00', '17:00'],
            ]);
        }
        foreach ($english as $group) {
            $this->assertSessionDefinitions($group, [
                'Morning' => ['07:00', '11:00'],
                'Afternoon' => ['13:00', '17:00'],
                'Evening' => ['17:30', '18:30'],
            ]);
        }
        foreach ($chinese as $group) {
            $this->assertSessionDefinitions($group, [
                'Evening' => ['17:30', '18:30'],
            ]);
        }

        $expectedRoomNumbers = collect(range(7, 12))
            ->flatMap(fn (int $grade) => collect(['A', 'B', 'C', 'D', 'E'])->map(fn (string $section) => $grade.$section))
            ->merge(collect(range(1, 12))->map(fn (int $room) => sprintf('E%02d', $room)))
            ->all();
        $this->assertEqualsCanonicalizing($expectedRoomNumbers, Classroom::pluck('room_number')->all());

        $identitySnapshot = [
            'groups' => ClassGroup::orderBy('id')->pluck('id')->all(),
            'sessions' => ClassSession::orderBy('id')->pluck('id')->all(),
            'rooms' => Classroom::orderBy('id')->pluck('id')->all(),
            'bookings' => ClassroomBooking::orderBy('id')->pluck('id')->all(),
        ];

        $this->seed(ClassroomManagementSeeder::class);

        $this->assertSame($identitySnapshot['groups'], ClassGroup::orderBy('id')->pluck('id')->all());
        $this->assertSame($identitySnapshot['sessions'], ClassSession::orderBy('id')->pluck('id')->all());
        $this->assertSame($identitySnapshot['rooms'], Classroom::orderBy('id')->pluck('id')->all());
        $this->assertSame($identitySnapshot['bookings'], ClassroomBooking::orderBy('id')->pluck('id')->all());
        $this->assertSame($studentCount, Student::count());
        $this->assertSame($morning->id, $student->fresh()->school_class_id);
    }

    public function test_one_class_identity_owns_multiple_sessions_instead_of_becoming_duplicate_classes(): void
    {
        $group = $this->classGroup('English', 6, null);
        $morning = $this->classSession($group, 'Morning', '07:00', '11:00');
        $afternoon = $this->classSession($group, 'Afternoon', '13:00', '17:00');
        $evening = $this->classSession($group, 'Evening', '17:30', '18:30');

        $this->assertSame(1, ClassGroup::where('program', 'English')->where('grade', 6)->count());
        $this->assertSame($group->id, $morning->class_group_id);
        $this->assertSame($group->id, $afternoon->class_group_id);
        $this->assertSame($group->id, $evening->class_group_id);
        $this->assertEqualsCanonicalizing(
            ['Morning', 'Afternoon', 'Evening'],
            $group->sessions()->pluck('session')->all(),
        );
    }

    public function test_booking_service_rejects_room_teacher_and_class_overlaps_but_reuses_resources_later(): void
    {
        $teacherA = $this->user('teacher', ['program' => 'Khmer']);
        $teacherB = $this->user('teacher', ['program' => 'Khmer']);
        $firstGroup = $this->classGroup('Khmer', 7, 'A', $teacherA);
        $secondGroup = $this->classGroup('Khmer', 7, 'B', $teacherB);
        $firstMorning = $this->classSession($firstGroup, 'Morning', '07:00', '11:00');
        $secondMorning = $this->classSession($secondGroup, 'Morning', '07:00', '11:00');
        $secondAfternoon = $this->classSession($secondGroup, 'Afternoon', '13:00', '17:00');
        $roomA = $this->classroom('T01');
        $roomB = $this->classroom('T02');
        $service = app(ClassroomBookingService::class);

        $first = $service->create($this->bookingData($firstGroup, $firstMorning, $roomA, $teacherA));

        $this->assertValidationError('classroom_id', fn () => $service->create(
            $this->bookingData($secondGroup, $secondMorning, $roomA, $teacherB),
        ));
        $this->assertValidationError('teacher_id', fn () => $service->create(
            $this->bookingData($secondGroup, $secondMorning, $roomB, $teacherA),
        ));
        $this->assertValidationError('class_group_id', fn () => $service->create(
            $this->bookingData($firstGroup, $firstMorning, $roomB, $teacherB),
        ));

        $later = $service->create($this->bookingData(
            $secondGroup,
            $secondAfternoon,
            $roomA,
            $teacherA,
            startsAt: '13:00',
            endsAt: '17:00',
        ));

        $this->assertSame($roomA->id, $first->classroom_id);
        $this->assertSame($roomA->id, $later->classroom_id);
        $this->assertSame($teacherA->id, $first->teacher_id);
        $this->assertSame($teacherA->id, $later->teacher_id);
        $this->assertSame(2, ClassroomBooking::count());
    }

    public function test_availability_is_calculated_from_bookings_and_supports_operational_filters(): void
    {
        $busyGroup = $this->classGroup('Khmer', 7, 'A');
        $upcomingGroup = $this->classGroup('English', 7, null);
        $busySession = $this->classSession($busyGroup, 'Morning', '07:00', '11:00');
        $upcomingSession = $this->classSession($upcomingGroup, 'Morning', '07:30', '11:00');
        $busyRoom = $this->classroom('BUSY');
        $upcomingRoom = $this->classroom('NEXT');
        $availableRoom = $this->classroom('OPEN');
        $outOfServiceRoom = $this->classroom('CLOSED', status: 'out_of_service');
        $bookingService = app(ClassroomBookingService::class);
        $bookingService->create($this->bookingData($busyGroup, $busySession, $busyRoom));
        $bookingService->create($this->bookingData(
            $upcomingGroup,
            $upcomingSession,
            $upcomingRoom,
            startsAt: '07:30',
            endsAt: '11:00',
        ));

        $availability = app(ClassroomAvailabilityService::class);
        $rooms = $availability->forMoment('2026-11-02 07:00');

        $this->assertSame('busy', $this->availabilityStatus($rooms, $busyRoom));
        $this->assertSame('upcoming', $this->availabilityStatus($rooms, $upcomingRoom));
        $this->assertSame('available', $this->availabilityStatus($rooms, $availableRoom));
        $this->assertSame('out_of_service', $this->availabilityStatus($rooms, $outOfServiceRoom));

        $busyOnly = $availability->forMoment('2026-11-02 07:00', ['status' => 'busy']);
        $this->assertSame([$busyRoom->id], $this->availabilityRoomIds($busyOnly));

        $khmerOnly = $availability->forMoment('2026-11-02 07:00', ['program' => 'Khmer']);
        $this->assertSame([$busyRoom->id], $this->availabilityRoomIds($khmerOnly));

        $morningOnly = $availability->forMoment('2026-11-02 07:00', ['session' => 'Morning']);
        $this->assertEqualsCanonicalizing([$busyRoom->id, $upcomingRoom->id], $this->availabilityRoomIds($morningOnly));

        $openSearch = $availability->forMoment('2026-11-02 07:00', ['search' => 'OPEN']);
        $this->assertSame([$availableRoom->id], $this->availabilityRoomIds($openSearch));
    }

    public function test_class_and_classroom_pages_are_viewable_but_management_is_admin_only(): void
    {
        $teacher = $this->user('teacher');
        $group = $this->classGroup('Khmer', 8, 'A', $teacher);
        $session = $this->classSession($group, 'Morning', '07:00', '11:00');
        $room = $this->classroom('VIEW');
        app(ClassroomBookingService::class)->create($this->bookingData($group, $session, $room, $teacher));
        $administrator = $this->user('administrator');
        $parent = $this->user('parent');

        $this->get(route('class-groups.index'))->assertRedirect(route('login'));
        $this->get(route('classrooms.index'))->assertRedirect(route('login'));

        $this->actingAs($parent)->get(route('class-groups.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('class-groups.index'))->assertOk();
        $this->get(route('class-groups.show', $group))->assertOk();
        $this->get(route('classrooms.index'))->assertOk();
        $this->get(route('classrooms.show', $room))->assertOk();
        $this->get(route('class-groups.create'))->assertForbidden();
        $this->get(route('classrooms.create'))->assertForbidden();
        $this->post(route('classrooms.store'), [
            'name' => 'Forbidden room',
            'room_number' => 'NOPE',
            'capacity' => 40,
            'status' => 'active',
        ])->assertForbidden();
        $this->assertDatabaseMissing('classrooms', ['room_number' => 'NOPE']);

        $this->actingAs($administrator)->get(route('class-groups.create'))->assertOk();
        $this->get(route('classrooms.create'))->assertOk();
        $this->post(route('class-groups.store'), [
            'name' => 'Chinese Grade 2',
            'grade' => 2,
            'section' => null,
            'program' => 'Chinese',
            'capacity' => 30,
            'status' => 'active',
        ])->assertRedirect();
        $this->assertDatabaseHas('class_groups', ['program' => 'Chinese', 'grade' => 2, 'section' => null]);
        $created = ClassGroup::where('program', 'Chinese')->where('grade', 2)->firstOrFail();
        $this->assertSame(['Evening'], $created->sessions()->pluck('session')->all());

        $this->post(route('classrooms.store'), [
            'name' => 'Admin room',
            'room_number' => 'YES',
            'capacity' => 40,
            'status' => 'active',
        ])->assertRedirect();
        $this->assertDatabaseHas('classrooms', ['room_number' => 'YES']);
    }

    public function test_dashboard_counts_normalized_class_groups_once_regardless_of_sessions_or_legacy_rows(): void
    {
        $first = $this->classGroup('English', 5, null);
        $second = $this->classGroup('Chinese', 5, null);
        foreach ([['Morning', '07:00', '11:00'], ['Afternoon', '13:00', '17:00'], ['Evening', '17:30', '18:30']] as [$name, $start, $end]) {
            $this->classSession($first, $name, $start, $end);
        }
        $this->classSession($second, 'Evening', '17:30', '18:30');
        foreach (['Morning', 'Afternoon', 'Evening'] as $session) {
            SchoolClass::create([
                'name' => 'Grade 5',
                'section' => 'A',
                'program' => 'English',
                'session' => $session,
                'class_group_id' => $first->id,
            ]);
        }

        $administrator = $this->user('administrator');
        $this->actingAs($administrator)->getJson(route('dashboard.data'))
            ->assertOk()
            ->assertJsonPath('summary.classCount', 2);
    }

    public function test_automatic_assignment_creates_weekday_bookings_without_any_conflicts(): void
    {
        $firstTeacher = $this->user('teacher');
        $secondTeacher = $this->user('teacher');
        $first = $this->classGroup('Khmer', 9, 'A', $firstTeacher);
        $second = $this->classGroup('Khmer', 9, 'B', $secondTeacher);
        $this->classSession($first, 'Morning', '07:00', '11:00');
        $this->classSession($second, 'Morning', '07:00', '11:00');
        $this->classroom('AUTO-1');
        $this->classroom('AUTO-2');

        $created = app(AutomaticClassroomAssignmentService::class)->assignAll([$first->id, $second->id]);

        $this->assertCount(10, $created);
        $this->assertSame(10, ClassroomBooking::count());
        $this->assertEqualsCanonicalizing(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            $created->pluck('day_of_week')->unique()->all(),
        );
        $this->assertNoBookingConflicts($created);
    }

    public function test_automatic_assignment_rolls_everything_back_when_not_all_groups_can_fit(): void
    {
        $first = $this->classGroup('Khmer', 10, 'A', $this->user('teacher'));
        $second = $this->classGroup('Khmer', 10, 'B', $this->user('teacher'));
        $this->classSession($first, 'Morning', '07:00', '11:00');
        $this->classSession($second, 'Morning', '07:00', '11:00');
        $this->classroom('ONLY-ONE');

        $this->assertValidationError('assignment', fn () => app(AutomaticClassroomAssignmentService::class)
            ->assignAll([$first->id, $second->id]));

        $this->assertSame(0, ClassroomBooking::count());
        $this->assertSame(1, Classroom::count());
    }

    private function classGroup(
        string $program,
        int $grade,
        ?string $section,
        ?User $teacher = null,
        int $capacity = 30,
    ): ClassGroup {
        return ClassGroup::create([
            'name' => $program === 'Khmer' ? "Khmer Grade {$grade}{$section}" : "{$program} Grade {$grade}",
            'grade' => $grade,
            'section' => $section,
            'program' => $program,
            'teacher_id' => $teacher?->id,
            'capacity' => $capacity,
            'status' => 'active',
        ]);
    }

    private function classSession(
        ClassGroup $group,
        string $session,
        string $startsAt,
        string $endsAt,
    ): ClassSession {
        return ClassSession::create([
            'class_group_id' => $group->id,
            'session' => $session,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }

    private function classroom(string $number, int $capacity = 40, string $status = 'active'): Classroom
    {
        return Classroom::create([
            'name' => 'Room '.$number,
            'room_number' => $number,
            'capacity' => $capacity,
            'status' => $status,
        ]);
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    /** @return array<string, mixed> */
    private function bookingData(
        ClassGroup $group,
        ClassSession $session,
        Classroom $classroom,
        ?User $teacher = null,
        string $day = 'Monday',
        string $startsAt = '07:00',
        string $endsAt = '11:00',
    ): array {
        return [
            'class_group_id' => $group->id,
            'class_session_id' => $session->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher?->id,
            'day_of_week' => $day,
            'session' => $session->session,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    /** @param array<string, array{0: string, 1: string}> $expected */
    private function assertSessionDefinitions(ClassGroup $group, array $expected): void
    {
        $actual = $group->sessions->mapWithKeys(fn (ClassSession $session) => [
            $session->session => [$this->time($session->starts_at), $this->time($session->ends_at)],
        ])->all();

        $this->assertSame($expected, $actual, "Unexpected session rules for {$group->name}.");
    }

    private function assertValidationError(string $key, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected a validation error for {$key}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($key, $exception->errors());
        }
    }

    private function availabilityStatus(Collection $rooms, Classroom $classroom): string
    {
        $item = $rooms->first(fn ($item) => $this->availabilityRoomId($item) === $classroom->id);
        $this->assertNotNull($item, "Classroom {$classroom->room_number} is absent from availability.");

        return (string) data_get($item, 'status');
    }

    /** @return list<int> */
    private function availabilityRoomIds(Collection $rooms): array
    {
        return $rooms->map(fn ($item) => $this->availabilityRoomId($item))->sort()->values()->all();
    }

    private function availabilityRoomId(mixed $item): int
    {
        return (int) (data_get($item, 'classroom.id')
            ?? data_get($item, 'classroom_id')
            ?? data_get($item, 'id'));
    }

    private function assertNoBookingConflicts(Collection $bookings): void
    {
        foreach ($bookings as $booking) {
            $overlaps = $bookings->filter(fn (ClassroomBooking $candidate) => $candidate->id !== $booking->id
                && $candidate->day_of_week === $booking->day_of_week
                && $this->time($candidate->starts_at) < $this->time($booking->ends_at)
                && $this->time($candidate->ends_at) > $this->time($booking->starts_at));

            $this->assertFalse($overlaps->contains('classroom_id', $booking->classroom_id));
            $this->assertFalse($overlaps->contains('class_group_id', $booking->class_group_id));
            if ($booking->teacher_id !== null) {
                $this->assertFalse($overlaps->contains('teacher_id', $booking->teacher_id));
            }
        }
    }

    private function time(mixed $value): string
    {
        return substr((string) $value, 0, 5);
    }
}
