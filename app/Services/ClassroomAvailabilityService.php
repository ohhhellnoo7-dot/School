<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassroomBooking;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ClassroomAvailabilityService
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_BUSY = 'busy';

    public const STATUS_UPCOMING = 'upcoming';

    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    /**
     * The second argument may be a filter array, or a separate HH:MM value
     * when the first argument contains only a date.
     *
     * @param  array<string, mixed>|string|null  $timeOrFilters
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{classroom: Classroom, status: string, current: ?ClassroomBooking, next: ?ClassroomBooking}>
     */
    public function forMoment(
        CarbonInterface|string $moment,
        array|string|null $timeOrFilters = [],
        array $filters = []
    ): Collection {
        $timezone = config('classrooms.timezone', 'Asia/Bangkok');
        if (is_array($timeOrFilters)) {
            $filters = $timeOrFilters;
        } elseif (filled($timeOrFilters)) {
            $date = $moment instanceof CarbonInterface
                ? $moment->setTimezone($timezone)->format('Y-m-d')
                : substr(trim($moment), 0, 10);
            $moment = $date.' '.$timeOrFilters;
        }

        $selected = $moment instanceof CarbonInterface
            ? CarbonImmutable::instance($moment)->setTimezone($timezone)
            : CarbonImmutable::parse($moment, $timezone);
        $minute = ($selected->hour * 60) + $selected->minute;
        $day = $selected->englishDayOfWeek;
        $academicYear = $filters['academic_year'] ?? $selected->year.'-'.($selected->year+1);
        $lessons = Schedule::with(['subject.teacher', 'teacher', 'schoolClass'])->where('day', $day)->where(fn ($q)=>$q->where('academic_year',$academicYear)->orWhereNull('academic_year'))->get();

        $rooms = Classroom::query()
            ->with(['bookings' => fn ($query) => $query
                ->where('day_of_week', $day)
                ->with(['classGroup', 'classSession', 'teacher'])
                ->orderBy('starts_minute')])
            ->orderBy('room_number')
            ->get();

        return $rooms
            ->map(function (Classroom $classroom) use ($minute, $lessons) {
                $bookings = $classroom->bookings;
                $current = $bookings->first(fn (ClassroomBooking $booking) => $booking->starts_minute <= $minute && $booking->ends_minute > $minute
                );
                $next = $bookings->first(fn (ClassroomBooking $booking) => $booking->starts_minute > $minute);

                $status = match (true) {
                    $classroom->status === Classroom::STATUS_OUT_OF_SERVICE => self::STATUS_OUT_OF_SERVICE,
                    $current !== null => self::STATUS_BUSY,
                    $next !== null => self::STATUS_UPCOMING,
                    default => self::STATUS_AVAILABLE,
                };

                $activity = app(RoomLessonService::class)->activity($classroom, $lessons, $minute, $current);
                if ($activity['currentLesson'] && $status !== self::STATUS_OUT_OF_SERVICE) $status = self::STATUS_BUSY;
                if (auth()->check() && in_array(auth()->user()->role, ['student', 'parent'], true)) {
                    $cohorts = \App\Models\Student::visibleTo(auth()->user())->with('schoolClass')->get()->pluck('schoolClass')->filter();
                    $visibleBooking = fn ($b) => ! $b || $cohorts->contains(fn ($c) => $c->class_group_id === $b->class_group_id && $c->session === $b->classSession?->session);
                    if ($activity['currentLesson'] && ! $cohorts->contains('id', $activity['currentLesson']->school_class_id)) { $activity['currentLesson'] = null; $activity['activity'] = 'Reserved for another cohort'; }
                    if ($activity['nextLesson'] && ! $cohorts->contains('id', $activity['nextLesson']->school_class_id)) $activity['nextLesson'] = null;
                    if (! $visibleBooking($current)) { $current = null; $activity['activity'] = 'Reserved for another cohort'; }
                    if (! $visibleBooking($next)) $next = null;
                }
                return compact('classroom', 'status', 'current', 'next') + $activity;
            })
            ->filter(fn (array $entry) => $this->matchesFilters($entry, $filters))
            ->values();
    }

    /**
     * Convenience wrapper for forms that collect date and time separately.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{classroom: Classroom, status: string, current: ?ClassroomBooking, next: ?ClassroomBooking}>
     */
    public function forDateAndTime(string $date, string $time, array $filters = []): Collection
    {
        return $this->forMoment("{$date} {$time}", $filters);
    }

    /**
     * @param  Collection<int, array{classroom: Classroom, status: string, current: ?ClassroomBooking, next: ?ClassroomBooking}>  $rooms
     * @return array{total: int, busy: int, available: int, upcoming: int, out_of_service: int}
     */
    public function summary(Collection $rooms): array
    {
        return [
            'total' => $rooms->count(),
            'busy' => $rooms->where('status', self::STATUS_BUSY)->count(),
            'available' => $rooms->where('status', self::STATUS_AVAILABLE)->count(),
            'upcoming' => $rooms->where('status', self::STATUS_UPCOMING)->count(),
            'out_of_service' => $rooms->where('status', self::STATUS_OUT_OF_SERVICE)->count(),
        ];
    }

    /** @param array{classroom: Classroom, status: string, current: ?ClassroomBooking, next: ?ClassroomBooking} $entry */
    private function matchesFilters(array $entry, array $filters): bool
    {
        /** @var Classroom $classroom */
        $classroom = $entry['classroom'];

        if (filled($filters['status'] ?? null) && $entry['status'] !== $filters['status']) {
            return false;
        }

        $roomSearch = $filters['room'] ?? $filters['room_name'] ?? $filters['search'] ?? null;
        if (filled($roomSearch)) {
            $needle = mb_strtolower(trim((string) $roomSearch));
            if (! str_contains(mb_strtolower($classroom->room_number.' '.$classroom->name), $needle)) {
                return false;
            }
        }

        if (filled($filters['minimum_capacity'] ?? null) && $classroom->capacity < (int) $filters['minimum_capacity']) {
            return false;
        }

        $bookingFilters = array_filter([
            'program' => $filters['program'] ?? null,
            'grade' => $filters['grade'] ?? null,
            'session' => $filters['session'] ?? null,
        ], fn ($value) => filled($value));

        if ($bookingFilters === []) {
            return true;
        }

        return $classroom->bookings->contains(function (ClassroomBooking $booking) use ($bookingFilters) {
            if (isset($bookingFilters['program']) && $booking->classGroup?->program !== $bookingFilters['program']) {
                return false;
            }
            if (isset($bookingFilters['grade']) && (int) $booking->classGroup?->grade !== (int) $bookingFilters['grade']) {
                return false;
            }
            if (isset($bookingFilters['session']) && $booking->classSession?->session !== $bookingFilters['session']) {
                return false;
            }

            return true;
        });
    }
}
