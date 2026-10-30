<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AutomaticTimetableGenerator
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    private const SESSION_STARTS = [
        'Morning' => ['07:00', '08:00', '09:00', '10:00'],
        'Afternoon' => ['13:00', '14:00', '15:00', '16:00'],
    ];

    private const MAX_ATTEMPTS = 60;

    private const MAX_SEARCH_STEPS = 300000;

    private array $teacherRules = [];

    /** @return Collection<int, Schedule> */
    public function generate(?SchoolClass $schoolClass = null, bool $overwrite = false): Collection
    {
        return DB::transaction(function () use ($schoolClass, $overwrite) {
            DB::table('teacher_management_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            $service = app(TeacherManagementService::class);
            $this->teacherRules = [];
            foreach (\App\Models\User::whereIn('id', DB::table('teacher_profiles')->select('teacher_id'))->get() as $teacher) {
                $this->teacherRules[$teacher->id] = ['periods' => $service->periods($teacher), 'breaks' => $service->breaks($teacher), 'limit' => $service->limit($teacher)];
            }
            // Lock every potentially generated class in deterministic order. This
            // serializes two requests targeting different classes that share staff.
            $locked = SchoolClass::query()->orderBy('id')->lockForUpdate()->get();
            $classes = $this->targetClasses($locked, $schoolClass);
            if ($classes->contains(fn ($class) => $class->program === 'Khmer' && $class->class_group_id)) $this->fail('Established Khmer cohorts must use the School Timetables replacement preview. The legacy hourly generator cannot publish the required two-hour subject blocks or bypass administrator confirmation.');
            $classes->load(['subjects' => fn ($query) => $query->orderBy('id'), 'subjects.teacher']);
            $this->validateClasses($classes);

            $allSchedules = Schedule::with('subject')->lockForUpdate()->get();
            $targetIds = $classes->modelKeys();
            [$targetSchedules, $outsideSchedules] = $allSchedules->partition(
                fn (Schedule $schedule) => in_array($this->classIdFor($schedule), $targetIds, true)
            );

            if ($targetSchedules->isNotEmpty() && ! $overwrite) {
                $this->fail('A timetable already exists for the selected class or classes. Use Regenerate Timetable to confirm replacement.');
            }

            $plan = $this->makePlan($classes, $outsideSchedules);
            $this->validatePlan($classes, $plan, $outsideSchedules);

            if ($overwrite && $targetSchedules->isNotEmpty()) {
                Schedule::query()->whereKey($targetSchedules->modelKeys())->delete();
            }

            return collect($plan)->map(fn (array $attributes) => Schedule::create($attributes));
        }, 3);
    }

    /**
     * @param  EloquentCollection<int, SchoolClass>  $locked
     * @return EloquentCollection<int, SchoolClass>
     */
    private function targetClasses(EloquentCollection $locked, ?SchoolClass $requested): EloquentCollection
    {
        $classes = $requested
            ? $locked->where('id', $requested->getKey())->values()
            : $locked->filter(fn (SchoolClass $class) => $this->gradeNumber($class) !== null && isset(self::SESSION_STARTS[$class->session]))->values();

        if ($classes->isEmpty()) {
            $this->fail('No Grade 7-12 morning or afternoon classes are available for timetable generation.');
        }

        return new EloquentCollection($classes->all());
    }

    /** @param EloquentCollection<int, SchoolClass> $classes */
    private function validateClasses(EloquentCollection $classes): void
    {
        foreach ($classes as $class) {
            $label = $class->label;
            if ($this->gradeNumber($class) === null) {
                $this->fail("{$label} is outside Grades 7-12.");
            }
            if (! isset(self::SESSION_STARTS[$class->session])) {
                $this->fail("{$label} must be assigned to the Morning or Afternoon session.");
            }
            if (blank($class->room)) {
                $this->fail("Assign a classroom to {$label} before generating its timetable.");
            }
            if ($class->subjects->isEmpty()) {
                $this->fail("Assign at least one subject to {$label} before generating its timetable.");
            }
            if ($class->subjects->count() > 17) {
                $this->fail("{$label} has more than 17 subjects, so every assigned subject cannot receive a weekly lesson.");
            }

            $unassigned = $class->subjects->first(fn (Subject $subject) => ! $subject->teacher_id || ! $subject->teacher);
            if ($unassigned) {
                $this->fail("Assign a teacher to {$unassigned->name} for {$label} before generating its timetable.");
            }
            foreach ($class->subjects as $subject) {
                try { app(TeacherManagementService::class)->validateSubject($subject); }
                catch (ValidationException $exception) { $this->fail($subject->name.' · '.$label.': '.$exception->getMessage()); }
            }
        }
    }

    /**
     * @param  EloquentCollection<int, SchoolClass>  $classes
     * @param  Collection<int, Schedule>  $outsideSchedules
     * @return array<int, array<string, mixed>>
     */
    private function makePlan(EloquentCollection $classes, Collection $outsideSchedules): array
    {
        $existingTeachers = [];
        $existingRooms = [];
        foreach ($outsideSchedules as $schedule) {
            $interval = [$this->time($schedule->starts_at), $this->time($schedule->ends_at)];
            $teacherId = $schedule->teacher_id ?: $schedule->subject?->teacher_id;
            if ($teacherId) {
                $existingTeachers[$teacherId][$schedule->day][] = $interval;
            }
            if ($schedule->room) {
                $existingRooms[$schedule->room][$schedule->day][] = $interval;
            }
        }

        $this->ensureRoomsAreAvailable($classes, $existingRooms);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            [$rows, $tasks, $remaining, $subjects] = $this->buildAttempt($classes);
            $teacherBusy = $existingTeachers;
            $assignments = [];
            $unassigned = array_fill(0, count($tasks), true);
            $steps = 0;

            if (! $this->assignAcademicLessons($tasks, $subjects, $remaining, $teacherBusy, $assignments, $unassigned, $steps)) {
                continue;
            }

            foreach ($tasks as $index => $task) {
                $rows[] = $this->academicRow($task, $subjects[$task['school_class_id']][$assignments[$index]]);
            }

            return $rows;
        }

        $this->fail('The timetable cannot satisfy all teacher, classroom, subject, session, and relaxation constraints. No changes were saved.');
    }

    /**
     * @param  EloquentCollection<int, SchoolClass>  $classes
     * @param  array<string, array<string, array<int, array{0: string, 1: string}>>>  $existingRooms
     */
    private function ensureRoomsAreAvailable(EloquentCollection $classes, array $existingRooms): void
    {
        $planned = [];
        foreach ($classes as $class) {
            foreach (self::DAYS as $day) {
                foreach (self::SESSION_STARTS[$class->session] as $start) {
                    $end = $this->addHour($start);
                    foreach ($existingRooms[$class->room][$day] ?? [] as [$busyStart, $busyEnd]) {
                        if ($this->overlaps($start, $end, $busyStart, $busyEnd)) {
                            $this->fail("Classroom {$class->room} is already in use on {$day} at {$start}.");
                        }
                    }
                    foreach ($planned[$class->room][$day] ?? [] as [$busyStart, $busyEnd, $label]) {
                        if ($this->overlaps($start, $end, $busyStart, $busyEnd)) {
                            $this->fail("Classroom {$class->room} is assigned to both {$label} and {$class->label} on {$day} at {$start}.");
                        }
                    }
                    $planned[$class->room][$day][] = [$start, $end, $class->label];
                }
            }
        }
    }

    /**
     * @param  EloquentCollection<int, SchoolClass>  $classes
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<int, int>>, 3: array<int, array<int, Subject>>}
     */
    private function buildAttempt(EloquentCollection $classes): array
    {
        $rows = [];
        $tasks = [];
        $remaining = [];
        $subjects = [];

        foreach ($classes as $class) {
            $days = self::DAYS;
            shuffle($days);
            $relaxation = [];
            foreach (self::DAYS as $day) foreach (self::SESSION_STARTS[$class->session] as $slot => $start) {
                if ($class->subjects->every(fn ($s) => $this->teacherIsBusy([], $s->teacher_id, $day, $start, $this->addHour($start)))) {
                    if (isset($relaxation[$day])) $this->fail("{$class->label}: more than one period on {$day} has no on-duty teacher. Adjust working hours or assignments.");
                    $relaxation[$day] = $slot;
                }
            }
            if (count($relaxation) > 3) $this->fail("{$class->label}: teacher breaks or unavailable periods leave too few academic periods. Review assignments.");
            $days = array_values(array_diff($days, array_keys($relaxation)));
            foreach (array_slice($days, 0, 3 - count($relaxation)) as $day) {
                $relaxation[$day] = random_int(0, 3);
            }

            $classSubjects = $class->subjects->shuffle()->values();
            $configured = $classSubjects->whereNotNull('required_weekly_minutes');
            if ($configured->isNotEmpty() && ($configured->count() !== $classSubjects->count() || $configured->sum('required_weekly_minutes') !== 1020 || $configured->contains(fn ($s) => $s->required_weekly_minutes % 60 !== 0))) $this->fail("{$class->label}: this hourly generator requires approved whole-hour curriculum allocations totaling 17 hours.");
            $base = intdiv(17, $classSubjects->count());
            $extra = 17 % $classSubjects->count();
            foreach ($classSubjects as $index => $subject) {
                $subjects[$class->id][$subject->id] = $subject;
                $remaining[$class->id][$subject->id] = $configured->isNotEmpty() ? intdiv($subject->required_weekly_minutes, 60) : $base + ($index < $extra ? 1 : 0);
            }

            foreach (self::DAYS as $day) {
                foreach (self::SESSION_STARTS[$class->session] as $slot => $start) {
                    $common = [
                        'school_class_id' => $class->id,
                        'day' => $day,
                        'starts_at' => $start,
                        'ends_at' => $this->addHour($start),
                        'room' => $class->room,
                    ];
                    if (($relaxation[$day] ?? null) === $slot) {
                        $rows[] = $this->relaxationRow($common);
                    } else {
                        $tasks[] = $common;
                    }
                }
            }
        }

        shuffle($tasks);

        return [$rows, $tasks, $remaining, $subjects];
    }

    /**
     * Backtracking uses the most constrained remaining class slot first.
     *
     * @param  array<int, array<string, mixed>>  $tasks
     * @param  array<int, array<int, Subject>>  $subjects
     * @param  array<int, array<int, int>>  $remaining
     * @param  array<int, array<string, array<int, array{0: string, 1: string}>>>  $teacherBusy
     * @param  array<int, int>  $assignments
     * @param  array<int, bool>  $unassigned
     */
    private function assignAcademicLessons(array $tasks, array $subjects, array &$remaining, array &$teacherBusy, array &$assignments, array &$unassigned, int &$steps): bool
    {
        if (++$steps > self::MAX_SEARCH_STEPS) {
            return false;
        }
        if (! in_array(true, $unassigned, true)) {
            return true;
        }

        $chosen = null;
        $chosenOptions = [];
        foreach ($unassigned as $index => $isUnassigned) {
            if (! $isUnassigned) {
                continue;
            }
            $task = $tasks[$index];
            $options = [];
            foreach ($subjects[$task['school_class_id']] as $subjectId => $subject) {
                if (($remaining[$task['school_class_id']][$subjectId] ?? 0) > 0
                    && ! $this->teacherIsBusy($teacherBusy, $subject->teacher_id, $task['day'], $task['starts_at'], $task['ends_at'])) {
                    $options[] = $subjectId;
                }
            }
            if ($options === []) {
                return false;
            }
            if ($chosen === null || count($options) < count($chosenOptions)) {
                $chosen = $index;
                $chosenOptions = $options;
                if (count($options) === 1) {
                    break;
                }
            }
        }

        shuffle($chosenOptions);
        $task = $tasks[$chosen];
        usort($chosenOptions, fn (int $a, int $b) => $remaining[$task['school_class_id']][$b] <=> $remaining[$task['school_class_id']][$a]);
        $unassigned[$chosen] = false;

        foreach ($chosenOptions as $subjectId) {
            $subject = $subjects[$task['school_class_id']][$subjectId];
            $remaining[$task['school_class_id']][$subjectId]--;
            $teacherBusy[$subject->teacher_id][$task['day']][] = [$task['starts_at'], $task['ends_at']];
            $assignments[$chosen] = $subjectId;

            if ($this->assignAcademicLessons($tasks, $subjects, $remaining, $teacherBusy, $assignments, $unassigned, $steps)) {
                return true;
            }

            array_pop($teacherBusy[$subject->teacher_id][$task['day']]);
            $remaining[$task['school_class_id']][$subjectId]++;
            unset($assignments[$chosen]);
        }

        $unassigned[$chosen] = true;

        return false;
    }

    /** @param array<int, array<string, array<int, array{0: string, 1: string}>>> $teacherBusy */
    private function teacherIsBusy(array $teacherBusy, int $teacherId, string $day, string $start, string $end): bool
    {
        if (isset($this->teacherRules[$teacherId])) {
            $rules = $this->teacherRules[$teacherId]; $periods = $rules['periods']->where('weekday', $day);
            $available = $periods->contains(fn ($p) => ! $p->unavailable && TeacherManagementService::minute($p->start_time) <= TeacherManagementService::minute($start) && TeacherManagementService::minute($p->end_time) >= TeacherManagementService::minute($end));
            if (! $available || $periods->contains(fn ($p) => $p->unavailable && TeacherManagementService::overlaps($start, $end, $p->start_time, $p->end_time))) return true;
            if ($rules['breaks']->contains(fn ($b) => $b->weekday === $day && TeacherManagementService::overlaps($start, $end, $b->start_time, $b->end_time))) return true;
            $used = 0;
            foreach ($teacherBusy[$teacherId] ?? [] as $intervals) foreach ($intervals as [$from, $to]) $used += TeacherManagementService::duration($from, $to);
            if ($used + TeacherManagementService::duration($start, $end) > $rules['limit']) return true;
        }
        foreach ($teacherBusy[$teacherId][$day] ?? [] as [$busyStart, $busyEnd]) {
            if ($this->overlaps($start, $end, $busyStart, $busyEnd)) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $task */
    private function academicRow(array $task, Subject $subject): array
    {
        return $task + [
            'subject_id' => $subject->id,
            'teacher_id' => $subject->teacher_id,
            'entry_type' => Schedule::TYPE_ACADEMIC,
            'title_en' => null,
            'title_km' => null,
            'generated_at' => now(),
        ];
    }

    /** @param array<string, mixed> $slot */
    private function relaxationRow(array $slot): array
    {
        return $slot + [
            'subject_id' => null,
            'teacher_id' => null,
            'entry_type' => Schedule::TYPE_RELAXATION,
            'title_en' => 'Relaxation Time',
            'title_km' => 'ម៉ោងសម្រាក',
            'generated_at' => now(),
        ];
    }

    /**
     * @param  EloquentCollection<int, SchoolClass>  $classes
     * @param  array<int, array<string, mixed>>  $plan
     * @param  Collection<int, Schedule>  $outsideSchedules
     */
    private function validatePlan(EloquentCollection $classes, array $plan, Collection $outsideSchedules): void
    {
        $classIds = $classes->modelKeys();
        $classesById = $classes->keyBy('id');
        $subjects = $classes->flatMap->subjects->keyBy('id');
        $seenClasses = [];
        $seenTeachers = [];
        $seenRooms = [];

        foreach ($outsideSchedules as $schedule) {
            $teacherId = $schedule->teacher_id ?: $schedule->subject?->teacher_id;
            $interval = [$this->time($schedule->starts_at), $this->time($schedule->ends_at)];
            if ($teacherId) {
                $seenTeachers[$teacherId][$schedule->day][] = $interval;
            }
            if ($schedule->room) {
                $seenRooms[$schedule->room][$schedule->day][] = $interval;
            }
        }

        foreach ($plan as $row) {
            if (! in_array($row['school_class_id'], $classIds, true) || ! in_array($row['day'], self::DAYS, true)) {
                $this->fail('Generated timetable validation failed because a class or weekday is invalid.');
            }
            $class = $classesById->get($row['school_class_id']);
            if (! in_array($row['starts_at'], self::SESSION_STARTS[$class->session], true)
                || $row['ends_at'] !== $this->addHour($row['starts_at'])) {
                $this->fail("Generated timetable validation found an entry outside {$class->label}'s {$class->session} session.");
            }
            if ($this->minutesBetween($row['starts_at'], $row['ends_at']) !== 60) {
                $this->fail('Generated timetable validation failed because every entry must last exactly one hour.');
            }
            $slotKey = $row['school_class_id'].'|'.$row['day'].'|'.$row['starts_at'];
            if (isset($seenClasses[$slotKey])) {
                $this->fail('Generated timetable validation found a duplicate class time slot.');
            }
            $seenClasses[$slotKey] = true;

            foreach ($seenRooms[$row['room']][$row['day']] ?? [] as [$busyStart, $busyEnd]) {
                if ($this->overlaps($row['starts_at'], $row['ends_at'], $busyStart, $busyEnd)) {
                    $this->fail("Generated timetable validation found a classroom conflict in {$row['room']}.");
                }
            }
            $seenRooms[$row['room']][$row['day']][] = [$row['starts_at'], $row['ends_at']];

            if ($row['entry_type'] === Schedule::TYPE_ACADEMIC) {
                $subject = $subjects->get($row['subject_id']);
                if (! $subject || $subject->school_class_id !== $row['school_class_id'] || $subject->teacher_id !== $row['teacher_id']) {
                    $this->fail('Generated timetable validation found an academic lesson with an invalid subject or teacher assignment.');
                }
                foreach ($seenTeachers[$row['teacher_id']][$row['day']] ?? [] as [$busyStart, $busyEnd]) {
                    if ($this->overlaps($row['starts_at'], $row['ends_at'], $busyStart, $busyEnd)) {
                        $this->fail("Generated timetable validation found a teacher conflict for {$subject->teacher->name}.");
                    }
                }
                $seenTeachers[$row['teacher_id']][$row['day']][] = [$row['starts_at'], $row['ends_at']];
            }
        }

        foreach ($classes as $class) {
            $rows = collect($plan)->where('school_class_id', $class->id);
            $relaxation = $rows->where('entry_type', Schedule::TYPE_RELAXATION);
            if ($rows->count() !== 20
                || $rows->where('entry_type', Schedule::TYPE_ACADEMIC)->count() !== 17
                || $relaxation->count() !== 3
                || $relaxation->pluck('day')->unique()->count() !== 3) {
                $this->fail("Generated timetable validation failed for {$class->label}; it must contain 17 lessons and three relaxation periods on different weekdays.");
            }
        }
    }

    private function classIdFor(Schedule $schedule): ?int
    {
        return $schedule->school_class_id ?: $schedule->subject?->school_class_id;
    }

    private function gradeNumber(SchoolClass $class): ?int
    {
        if (preg_match('/\b(?:grade\s*)?(7|8|9|10|11|12)\b/i', $class->name, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function addHour(string $time): string
    {
        return sprintf('%02d:00', ((int) substr($time, 0, 2)) + 1);
    }

    private function time(mixed $time): string
    {
        return substr((string) $time, 0, 5);
    }

    private function overlaps(string $start, string $end, string $busyStart, string $busyEnd): bool
    {
        return $start < $busyEnd && $end > $busyStart;
    }

    private function minutesBetween(string $start, string $end): int
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $start));
        [$endHour, $endMinute] = array_map('intval', explode(':', $end));

        return ($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute);
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['timetable' => $message]);
    }
}
