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
        $weeklyLessons = Schedule::with(['subject.teacher', 'teacher', 'schoolClass.classGroup'])->where(fn ($q)=>$q->where('academic_year',$academicYear)->orWhereNull('academic_year'))->get();
        $lessons = $weeklyLessons->where('day', $day);
        $weeklyBookings = ClassroomBooking::with('classGroup', 'classSession', 'teacher')->get()->groupBy('classroom_id');
        $programsByRoom = $weeklyLessons->groupBy('classroom_id')->map(fn ($entries) => $entries->pluck('schoolClass.program')->filter());
        $cohorts = \App\Models\SchoolClass::withCount(['students as enrolled_count' => fn ($query) => $query->where('status', 'active')])->get();
        $lessonsByRoom = $weeklyLessons->groupBy('classroom_id');

        $rooms = Classroom::query()
            ->with(['buildingRecord', 'bookings' => fn ($query) => $query
                ->where('day_of_week', $day)
                ->with(['classGroup', 'classSession', 'teacher'])
                ->orderBy('starts_minute')])
            ->orderBy('room_number')
            ->get();

        return $rooms
            ->map(function (Classroom $classroom) use ($minute, $lessons, $weeklyBookings, $programsByRoom, $cohorts, $lessonsByRoom) {
                $programs = ($programsByRoom->get($classroom->id) ?? collect())
                    ->merge(($weeklyBookings->get($classroom->id) ?? collect())->pluck('classGroup.program'))
                    ->filter()->unique()->sort()->values();
                $programDetails = $programs->mapWithKeys(function ($program) use ($classroom, $weeklyBookings, $lessonsByRoom, $cohorts) {
                    $programLessons = ($lessonsByRoom->get($classroom->id) ?? collect())->filter(fn ($lesson) => $lesson->schoolClass?->program === $program);
                    $programBookings = ($weeklyBookings->get($classroom->id) ?? collect())->filter(fn ($booking) => $booking->classGroup?->program === $program);
                    $ownerIds = $programLessons->pluck('school_class_id');
                    $classes = $cohorts->filter(fn ($class) => $class->program === $program && ($ownerIds->contains($class->timetableOwnerId()) ||
                        $programBookings->contains(fn ($booking) => $booking->class_group_id === $class->class_group_id && $booking->classSession?->session === $class->session)));
                    return [$program => [
                        'students' => $classes->sum('enrolled_count'),
                        'classes' => $classes->pluck('label')->unique()->values(),
                        'teachers' => $programLessons->pluck('teacher.name')->merge($programBookings->pluck('teacher.name'))->filter()->unique()->values(),
                        'subjects' => $programLessons->pluck('subject.name')->filter()->unique()->values(),
                        'sessions' => $classes->pluck('session')->merge($programBookings->pluck('classSession.session'))->filter()->unique()->values(),
                    ]];
                });
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
                if ($activity['nextLesson'] && $status === self::STATUS_AVAILABLE) $status = self::STATUS_UPCOMING;
                $roomLessons = app(RoomLessonService::class)->lessons($classroom, $lessons);
                if (auth()->check() && in_array(auth()->user()->role, ['student', 'parent'], true)) {
                    $cohorts = \App\Models\Student::visibleTo(auth()->user())->with('schoolClass')->get()->pluck('schoolClass')->filter();
                    $visibleBooking = fn ($b) => ! $b || $cohorts->contains(fn ($c) => $c->class_group_id === $b->class_group_id && $c->session === $b->classSession?->session);
                    $visibleLesson = fn ($lesson) => $cohorts->contains(fn ($class) => $class->timetableOwnerId() === (int) $lesson->school_class_id);
                    if ($activity['currentLesson'] && ! $visibleLesson($activity['currentLesson'])) { $activity['currentLesson'] = null; $activity['activity'] = 'Reserved for another cohort'; }
                    if ($activity['nextLesson'] && ! $visibleLesson($activity['nextLesson'])) $activity['nextLesson'] = null;
                    if (! $visibleBooking($current)) { $current = null; $activity['activity'] = 'Reserved for another cohort'; }
                    if (! $visibleBooking($next)) $next = null;
                }
                return compact('classroom', 'status', 'current', 'next', 'roomLessons', 'programs', 'programDetails') + $activity;
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

        if (filled($filters['building'] ?? null) && $classroom->building !== $filters['building']) {
            return false;
        }

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

        $matchesLesson = $entry['roomLessons']->contains(function (Schedule $lesson) use ($bookingFilters) {
            $class = $lesson->schoolClass;
            if (! $class) return false;
            if (isset($bookingFilters['program']) && $class->program !== $bookingFilters['program']) return false;
            if (isset($bookingFilters['grade']) && (int) $class->classGroup?->grade !== (int) $bookingFilters['grade']) return false;
            if (isset($bookingFilters['session']) && $class->session !== $bookingFilters['session']) return false;
            return true;
        });

        return $matchesLesson || $classroom->bookings->contains(function (ClassroomBooking $booking) use ($bookingFilters) {
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
