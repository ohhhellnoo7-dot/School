<?php

namespace Database\Seeders;

use App\Models\ClassGroup;
use App\Models\Classroom;
use App\Models\ClassroomBooking;
use App\Models\ClassSession;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ClassroomBookingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ClassroomManagementSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $groups = $this->createClassGroups();
            $this->createSessions($groups);
            $rooms = $this->createPhysicalRooms();
            $this->mapLegacyClasses($groups);
            $this->createDefaultBookings($groups, $rooms);
        }, 3);

        $this->command?->info(sprintf(
            'Classroom management data ready: %d class groups, %d sessions, %d rooms and %d weekday bookings. Legacy records were preserved.',
            ClassGroup::count(),
            ClassSession::count(),
            Classroom::count(),
            ClassroomBooking::count()
        ));
    }

    /** @return Collection<int, ClassGroup> */
    private function createClassGroups(): Collection
    {
        $teachers = User::query()
            ->where('role', 'teacher')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (User $teacher) => $teacher->program ?: 'Khmer');
        $groups = collect();

        foreach (range(7, 12) as $grade) {
            foreach (range('A', 'E') as $section) {
                $groups->push($this->firstOrCreateGroup(
                    'Khmer',
                    $grade,
                    $section,
                    $teachers->get('Khmer', collect())
                ));
            }
        }

        foreach (['English', 'Chinese'] as $program) {
            foreach (range(1, 12) as $grade) {
                $groups->push($this->firstOrCreateGroup(
                    $program,
                    $grade,
                    null,
                    $teachers->get($program, collect())
                ));
            }
        }

        return $groups;
    }

    /** @param Collection<int, User> $teachers */
    private function firstOrCreateGroup(
        string $program,
        int $grade,
        ?string $section,
        Collection $teachers
    ): ClassGroup {
        $teacher = $teachers->isEmpty()
            ? null
            : $teachers->values()[($grade + ($section ? ord($section) : 0)) % $teachers->count()];

        return ClassGroup::query()->firstOrCreate([
            'program' => $program,
            'grade' => $grade,
            'section' => $section,
        ], [
            'name' => 'Grade '.$grade,
            'teacher_id' => $teacher?->getKey(),
            'capacity' => 40,
            'status' => 'active',
        ]);
    }

    /** @param Collection<int, ClassGroup> $groups */
    private function createSessions(Collection $groups): void
    {
        foreach ($groups as $group) {
            foreach (ClassGroup::allowedSessions($group->program) as $session) {
                $times = config("classrooms.sessions.{$session}");
                if (! is_array($times)) {
                    throw new RuntimeException("Missing classroom time configuration for {$session}.");
                }

                $group->sessions()->firstOrCreate([
                    'session' => $session,
                ], [
                    'starts_at' => $times['starts_at'],
                    'ends_at' => $times['ends_at'],
                ]);
            }
        }
    }

    /** @return Collection<int, Classroom> */
    private function createPhysicalRooms(): Collection
    {
        $configuredRooms = collect(config('classrooms.rooms', []));
        if ($configuredRooms->count() !== 42 || $configuredRooms->pluck('room_number')->unique()->count() !== 42) {
            throw new RuntimeException('Classroom configuration must define exactly 42 unique physical rooms.');
        }

        return $configuredRooms->map(fn (array $room) => Classroom::query()->firstOrCreate([
            'room_number' => $room['room_number'],
        ], [
            'name' => $room['name'],
            'capacity' => $room['capacity'],
            'status' => Classroom::STATUS_ACTIVE,
        ]));
    }

    /** @param Collection<int, ClassGroup> $groups */
    private function mapLegacyClasses(Collection $groups): void
    {
        $lookup = $groups->keyBy(fn (ClassGroup $group) => $this->groupKey(
            $group->program,
            $group->grade,
            $group->section
        ));

        foreach (SchoolClass::query()->whereNull('class_group_id')->orderBy('id')->lockForUpdate()->get() as $legacy) {
            if (! preg_match('/(?:Grade\s*)?(\d{1,2})/i', (string) $legacy->name, $matches)) {
                continue;
            }

            $grade = (int) $matches[1];
            $program = $legacy->program ?: 'Khmer';
            $section = $program === 'Khmer' ? strtoupper(trim((string) $legacy->section)) : null;
            $group = $lookup->get($this->groupKey($program, $grade, $section));

            if ($group) {
                // This additive link is the only change made to legacy rows.
                $legacy->forceFill(['class_group_id' => $group->getKey()])->saveQuietly();
            }
        }
    }

    /**
     * @param  Collection<int, ClassGroup>  $groups
     * @param  Collection<int, Classroom>  $rooms
     */
    private function createDefaultBookings(Collection $groups, Collection $rooms): void
    {
        $service = app(ClassroomBookingService::class);
        $roomByNumber = $rooms->keyBy('room_number');
        $chineseEveningRooms = $rooms
            ->filter(fn (Classroom $room) => preg_match('/^(?:[7-9]|1[0-2])[A-E]$/', $room->room_number))
            ->sortBy('id')
            ->take(12)
            ->values();

        if ($chineseEveningRooms->count() !== 12) {
            throw new RuntimeException('Twelve Khmer rooms are required for Chinese evening assignments.');
        }

        foreach ($groups as $group) {
            $group->loadMissing('sessions');
            foreach ($group->sessions as $session) {
                $room = match ($group->program) {
                    'Khmer' => $roomByNumber->get($group->grade.$group->section),
                    'English' => $roomByNumber->get('E'.str_pad((string) $group->grade, 2, '0', STR_PAD_LEFT)),
                    'Chinese' => $chineseEveningRooms->get($group->grade - 1),
                    default => null,
                };

                if (! $room) {
                    throw new RuntimeException("No default room is configured for {$group->display_name}.");
                }

                foreach (config('classrooms.weekdays', []) as $day) {
                    $exists = ClassroomBooking::query()
                        ->where('class_group_id', $group->getKey())
                        ->where('class_session_id', $session->getKey())
                        ->where('day_of_week', $day)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $service->create([
                        'class_group_id' => $group->getKey(),
                        'class_session_id' => $session->getKey(),
                        'classroom_id' => $room->getKey(),
                        // The optional booking teacher remains unassigned until
                        // an actual teacher is scheduled for this time block.
                        'teacher_id' => null,
                        'day_of_week' => $day,
                        'starts_at' => $session->starts_at,
                        'ends_at' => $session->ends_at,
                    ]);
                }
            }
        }
    }

    private function groupKey(string $program, int $grade, ?string $section): string
    {
        return implode('|', [$program, $grade, strtoupper(trim((string) $section))]);
    }
}
