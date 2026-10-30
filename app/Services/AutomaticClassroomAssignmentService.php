<?php

namespace App\Services;

use App\Models\ClassGroup;
use App\Models\Classroom;
use App\Models\ClassroomBooking;
use App\Models\ClassSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutomaticClassroomAssignmentService
{
    public function __construct(private readonly ClassroomBookingService $bookings) {}

    /**
     * Assign every missing weekday class-session booking in one transaction.
     * Existing bookings and physical classroom records are never changed.
     *
     * @param  list<int>|null  $classGroupIds
     * @return Collection<int, ClassroomBooking>
     */
    public function assignAll(?array $classGroupIds = null): Collection
    {
        return DB::transaction(function () use ($classGroupIds) {
            DB::table('teacher_management_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            $groupsQuery = ClassGroup::query()
                ->active()
                ->with(['sessions' => fn ($query) => $query->orderBy('starts_at')])
                ->orderByDesc('capacity')
                ->orderBy('id')
                ->lockForUpdate();

            if ($classGroupIds !== null) {
                $classGroupIds = array_values(array_unique(array_map('intval', $classGroupIds)));
                $groupsQuery->whereKey($classGroupIds);
            }

            $groups = $groupsQuery->get();
            if ($classGroupIds !== null && $groups->count() !== count($classGroupIds)) {
                throw ValidationException::withMessages([
                    'assignment' => 'One or more selected class groups do not exist or are inactive.',
                ]);
            }
            if ($groups->isEmpty() || $groups->every(fn (ClassGroup $group) => $group->sessions->isEmpty())) {
                return collect();
            }

            // Match ClassroomBookingService's lock order: groups (and their
            // sessions) are locked before physical rooms.
            ClassSession::query()
                ->whereIn('class_group_id', $groups->modelKeys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $rooms = Classroom::query()
                ->operational()
                ->orderBy('capacity')
                ->orderBy('room_number')
                ->lockForUpdate()
                ->get();

            if ($rooms->isEmpty()) {
                throw ValidationException::withMessages([
                    'assignment' => 'No active physical classrooms exist. Add or restore a classroom before automatic assignment.',
                ]);
            }

            $requirements = $this->missingRequirements($groups);
            if ($requirements->isEmpty()) {
                return collect();
            }

            $created = collect();

            foreach ($requirements as $requirement) {
                /** @var ClassGroup $group */
                $group = $requirement['group'];
                /** @var ClassSession $session */
                $session = $requirement['session'];
                $day = $requirement['day'];
                $startsMinute = ClassroomBooking::toMinute($session->starts_at);
                $endsMinute = ClassroomBooking::toMinute($session->ends_at);
                $requiredCapacity = max((int) $group->capacity, $group->students()->count());

                $classroom = $rooms->first(function (Classroom $candidate) use (
                    $day,
                    $startsMinute,
                    $endsMinute,
                    $requiredCapacity
                ) {
                    if ($candidate->capacity < $requiredCapacity) {
                        return false;
                    }

                    return ! ClassroomBooking::query()
                        ->where('classroom_id', $candidate->getKey())
                        ->onDay($day)
                        ->overlapping($startsMinute, $endsMinute)
                        ->exists();
                });

                if (! $classroom) {
                    throw ValidationException::withMessages([
                        'assignment' => sprintf(
                            'No conflict-free room with %d seats is available for %s, %s %s-%s. No assignments were saved.',
                            $requiredCapacity,
                            $group->display_name,
                            $day,
                            substr((string) $session->starts_at, 0, 5),
                            substr((string) $session->ends_at, 0, 5)
                        ),
                    ]);
                }

                $created->push($this->bookings->create([
                    'class_group_id' => $group->getKey(),
                    'class_session_id' => $session->getKey(),
                    'classroom_id' => $classroom->getKey(),
                    // A class teacher is homeroom metadata, not proof that the
                    // same teacher teaches every simultaneous booked session.
                    'teacher_id' => null,
                    'day_of_week' => $day,
                    'starts_at' => $session->starts_at,
                    'ends_at' => $session->ends_at,
                ]));
            }

            return $created;
        }, 3);
    }

    /** @param list<int>|null $classGroupIds */
    public function assign(?array $classGroupIds = null): Collection
    {
        return $this->assignAll($classGroupIds);
    }

    /**
     * @param  Collection<int, ClassGroup>  $groups
     * @return Collection<int, array{group: ClassGroup, session: ClassSession, day: string}>
     */
    private function missingRequirements(Collection $groups): Collection
    {
        $existing = ClassroomBooking::query()
            ->whereIn('class_group_id', $groups->modelKeys())
            ->lockForUpdate()
            ->get(['class_group_id', 'class_session_id', 'day_of_week'])
            ->mapWithKeys(fn (ClassroomBooking $booking) => [
                $booking->class_group_id.'|'.$booking->class_session_id.'|'.$booking->day_of_week => true,
            ]);
        $dayOrder = array_flip(config('classrooms.weekdays', []));
        $requirements = collect();

        foreach ($groups as $group) {
            foreach ($group->sessions as $session) {
                foreach (config('classrooms.weekdays', []) as $day) {
                    $key = $group->getKey().'|'.$session->getKey().'|'.$day;
                    if (! $existing->has($key)) {
                        $requirements->push(compact('group', 'session', 'day'));
                    }
                }
            }
        }

        return $requirements->sortBy(fn (array $item) => sprintf(
            '%02d-%04d-%06d-%010d',
            $dayOrder[$item['day']] ?? 99,
            ClassroomBooking::toMinute($item['session']->starts_at),
            999999 - (int) $item['group']->capacity,
            $item['group']->getKey()
        ))->values();
    }
}
