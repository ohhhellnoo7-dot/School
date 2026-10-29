<?php

namespace App\Services;

use App\Models\ClassGroup;
use App\Models\Classroom;
use App\Models\ClassroomBooking;
use App\Models\ClassSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ClassroomBookingService
{
    private const SNAPSHOT_FIELDS = [
        'class_group_id', 'class_session_id', 'classroom_id', 'teacher_id',
        'day_of_week', 'starts_at', 'ends_at',
    ];

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ClassroomBooking
    {
        try {
            return DB::transaction(function () use ($attributes) {
                $data = $this->validatedPayload($attributes);

                return $this->persist(new ClassroomBooking, $data);
            }, 3);
        } catch (QueryException $exception) {
            $this->convertConstraintException($exception);
        }
    }

    /** @param array<string, mixed> $attributes */
    public function update(ClassroomBooking $booking, array $attributes): ClassroomBooking
    {
        try {
            return DB::transaction(function () use ($booking, $attributes) {
                $snapshot = ClassroomBooking::query()->findOrFail($booking->getKey());
                $data = $this->validatedPayload(array_replace(
                    $snapshot->only(self::SNAPSHOT_FIELDS),
                    $attributes
                ));

                return $this->persist($snapshot, $data);
            }, 3);
        } catch (QueryException $exception) {
            $this->convertConstraintException($exception);
        }
    }

    public function cancel(ClassroomBooking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $snapshot = ClassroomBooking::query()->findOrFail($booking->getKey());
            $this->lockResources($snapshot->only(self::SNAPSHOT_FIELDS), $snapshot);
            $locked = $this->lockBooking($snapshot);
            $locked->delete();
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(ClassroomBooking $booking, array $data): ClassroomBooking
    {
        [$classGroup, $classSession, $classroom, $teacher] = $this->lockResources(
            $data,
            $booking->exists ? $booking : null
        );
        if ($booking->exists) {
            $booking = $this->lockBooking($booking);
        }

        $this->validateClassAndSession($classGroup, $classSession, $data);
        $this->validateClassroom($classGroup, $classroom);
        $this->validateTeacher($teacher);

        $startsMinute = ClassroomBooking::toMinute($data['starts_at']);
        $endsMinute = ClassroomBooking::toMinute($data['ends_at']);
        $this->validateConflicts(
            $booking,
            $classGroup,
            $classroom,
            $teacher,
            $data['day_of_week'],
            $startsMinute,
            $endsMinute
        );

        $booking->fill([
            ...$data,
            'starts_minute' => $startsMinute,
            'ends_minute' => $endsMinute,
        ])->save();

        return $booking->fresh(['classGroup', 'classSession', 'classroom', 'teacher']);
    }

    /**
     * Lock both the old and requested resources in a global table/id order.
     * This lets an edit safely move a booking without first taking a booking
     * row lock that could deadlock with a simultaneous create.
     *
     * @param  array<string, mixed>  $data
     * @return array{ClassGroup, ClassSession, Classroom, ?User}
     */
    private function lockResources(array $data, ?ClassroomBooking $previous): array
    {
        $groupIds = $this->resourceIds($data['class_group_id'], $previous?->class_group_id);
        $groups = ClassGroup::query()->whereKey($groupIds)->orderBy('id')->lockForUpdate()->get();
        $classGroup = $groups->firstWhere('id', (int) $data['class_group_id']);
        if (! $classGroup) {
            throw ValidationException::withMessages(['class_group_id' => 'The selected class group no longer exists.']);
        }

        $sessionIds = $this->resourceIds($data['class_session_id'], $previous?->class_session_id);
        $sessions = ClassSession::query()->whereKey($sessionIds)->orderBy('id')->lockForUpdate()->get();
        $classSession = $sessions->firstWhere('id', (int) $data['class_session_id']);
        if (! $classSession) {
            throw ValidationException::withMessages(['class_session_id' => 'The selected class session no longer exists.']);
        }

        $roomIds = $this->resourceIds($data['classroom_id'], $previous?->classroom_id);
        $rooms = Classroom::query()->whereKey($roomIds)->orderBy('id')->lockForUpdate()->get();
        $classroom = $rooms->firstWhere('id', (int) $data['classroom_id']);
        if (! $classroom) {
            throw ValidationException::withMessages(['classroom_id' => 'The selected classroom no longer exists.']);
        }

        $teacherIds = $this->resourceIds($data['teacher_id'], $previous?->teacher_id);
        $teachers = $teacherIds === []
            ? collect()
            : User::query()->whereKey($teacherIds)->orderBy('id')->lockForUpdate()->get();
        $teacher = $data['teacher_id'] === null
            ? null
            : $teachers->firstWhere('id', (int) $data['teacher_id']);
        if ($data['teacher_id'] !== null && ! $teacher) {
            throw ValidationException::withMessages(['teacher_id' => 'The selected teacher no longer exists.']);
        }

        return [$classGroup, $classSession, $classroom, $teacher];
    }

    /** @return list<int> */
    private function resourceIds(mixed ...$ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function lockBooking(ClassroomBooking $snapshot): ClassroomBooking
    {
        $locked = ClassroomBooking::query()->lockForUpdate()->findOrFail($snapshot->getKey());

        foreach (self::SNAPSHOT_FIELDS as $field) {
            $before = in_array($field, ['starts_at', 'ends_at'], true)
                ? substr((string) $snapshot->{$field}, 0, 5)
                : $snapshot->{$field};
            $current = in_array($field, ['starts_at', 'ends_at'], true)
                ? substr((string) $locked->{$field}, 0, 5)
                : $locked->{$field};

            if ((string) $before !== (string) $current) {
                throw ValidationException::withMessages([
                    'booking' => 'This booking changed while you were editing it. Refresh the schedule and try again.',
                ]);
            }
        }

        return $locked;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{class_group_id: int, class_session_id: int, classroom_id: int, teacher_id: ?int, day_of_week: string, starts_at: string, ends_at: string}
     */
    private function validatedPayload(array $attributes): array
    {
        foreach (['starts_at', 'ends_at'] as $field) {
            try {
                if (array_key_exists($field, $attributes)) {
                    $attributes[$field] = ClassroomBooking::normalizeTime($attributes[$field]);
                }
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([
                    $field => 'Use a valid 24-hour time such as 07:00 or 17:30.',
                ]);
            }
        }

        $data = Validator::make($attributes, [
            'class_group_id' => ['required', 'integer', Rule::exists('class_groups', 'id')],
            'class_session_id' => ['required', 'integer', Rule::exists('class_sessions', 'id')],
            'classroom_id' => ['required', 'integer', Rule::exists('classrooms', 'id')],
            'teacher_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'teacher')),
            ],
            'day_of_week' => ['required', 'string', Rule::in(config('classrooms.weekdays', []))],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
        ], [
            'day_of_week.in' => 'Classrooms may be booked from Monday through Friday only.',
        ])->validate();

        $data['class_group_id'] = (int) $data['class_group_id'];
        $data['class_session_id'] = (int) $data['class_session_id'];
        $data['classroom_id'] = (int) $data['classroom_id'];
        $data['teacher_id'] = filled($data['teacher_id'] ?? null) ? (int) $data['teacher_id'] : null;

        if (ClassroomBooking::toMinute($data['starts_at']) >= ClassroomBooking::toMinute($data['ends_at'])) {
            throw ValidationException::withMessages([
                'ends_at' => 'The booking end time must be after its start time.',
            ]);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function validateClassAndSession(ClassGroup $group, ClassSession $session, array $data): void
    {
        if ($group->status !== 'active') {
            throw ValidationException::withMessages([
                'class_group_id' => "{$group->display_name} is inactive and cannot be booked.",
            ]);
        }

        if ((int) $session->class_group_id !== (int) $group->getKey()) {
            throw ValidationException::withMessages([
                'class_session_id' => 'The selected session does not belong to this class group.',
            ]);
        }

        if (! in_array($session->session, ClassGroup::allowedSessions($group->program), true)) {
            throw ValidationException::withMessages([
                'class_session_id' => "{$group->program} classes cannot use the {$session->session} session.",
            ]);
        }

        $startsMinute = ClassroomBooking::toMinute($data['starts_at']);
        $endsMinute = ClassroomBooking::toMinute($data['ends_at']);
        $sessionStart = ClassroomBooking::toMinute($session->starts_at);
        $sessionEnd = ClassroomBooking::toMinute($session->ends_at);
        $configured = config("classrooms.sessions.{$session->session}");

        if (is_array($configured)) {
            $sessionStart = max($sessionStart, ClassroomBooking::toMinute($configured['starts_at']));
            $sessionEnd = min($sessionEnd, ClassroomBooking::toMinute($configured['ends_at']));
        }

        if ($startsMinute < $sessionStart || $endsMinute > $sessionEnd) {
            throw ValidationException::withMessages([
                'class_session_id' => sprintf(
                    '%s bookings must stay within %s-%s.',
                    $session->session,
                    substr((string) $session->starts_at, 0, 5),
                    substr((string) $session->ends_at, 0, 5)
                ),
            ]);
        }
    }

    private function validateClassroom(ClassGroup $group, Classroom $classroom): void
    {
        if ($classroom->status !== Classroom::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'classroom_id' => "Room {$classroom->room_number} is out of service.",
            ]);
        }

        $requiredCapacity = max((int) $group->capacity, $group->students()->count());
        if ((int) $classroom->capacity < $requiredCapacity) {
            throw ValidationException::withMessages([
                'classroom_id' => "Room {$classroom->room_number} seats {$classroom->capacity}; {$group->display_name} requires {$requiredCapacity} seats.",
            ]);
        }
    }

    private function validateTeacher(?User $teacher): void
    {
        if ($teacher === null) {
            return;
        }

        if ($teacher->role !== 'teacher') {
            throw ValidationException::withMessages([
                'teacher_id' => 'The selected user is not a teacher.',
            ]);
        }

        if (filled($teacher->status) && $teacher->status !== 'active') {
            throw ValidationException::withMessages([
                'teacher_id' => "{$teacher->name} is not an active teacher.",
            ]);
        }
    }

    private function validateConflicts(
        ClassroomBooking $booking,
        ClassGroup $group,
        Classroom $classroom,
        ?User $teacher,
        string $day,
        int $startsMinute,
        int $endsMinute
    ): void {
        $overlaps = ClassroomBooking::query()
            ->onDay($day)
            ->overlapping($startsMinute, $endsMinute)
            ->when($booking->exists, fn ($query) => $query->whereKeyNot($booking->getKey()));

        $roomConflict = (clone $overlaps)
            ->where('classroom_id', $classroom->getKey())
            ->with('classGroup:id,name,program,grade,section')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($roomConflict) {
            throw ValidationException::withMessages([
                'classroom_id' => sprintf(
                    'Room %s is already occupied by %s on %s from %s to %s.',
                    $classroom->room_number,
                    $roomConflict->classGroup?->display_name ?? 'another class',
                    $day,
                    substr((string) $roomConflict->starts_at, 0, 5),
                    substr((string) $roomConflict->ends_at, 0, 5)
                ),
            ]);
        }

        $classConflict = (clone $overlaps)
            ->where('class_group_id', $group->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($classConflict) {
            throw ValidationException::withMessages([
                'class_group_id' => "{$group->display_name} already has an overlapping classroom booking on {$day}.",
            ]);
        }

        if ($teacher !== null) {
            $teacherConflict = (clone $overlaps)
                ->where('teacher_id', $teacher->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($teacherConflict) {
                throw ValidationException::withMessages([
                    'teacher_id' => "{$teacher->name} already has an overlapping classroom booking on {$day}.",
                ]);
            }
        }
    }

    private function convertConstraintException(QueryException $exception): never
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        if (in_array($sqlState, ['23P01', '23505', '23514', '23000'], true)) {
            throw ValidationException::withMessages([
                'classroom_id' => 'That classroom assignment now conflicts with another booking. Refresh the schedule and choose another room or time.',
            ]);
        }

        throw $exception;
    }
}
