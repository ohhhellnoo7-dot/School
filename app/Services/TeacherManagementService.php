<?php

namespace App\Services;

use App\Models\{ClassSession, Schedule, Subject, User};
use App\Support\{TeacherTimetable, TimetableConflicts};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherManagementService
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    public const SESSIONS = ['Morning' => ['07:00', '11:00'], 'Afternoon' => ['13:00', '17:00'], 'Evening' => ['16:30', '18:30']];

    public function locked(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            DB::table('teacher_management_settings')->where('id', 1)->lockForUpdate()->firstOrFail();
            return $callback();
        }, 3);
    }

    public function settings(): object { return DB::table('teacher_management_settings')->where('id', 1)->first(); }
    public function weeklyWorkPlan(User|string|null $teacher = null): array
    {
        $settings = $this->settings();
        $teaching = $teacher instanceof User ? $this->limit($teacher) : $this->defaultLimit($teacher);
        $plan = ['teaching'=>$teaching / 60,'preparation'=>$settings->preparation_minutes / 60,'marking'=>$settings->marking_minutes / 60,'other_duties'=>$settings->other_duties_minutes / 60];
        return $plan + ['total'=>array_sum($plan)];
    }
    public function defaultLimit(?string $program = null): int
    {
        $settings = $this->settings();
        return (int) ($program === 'Khmer' ? ($settings->khmer_weekly_minutes ?? $settings->weekly_minutes) : $settings->weekly_minutes);
    }
    public function limit(User $teacher): int { return (int) (DB::table('teacher_profiles')->where('teacher_id', $teacher->id)->value('weekly_minutes') ?? $this->defaultLimit($teacher->program)); }
    public static function minute(string $time): int { return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2); }
    public static function duration(string $start, string $end): int { return max(0, self::minute($end) - self::minute($start)); }
    public static function overlaps(string $start, string $end, string $otherStart, string $otherEnd): bool { return self::minute($start) < self::minute($otherEnd) && self::minute($end) > self::minute($otherStart); }
    public function managed(int $id): bool { return DB::table('teacher_profiles')->where('teacher_id', $id)->exists(); }
    public function subjectLimit(User $teacher): int { return blank($teacher->program) || $teacher->program === 'Khmer' ? 2 : PHP_INT_MAX; }
    public function qualifications(User $teacher) { return DB::table('teacher_qualifications')->where('teacher_id', $teacher->id)->orderBy('slot')->pluck('subject_name'); }
    public function periods(User $teacher) { return DB::table('teacher_working_periods')->where('teacher_id', $teacher->id)->get(); }
    public function breaks(User $teacher) { return DB::table('teacher_breaks')->join('teacher_break_plans', 'teacher_break_plans.id', '=', 'teacher_breaks.teacher_break_plan_id')->where('teacher_id', $teacher->id)->select('teacher_breaks.*')->orderBy('teacher_breaks.id')->get(); }
    public function lessons(User $teacher) { return Schedule::with('subject.schoolClass', 'schoolClass')->where('entry_type', 'academic')->where('teacher_id', $teacher->id)->get(); }

    public function eligibleForBreaks(User $teacher): bool
    {
        // These programs fill their working shifts with lessons rather than automatic free periods.
        if (in_array($teacher->program, ['English', 'Chinese'], true)) return false;
        return $this->periods($teacher)->where('unavailable', false)->pluck('weekday')->unique()->count() >= 3;
    }

    public function workload(User $teacher): array
    {
        $lessons = $this->lessons($teacher);
        $minutes = $lessons->sum(fn ($s) => self::duration($s->starts_at, $s->ends_at));
        $subjects = Subject::with('schoolClass')->where('teacher_id', $teacher->id)->get()
            ->concat($lessons->pluck('subject')->filter())->unique('id');
        $classes = $subjects->pluck('schoolClass')->concat($lessons->pluck('schoolClass'))->filter()
            ->unique(fn ($c) => $c->class_group_id ?: $c->program.'|'.$c->name.'|'.$c->section);
        $limit = $this->limit($teacher);
        return ['subjects' => $subjects->pluck('name')->unique()->values(), 'classes' => $classes,
            'weekly' => $minutes / 60, 'limit' => $limit / 60, 'breaks' => $this->breaks($teacher)->sum('duration_minutes') / 60,
            'remaining' => max(0, $limit - $minutes) / 60,
            'status' => $lessons->isEmpty() ? 'UNASSIGNED' : ($minutes > $limit ? 'OVERLOADED' : ($minutes === $limit ? 'FULL' : 'AVAILABLE'))];
    }

    public function availability(User $teacher): array
    {
        $now = \Carbon\CarbonImmutable::now(config('app.timezone'));
        $day = $now->englishDayOfWeek;
        $time = $now->format('H:i');
        $academicYear = $now->year.'-'.($now->year+1);
        $lessons = $this->lessons($teacher)->filter(fn ($s)=>\App\Support\AcademicYear::overlaps($s->academic_year,$academicYear))->where('day', $day)->sortBy('starts_at');
        $current = $lessons->first(fn ($s) => self::minute($s->starts_at) <= self::minute($time) && self::minute($s->ends_at) > self::minute($time));
        $next = $lessons->first(fn ($s) => self::minute($s->starts_at) > self::minute($time));
        $free = $this->breaks($teacher)->contains(fn ($b) => $b->weekday === $day && self::minute($b->start_time) <= self::minute($time) && self::minute($b->end_time) > self::minute($time));
        $status = $teacher->status !== 'active' ? 'Inactive' : ($current ? 'Teaching' : ($free ? 'Designated Free Period' : ($this->onDuty($teacher, $day, $time, $now->addMinute()->format('H:i')) ? 'Available' : 'Off duty')));
        $describe = fn ($s) => $s ? $s->subject?->name.' · '.$s->subject?->schoolClass?->label.' · '.__('Room').' '.$s->room.' · '.substr($s->starts_at, 0, 5).'–'.substr($s->ends_at, 0, 5) : __('No lesson');
        return ['status' => __($status), 'current' => $describe($current), 'next' => $describe($next), 'time' => __($now->englishDayOfWeek).' '.$now->format('H:i')];
    }

    public function saveProfile(User $teacher, array $names, ?int $limit, array $periods): void
    {
        $this->locked(function () use ($teacher, $names, $limit, $periods) {
            if (count(array_unique($names)) !== count($names) || count($names) > $this->subjectLimit($teacher)) $this->fail('Choose different subjects; Khmer teachers may have at most two.');
            foreach ($names as $name) if (! Subject::where('name', $name)->exists()) $this->fail('Choose subjects from the existing catalog.');
            foreach ($periods as $p) {
                if (! in_array($p['weekday'], self::DAYS, true) || self::duration($p['start_time'], $p['end_time']) < 1) $this->fail('Working hours must be valid weekday intervals.');
            }
            DB::table('teacher_profiles')->updateOrInsert(['teacher_id' => $teacher->id], ['weekly_minutes' => $limit]);
            DB::table('teacher_qualifications')->where('teacher_id', $teacher->id)->delete();
            foreach (array_values($names) as $slot => $name) DB::table('teacher_qualifications')->insert(['teacher_id' => $teacher->id, 'subject_name' => $name, 'slot' => $slot + 1]);
            DB::table('teacher_working_periods')->where('teacher_id', $teacher->id)->delete();
            foreach ($periods as $p) DB::table('teacher_working_periods')->insert(['teacher_id' => $teacher->id] + $p);
            foreach ($this->breaks($teacher) as $b) $this->validateBreak($teacher, (array) $b);
            // Legacy lessons are retained and surfaced for review, not rewritten as qualifications.
        });
    }

    public function onDuty(User $teacher, string $day, string $start, string $end): bool
    {
        if (self::minute($end) <= self::minute($start)) return false;
        $periods = $this->periods($teacher)->where('weekday', $day);
        if (! $periods->contains(fn ($p) => ! $p->unavailable && self::minute($p->start_time) <= self::minute($start) && self::minute($p->end_time) >= self::minute($end))) return false;
        return ! $periods->contains(fn ($p) => $p->unavailable && self::overlaps($start, $end, $p->start_time, $p->end_time));
    }

    public function validateSubject(Subject $subject): void
    {
        if (! $subject->teacher_id) return;
        $teacher = User::findOrFail($subject->teacher_id);
        if ($teacher->role !== 'teacher' || $teacher->status !== 'active') $this->fail('Select an active teacher.');
        if (Subject::where('teacher_id', $teacher->id)->where('id', '!=', $subject->id ?: 0)->pluck('name')->push($subject->name)->unique()->count() > $this->subjectLimit($teacher)) $this->fail('A Khmer teacher cannot teach more than two distinct subjects. Review existing assignments.');
        if (! $this->qualifications($teacher)->contains($subject->name)) $this->fail('Confirm this subject qualification in the teacher profile first.');
        $slots = Schedule::where('subject_id', $subject->id ?: 0)->get();
        $minutes = $this->lessons($teacher)->where('subject_id', '!=', $subject->id)->sum(fn ($s) => self::duration($s->starts_at, $s->ends_at));
        if ($minutes + $slots->sum(fn ($s) => self::duration($s->starts_at, $s->ends_at)) > $this->limit($teacher)) $this->fail('This assignment exceeds the teacher’s weekly teaching limit.');
        foreach ($slots as $slot) $this->validateLesson($subject, $slot, false);
    }

    public function validateLesson(Subject $subject, Schedule $slot, bool $workload = true): void
    {
        $teacher = User::find($slot->teacher_id ?: $subject->teacher_id);
        if (! $teacher || $teacher->role !== 'teacher' || $teacher->status !== 'active') $this->fail('Assign an active, qualified teacher first.');
        $this->validateEnglishSession($teacher, $slot, $subject->schoolClass);
        if ($slot->classroom_id) {
            $room = \App\Models\Classroom::findOrFail($slot->classroom_id);
            if ($room->status !== 'active') $this->fail('This physical classroom is out of service.');
            if (! in_array(RoomLessonService::roomKey($slot->room), [RoomLessonService::roomKey($room->room_number), RoomLessonService::roomKey($room->name)], true)) $this->fail('The lesson room label must match its physical classroom. Review the room reservation before moving the lesson.');
            if ($room->capacity < $subject->schoolClass->students()->where('status', 'active')->count()) $this->fail('This classroom does not have enough capacity for the enrolled cohort.');
            if (! in_array(self::duration($slot->starts_at, $slot->ends_at), [60, 120], true) || self::minute($slot->starts_at) % 60 !== 0) $this->fail('Choose a one-hour or two-hour lesson starting on the hour.');
            if (\App\Models\ClassroomBooking::where('classroom_id', $room->id)->where('day_of_week', $slot->day)->where('class_group_id', '!=', $subject->schoolClass->class_group_id)->where('starts_at', '<', $slot->ends_at)->where('ends_at', '>', $slot->starts_at)->exists()) $this->fail('This classroom is reserved for another class. Choose Another Time.');
            $roomLessons = app(RoomLessonService::class)->lessons($room->load('bookings.classSession'), Schedule::with('schoolClass')->where('day', $slot->day)->get());
            if ($roomLessons->contains(fn ($s) => $s->id !== $slot->id && self::overlaps($s->starts_at, $s->ends_at, $slot->starts_at, $slot->ends_at))) $this->fail('This physical classroom already has a lesson. Choose Another Time.');
        }
        if (! in_array($slot->day, self::DAYS, true) || ! TeacherTimetable::withinHours($subject, $slot->starts_at, $slot->ends_at)) $this->fail('Choose a weekday time within the class session.');
        if ($subject->schoolClass?->class_group_id && ! ClassSession::where('class_group_id', $subject->schoolClass->class_group_id)->where('session', $subject->schoolClass->session)->where('starts_at', '<=', $slot->starts_at)->where('ends_at', '>=', $slot->ends_at)->whereHas('classGroup', fn ($q) => $q->where('status', 'active'))->exists()) $this->fail('No active class-session assignment covers this lesson.');
        if (! $this->onDuty($teacher, $slot->day, $slot->starts_at, $slot->ends_at)) $this->fail('The teacher is not on duty or is unavailable during this time.');
        if (! $this->qualifications($teacher)->contains($subject->name)) $this->fail('The teacher is not qualified for this subject.');
        if (Subject::where('teacher_id', $teacher->id)->where('id', '!=', $subject->id ?: 0)->pluck('name')->push($subject->name)->unique()->count() > $this->subjectLimit($teacher)) $this->fail('Resolve the Khmer teacher’s two-subject limit violation before adding lessons.');
        if ($this->breaks($teacher)->contains(fn ($b) => $b->weekday === $slot->day && self::overlaps($slot->starts_at, $slot->ends_at, $b->start_time, $b->end_time))) $this->fail('The teacher has a scheduled break during this time.');
        if (TimetableConflicts::exists($subject, $slot->day, $slot->starts_at, $slot->ends_at, $slot->room ?: '', $slot->id)) $this->fail('This time conflicts with a teacher, class, or classroom lesson.');
        if ($workload && $this->lessons($teacher)->where('id', '!=', $slot->id)->sum(fn ($s) => self::duration($s->starts_at, $s->ends_at)) + self::duration($slot->starts_at, $slot->ends_at) > $this->limit($teacher)) $this->fail('The weekly teaching limit would be exceeded.');
    }

    public function validateBreak(User $teacher, array $break): void
    {
        $day = $break['weekday']; $start = $break['start_time']; $end = $break['end_time'];
        $sessions = ($teacher->program ?: 'Khmer') === 'Khmer' ? array_slice(self::SESSIONS, 0, 2) : ($teacher->program === 'Chinese' ? [['17:30','18:30'],['18:30','19:30']] : self::SESSIONS);
        $session = collect($sessions)->contains(fn ($hours) => self::minute($start) >= self::minute($hours[0]) && self::minute($end) <= self::minute($hours[1]) && (self::minute($start) - self::minute($hours[0])) % 60 === 0);
        if (! in_array($day, self::DAYS, true) || self::duration($start, $end) !== 60 || ! $session || ! $this->onDuty($teacher, $day, $start, $end)) $this->fail('Breaks must last one hour within confirmed working sessions, outside unavailable periods.');
        if ($this->lessons($teacher)->contains(fn ($s) => $s->day === $day && self::overlaps($start, $end, $s->starts_at, $s->ends_at))) $this->fail('This break overlaps a teaching lesson. No lesson has been removed.');
    }

    public function validateEnglishSession(User $teacher, Schedule $lesson, \App\Models\SchoolClass $class): void
    {
        if ($teacher->program !== 'English' || ! $class->session) return;
        $others = Schedule::with('schoolClass')->where('teacher_id', $teacher->id)
            ->where('entry_type', 'academic')->where('day', $lesson->day)
            ->whereHas('schoolClass', fn ($q) => $q->where('session', $class->session))
            ->when($lesson->id, fn ($q) => $q->where('id', '!=', $lesson->id))->get();
        foreach ($others as $other) {
            if (! \App\Support\AcademicYear::overlaps($other->academic_year, $lesson->academic_year)) continue;
            $sameRoom = $lesson->classroom_id && $other->classroom_id
                ? (int) $lesson->classroom_id === (int) $other->classroom_id
                : RoomLessonService::roomKey($lesson->room ?? '') === RoomLessonService::roomKey($other->room ?? '');
            if ($other->schoolClass->timetableOwnerId() !== $class->timetableOwnerId() || ! $sameRoom) {
                $this->fail('An English teacher can teach only one class in one classroom per session each day. Choose another teacher or a different session.');
            }
        }
    }

    public function proposeBreaks(User $teacher): array
    {
        if ($teacher->status !== 'active') $this->fail('Breaks can only be generated for active teachers.');
        $options = [];
        $sessions = $teacher->program === 'Chinese' ? [['17:30','18:30'],['18:30','19:30']] : self::SESSIONS;
        foreach (self::DAYS as $day) foreach ($sessions as [$from, $to]) {
            for ($minute = self::minute($from); $minute + 60 <= self::minute($to); $minute += 60) {
                $format = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                $b = ['weekday' => $day, 'start_time' => $format($minute), 'end_time' => $format($minute + 60), 'duration_minutes' => 60];
                try { $this->validateBreak($teacher, $b); $options[$day][] = $b; } catch (ValidationException $e) { /* Only valid on-duty slots are candidates. */ }
            }
        }
        if (count($options) < 3) $this->fail('This teacher does not have enough available time for three weekly breaks. Please adjust their teaching schedule.');
        return collect(array_keys($options))->shuffle()->take(3)->map(fn ($day) => collect($options[$day])->random())->values()->all();
    }

    public function saveBreaks(User $teacher, array $breaks, bool $replace = false): void
    {
        $this->locked(function () use ($teacher, $breaks, $replace) {
            if ($teacher->status !== 'active') $this->fail('The teacher is no longer active.');
            if (count($breaks) !== 3 || count(array_unique(array_column($breaks, 'weekday'))) !== 3) $this->fail('A completed schedule must have exactly three one-hour breaks on different weekdays.');
            foreach ($breaks as $b) $this->validateBreak($teacher, $b);
            $plan = DB::table('teacher_break_plans')->where('teacher_id', $teacher->id)->first();
            if ($plan && ! $replace) $this->fail('Confirm replacement before regenerating existing breaks.');
            $id = $plan?->id ?? DB::table('teacher_break_plans')->insertGetId(['teacher_id' => $teacher->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('teacher_breaks')->where('teacher_break_plan_id', $id)->delete();
            foreach ($breaks as $b) DB::table('teacher_breaks')->insert(['teacher_break_plan_id' => $id, 'weekday' => $b['weekday'], 'start_time' => $b['start_time'], 'end_time' => $b['end_time'], 'duration_minutes' => 60, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('teacher_break_plans')->where('id', $id)->update(['updated_at' => now()]);
        });
    }

    public function autoAssignments(): array
    {
        // Simulation is always rolled back, including all denormalized teacher IDs.
        return $this->locked(function () {
            DB::beginTransaction();
            try {
                $proposed = []; $missing = [];
                $teachers = User::where('role', 'teacher')->where('status', 'active')->get();
                foreach (Subject::whereNull('teacher_id')->whereHas('schoolClass', fn ($q) => $q->where('program', 'Khmer'))->orderBy('id')->get() as $subject) {
                    if (! $subject->schoolClass?->class_group_id || ! ClassSession::where('class_group_id', $subject->schoolClass->class_group_id)->where('session', $subject->schoolClass->session)->whereHas('classGroup', fn ($q) => $q->where('status', 'active'))->exists()) { $missing[] = $subject->code.': confirm an active class-session link first.'; continue; }
                    if (! Schedule::where('subject_id', $subject->id)->exists()) { $missing[] = $subject->code.': add approved lesson times first.'; continue; }
                    $reasons = [];
                    foreach ($teachers->sortBy(fn ($t) => $this->workload($t)['weekly']) as $teacher) {
                        $subject->teacher_id = $teacher->id;
                        try {
                            $this->validateSubject($subject);
                            $subject->save();
                            Schedule::where('subject_id', $subject->id)->update(['teacher_id' => $teacher->id]);
                            $proposed[] = ['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'description' => $subject->name.' · '.$subject->schoolClass->label.' → '.$teacher->name];
                            continue 2;
                        } catch (ValidationException $e) { $reasons[] = $e->getMessage(); }
                    }
                    $missing[] = $subject->code.': '.(implode(' ', array_unique($reasons)) ?: 'No active teachers.');
                }
                return ['assignments' => $proposed, 'missing' => $missing];
            } finally { DB::rollBack(); }
        });
    }

    public function confirmAssignments(array $assignments): void
    {
        $this->locked(function () use ($assignments) {
            foreach ($assignments as $a) {
                $subject = Subject::findOrFail($a['subject_id']);
                if ($subject->teacher_id) $this->fail('An assignment changed after preview. Generate a new preview; existing assignments were preserved.');
                $subject->teacher_id = $a['teacher_id'];
                $this->validateSubject($subject);
                $subject->save();
                Schedule::where('subject_id', $subject->id)->update(['teacher_id' => $subject->teacher_id]);
            }
        });
    }

    public function demand(): array
    {
        $sessions = ClassSession::whereHas('classGroup', fn ($q) => $q->where('program', 'Khmer')->where('status', 'active'))->with('classGroup')->get();
        $cohortSettings = \App\Models\SchoolClass::where('program','Khmer')->whereNull('shared_timetable_class_id')->get()->keyBy(fn ($c)=>$c->class_group_id.'|'.$c->session);
        $hours = $sessions->sum(fn ($s) => max(0, self::duration($s->starts_at, $s->ends_at) * 5 - (int)($cohortSettings->get($s->class_group_id.'|'.$s->session)?->weekly_relaxation_minutes ?? 180))) / 60;
        $active = User::where('role', 'teacher')->where('status', 'active')->where(fn ($q) => $q->where('program', 'Khmer')->orWhereNull('program'))->get();
        $capacity = $active->sum(fn ($t) => $this->limit($t)) / 60;
        $limit = $this->defaultLimit('Khmer') / 60;
        $cohorts = [];
        foreach ($sessions->groupBy('class_group_id') as $groupSessions) {
            $group = $groupSessions->first()->classGroup;
            $students = \App\Models\Student::with('schoolClass')->where('status', 'active')->whereHas('schoolClass', fn ($q) => $q->where('class_group_id', $group->id))->get()->groupBy('schoolClass.session');
            $morning = $students->get('Morning', collect()); $afternoon = $students->get('Afternoon', collect());
            $identity = fn ($s) => mb_strtolower(trim($s->name)).'|'.substr((string)$s->date_of_birth, 0, 10);
            $shared = $morning->map($identity)->intersect($afternoon->map($identity))->count();
            $cohorts[] = ['class' => $group->display_name, 'morning' => $morning->count(), 'afternoon' => $afternoon->count(),
                'evidence' => $shared ? 'Possible shared students: matching names and birth dates need review' : ($morning->isNotEmpty() && $afternoon->isNotEmpty() ? 'Separate cohorts in current enrollment records' : 'Both cohorts are not established by active enrollment')];
        }
        return ['sessions' => $sessions, 'hours' => $hours, 'active' => $active->count(), 'capacity' => $capacity,
            'target' => $this->settings()->target, 'needed' => max(0, (int) ceil(($hours - $capacity) / max(1, $limit))), 'minimum' => (int) ceil($hours / max(1, $limit)), 'cohorts' => $cohorts];
    }

    public function coverage(): array
    {
        $subjects = Subject::with('schoolClass')->whereHas('schoolClass.classGroup', fn ($q) => $q->where('program', 'Khmer')->where('status', 'active'))->get();
        $lessons = Schedule::with('subject.schoolClass')->whereIn('subject_id', $subjects->modelKeys())->get();
        $teachers = User::where('role', 'teacher')->where('status', 'active')->get();
        $rows = []; $edges = [];
        $add = function ($from, $to, $capacity) use (&$edges) { $edges[$from][$to] = max(0, (int)$capacity); $edges[$to][$from] ??= 0; };
        foreach ($subjects->groupBy('name') as $name => $group) {
            $slots = $lessons->whereIn('subject_id', $group->modelKeys());
            $required = $group->sum(fn ($s) => $s->required_weekly_minutes ?? $slots->where('subject_id', $s->id)->sum(fn ($l) => self::duration($l->starts_at, $l->ends_at)));
            $covered = 0; $conflicts = 0;
            foreach ($slots as $slot) {
                try { $this->validateLesson($slot->subject, $slot); $covered += self::duration($slot->starts_at, $slot->ends_at); }
                catch (ValidationException $e) { $conflicts++; }
            }
            $rows[$name] = ['name' => $name, 'required' => $required / 60, 'covered' => min($required, $covered) / 60,
                'unknown' => $group->whereNull('required_weekly_minutes')->count(), 'conflicts' => $conflicts, 'classes' => $group];
            $add('subject:'.$name, 'sink', max(0, $required - $covered));
        }
        foreach ($teachers as $t) {
            $free = [];
            $periods = $this->periods($t);
            foreach (self::DAYS as $day) foreach (array_slice(self::SESSIONS, 0, 2) as [$start, $end]) {
                foreach ($periods->where('weekday', $day)->where('unavailable', false) as $p) {
                    for ($m = max(self::minute($start), self::minute($p->start_time)); $m < min(self::minute($end), self::minute($p->end_time)); $m++) $free[$day][$m] = true;
                }
            }
            foreach ($periods->where('unavailable', true) as $p) for ($m = self::minute($p->start_time); $m < self::minute($p->end_time); $m++) unset($free[$p->weekday][$m]);
            foreach ($this->lessons($t) as $s) for ($m = self::minute($s->starts_at); $m < self::minute($s->ends_at); $m++) unset($free[$s->day][$m]);
            foreach ($this->breaks($t) as $b) for ($m = self::minute($b->start_time); $m < self::minute($b->end_time); $m++) unset($free[$b->weekday][$m]);
            $capacity = min((int) round($this->workload($t)['remaining'] * 60), array_sum(array_map('count', $free)));
            $add('source', 'teacher:'.$t->id, $capacity);
            $assigned = Subject::where('teacher_id', $t->id)->pluck('name');
            foreach ($this->qualifications($t) as $name) {
                if (! isset($rows[$name]) || $assigned->concat([$name])->unique()->count() > $this->subjectLimit($t)) continue;
                $sessions = $rows[$name]['classes']->pluck('schoolClass.session')->filter()->unique();
                $usable = 0;
                foreach ($free as $day => $minutes) foreach (array_keys($minutes) as $m) {
                    if ($sessions->contains(fn ($session) => isset(self::SESSIONS[$session]) && $m >= self::minute(self::SESSIONS[$session][0]) && $m < self::minute(self::SESSIONS[$session][1]))) $usable++;
                }
                $add('teacher:'.$t->id, 'subject:'.$name, min($capacity, $usable));
            }
        }
        while (true) {
            $parents = ['source' => null]; $queue = ['source'];
            for ($i = 0; $i < count($queue) && ! array_key_exists('sink', $parents); $i++) foreach ($edges[$queue[$i]] ?? [] as $next => $capacity) {
                if ($capacity > 0 && ! array_key_exists($next, $parents)) { $parents[$next] = $queue[$i]; $queue[] = $next; }
            }
            if (! array_key_exists('sink', $parents)) break;
            $amount = PHP_INT_MAX;
            for ($node = 'sink'; $node !== 'source'; $node = $parents[$node]) $amount = min($amount, $edges[$parents[$node]][$node]);
            for ($node = 'sink'; $node !== 'source'; $node = $parents[$node]) { $edges[$parents[$node]][$node] -= $amount; $edges[$node][$parents[$node]] += $amount; }
        }
        foreach ($rows as $name => &$row) { $row['missing'] = $edges['subject:'.$name]['sink'] / 60; $row['available'] = $row['required'] - $row['missing']; }
        unset($row);
        return array_values($rows);
    }

    public function fail(string $message): never { throw ValidationException::withMessages(['teacher' => $message]); }
}
