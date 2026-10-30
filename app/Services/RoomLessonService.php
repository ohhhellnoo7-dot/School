<?php

namespace App\Services;

use App\Models\{Classroom, Schedule};
use Illuminate\Support\Collection;

class RoomLessonService
{
    public static function roomKey(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', preg_replace('/^room\s*/i', '', strtolower(trim($name ?? ''))));
    }

    /** Reservations establish occupancy; the shared lesson establishes activity. */
    public function lessons(Classroom $room, Collection $lessons): Collection
    {
        $keys = [self::roomKey($room->room_number), self::roomKey($room->name)];
        return $lessons->filter(function (Schedule $lesson) use ($room, $keys) {
            if ($lesson->classroom_id) return (int) $lesson->classroom_id === (int) $room->id;
            if (in_array(self::roomKey($lesson->room), $keys, true)) return true;
            // Legacy lessons have room labels rather than a physical room FK.
            // Their class-session reservation supplies a link, with mismatch reported.
            return $room->bookings->contains(fn ($b) => $b->day_of_week === $lesson->day
                && $b->class_group_id === $lesson->schoolClass?->class_group_id
                && $b->classSession?->session === $lesson->schoolClass?->session
                && TeacherManagementService::overlaps($b->starts_at, $b->ends_at, $lesson->starts_at, $lesson->ends_at));
        })->sortBy('starts_at')->values();
    }

    public function activity(Classroom $room, Collection $lessons, int $minute, mixed $reservation): array
    {
        $roomLessons = $this->lessons($room, $lessons);
        $currentLessons = $roomLessons->filter(fn ($s) => TeacherManagementService::minute($s->starts_at) <= $minute && TeacherManagementService::minute($s->ends_at) > $minute);
        $currentLesson = $currentLessons->first();
        $nextLesson = $roomLessons->first(fn ($s) => TeacherManagementService::minute($s->starts_at) > $minute);
        $occupancy = $reservation || $currentLesson ? 'Occupied' : 'Available';
        if ($room->status === Classroom::STATUS_OUT_OF_SERVICE) $occupancy = 'Out of service';
        $activity = $currentLesson ? ($currentLesson->isRelaxation() ? 'Scheduled relaxation' : $currentLesson->subject?->name) : ($reservation ? 'Free Period' : 'No Class');
        $roomMismatch = $currentLesson && ! $currentLesson->classroom_id && ! in_array(self::roomKey($currentLesson->room), [self::roomKey($room->room_number), self::roomKey($room->name)], true);
        return compact('currentLesson', 'nextLesson', 'occupancy', 'activity', 'roomMismatch') + ['lessonConflict' => $currentLessons->count() > 1];
    }
}
