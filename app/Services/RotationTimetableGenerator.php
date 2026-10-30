<?php

namespace App\Services;

use App\Models\{Classroom, ClassroomBooking, ClassSession, Schedule, SchoolClass, Subject, User};
use Illuminate\Support\Facades\DB;

/** Plans individual lessons from approved curriculum, without modifying preview data. */
class RotationTimetableGenerator
{
    public function __construct(private TeacherManagementService $rules) {}

    public function fingerprint(): string
    {
        return hash('sha256', app(TeacherScheduleGenerator::class)->fingerprint()
            .DB::table('classrooms')->orderBy('id')->get()->toJson()
            .DB::table('classroom_bookings')->orderBy('id')->get()->toJson());
    }

    public function preview(SchoolClass $class, bool $replace = false, ?\Illuminate\Support\Collection $preservedRelaxation = null): array
    {
        return $this->rules->locked(function () use ($class, $replace, $preservedRelaxation) {
            $class->refresh()->load('subjects');
            if (! in_array($class->session, ['Morning', 'Afternoon', 'Evening'], true) || ($class->program === 'Khmer' && $class->session === 'Evening')) $this->rules->fail('Choose a permitted class session.');
            $session = ClassSession::with('classGroup')->where('class_group_id', $class->class_group_id)->where('session', $class->session)->first();
            if (! $session || $session->classGroup->status !== 'active') $this->rules->fail('Confirm the active class identity and session before generation.');
            if ($class->program === 'Khmer' && ! in_array((int) $session->classGroup->grade, range(7, 12), true)) $this->rules->fail('Khmer timetable generation supports Grades 7–12.');
            $existing = Schedule::with('subject')->where('school_class_id', $class->id)->get();
            if ($existing->isNotEmpty() && ! $replace) $this->rules->fail('This class already has a timetable. Choose Regenerate Schedule to preview replacement.');
            if ($preservedRelaxation !== null) $existing = $existing->merge($preservedRelaxation);
            if ($class->subjects->isEmpty() || $class->subjects->contains(fn ($s) => $s->required_weekly_minutes === null)) $this->rules->fail('Configure approved weekly hours for every class subject in Scheduling Settings first.');
            $required = $class->subjects->sum('required_weekly_minutes');
            $relaxMinutes = (int) $class->weekly_relaxation_minutes;
            if ($required + $relaxMinutes > 1200) $this->rules->fail('Approved curriculum and relaxation exceed the 20 available session hours. No subject was removed.');
            $outside = Schedule::with(['subject', 'schoolClass'])->where('school_class_id', '!=', $class->id)->get();
            $bookings = ClassroomBooking::with(['classSession', 'classroom'])->get();
            $rooms = Classroom::where('status', 'active')->get();
            $teachers = User::where('role', 'teacher')->where('status', 'active')->get()->keyBy('id');
            $qualifications = []; $limits = []; $breaks = []; $newBreaks = []; $used = []; $names = [];
            foreach ($teachers as $teacher) {
                $qualifications[$teacher->id] = $this->rules->qualifications($teacher)->all();
                $limits[$teacher->id] = $this->rules->limit($teacher);
                $names[$teacher->id] = Subject::where('teacher_id', $teacher->id)->pluck('name')->unique()->all();
                $breaks[$teacher->id] = $this->rules->breaks($teacher)->all();
                $used[$teacher->id] = $outside->filter(fn ($s) => ($s->teacher_id ?? $s->subject?->teacher_id) === $teacher->id)->sum(fn ($s) => $this->rules::duration($s->starts_at, $s->ends_at));
                $names[$teacher->id] = array_unique([...$names[$teacher->id], ...$outside->where('teacher_id', $teacher->id)->pluck('subject.name')->filter()->all()]);
                if ($used[$teacher->id] + $class->subjects->where('teacher_id', $teacher->id)->sum('required_weekly_minutes') > $limits[$teacher->id] && $class->subjects->where('teacher_id', $teacher->id)->sum('required_weekly_minutes') > 0) $this->rules->fail($teacher->name.' would exceed the configured weekly teaching limit.');
            }
            $tasks = []; $assignmentOptions = [];
            foreach ($class->subjects as $subject) {
                $minutes = (int) $subject->required_weekly_minutes;
                $lessonMinutes = \App\Support\KhmerLessonDuration::minutes($subject);
                \App\Support\KhmerLessonDuration::validateWeeklyHours($subject, $minutes);
                if ($minutes % 60 !== 0 || ! in_array((int) $subject->lesson_minutes, [60, 120], true)) $this->rules->fail('Curriculum must use whole hours and lesson durations of one or two hours.');
                if (! $minutes) continue;
                $eligible = $teachers->filter(fn ($t) => ($class->program !== 'Khmer' || ($t->program ?: 'Khmer') === 'Khmer') && (! $subject->teacher_id || $subject->teacher_id === $t->id) && in_array($subject->name, $qualifications[$t->id], true) && count(array_unique([...$names[$t->id], $subject->name])) <= 2);
                if ($eligible->isEmpty()) $this->rules->fail('Assign an active teacher with confirmed qualifications and at most two subjects for '.$subject->name.'.');
                $assignmentOptions[$subject->id] = $eligible->keys()->all();
                foreach ($eligible as $teacher) if (! $breaks[$teacher->id]) {
                    $newBreaks[$teacher->id] = $this->rules->proposeBreaks($teacher);
                    $breaks[$teacher->id] = array_map(fn ($b) => (object) $b, $newBreaks[$teacher->id]);
                }
                foreach ($eligible as $teacher) {
                    $periods = collect($breaks[$teacher->id]);
                    if ($periods->count() !== 3 || $periods->pluck('weekday')->unique()->count() !== 3) $this->rules->fail($teacher->name.' needs three one-hour designated free periods on different weekdays.');
                    foreach ($periods as $period) $this->rules->validateBreak($teacher, (array) $period);
                }
                while ($minutes > 0) {
                    $duration = $class->program === 'Khmer' ? $lessonMinutes : min($lessonMinutes, $minutes, $this->rules::duration($session->starts_at, $session->ends_at));
                    $tasks[] = ['subject_id' => $subject->id, 'subject_name' => $subject->name, 'minutes' => $duration, 'entry_type' => 'academic'];
                    $minutes -= $duration;
                }
            }
            // Preserve existing relaxation times as fixed commitments when compatible.
            $fixed = $existing->where('entry_type', 'relaxation')->map(fn ($s) => $s->only(['school_class_id', 'day', 'starts_at', 'ends_at', 'room', 'classroom_id', 'entry_type', 'subject_id', 'teacher_id', 'title_en', 'title_km']))->values()->all();
            $validatedFixed = [];
            foreach ($fixed as &$row) {
                if ($this->rules::duration($row['starts_at'], $row['ends_at']) !== 60) $this->rules->fail('Existing relaxation periods must each last one hour. Review before regeneration.');
                $reservation = $bookings->first(fn ($b) => $b->class_group_id === $class->class_group_id && $b->classSession?->session === $class->session && $b->day_of_week === $row['day'] && $this->rules::minute($b->starts_at) <= $this->rules::minute($row['starts_at']) && $this->rules::minute($b->ends_at) >= $this->rules::minute($row['ends_at']));
                $room = $row['classroom_id'] ? $rooms->firstWhere('id', $row['classroom_id']) : $reservation?->classroom;
                [$from, $to] = [$session->starts_at, $session->ends_at];
                if (! $room || $room->status !== 'active' || ! in_array($row['day'], $this->rules::DAYS, true) || $this->rules::minute($row['starts_at']) < $this->rules::minute($from) || $this->rules::minute($row['ends_at']) > $this->rules::minute($to)) $this->rules->fail('Review the existing student relaxation time and physical room before regeneration.');
                $row['classroom_id'] = $room->id;
                $row['room'] = $room->room_number;
                if (! $this->fits($row, $outside->map(fn ($s) => $s->getAttributes())->all(), $validatedFixed, $used, $limits, $names, $class->subjects->keyBy('id')->all())) $this->rules->fail('Existing student relaxation conflicts with another commitment. Choose Another Time.');
                $validatedFixed[] = $row;
            }
            unset($row);
            $fixedMinutes = collect($fixed)->sum(fn ($s) => $this->rules::duration($s['starts_at'], $s['ends_at']));
            if ($fixedMinutes > $relaxMinutes) $this->rules->fail('Existing relaxation exceeds the configured requirement. Review it before regeneration.');
            for ($m = $fixedMinutes; $m < $relaxMinutes; $m += 60) $tasks[] = ['subject_id' => null, 'subject_name' => null, 'minutes' => 60, 'entry_type' => 'relaxation'];

            $options = [];
            $studentCount = $class->students()->where('status', 'active')->count();
            foreach ($tasks as $index => $task) foreach ($this->rules::DAYS as $day) {
                $reservation = $bookings->first(fn ($b) => $b->class_group_id === $class->class_group_id && $b->classSession?->session === $class->session && $b->day_of_week === $day);
                $room = $reservation?->classroom;
                if (! $room || $room->status !== 'active' || $room->capacity < $studentCount) continue;
                [$from, $to] = [$session->starts_at, $session->ends_at];
                for ($m = max($this->rules::minute($from), $this->rules::minute($session->starts_at)); $m + $task['minutes'] <= min($this->rules::minute($to), $this->rules::minute($session->ends_at)); $m += 60) {
                    $start = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                    $end = sprintf('%02d:%02d', intdiv($m + $task['minutes'], 60), ($m + $task['minutes']) % 60);
                    if ($reservation && ($m < $reservation->starts_minute || $m + $task['minutes'] > $reservation->ends_minute)) continue;
                    if ($bookings->contains(fn ($b) => $b->classroom_id === $room->id && $b->class_group_id !== $class->class_group_id && $b->day_of_week === $day && $this->rules::overlaps($start, $end, $b->starts_at, $b->ends_at))) continue;
                    foreach ($task['subject_id'] ? $assignmentOptions[$task['subject_id']] : [null] as $teacherId) {
                        if ($teacherId && (! $this->rules->onDuty($teachers[$teacherId], $day, $start, $end) || collect($breaks[$teacherId])->contains(fn ($b) => $b->weekday === $day && $this->rules::overlaps($start, $end, $b->start_time, $b->end_time)))) continue;
                        $row = ['school_class_id' => $class->id, 'subject_id' => $task['subject_id'], 'teacher_id' => $teacherId, 'classroom_id' => $room->id, 'room' => $room->room_number, 'entry_type' => $task['entry_type'], 'day' => $day, 'starts_at' => $start, 'ends_at' => $end, 'title_en' => $task['entry_type'] === 'relaxation' ? 'Relaxation Time' : null];
                        if ($this->fits($row, [...$outside->map(fn ($s) => $s->getAttributes())->all(), ...$fixed], [], $used, $limits, $names, $class->subjects->keyBy('id')->all())) $options[$index][] = $row;
                    }
                }
            }
            foreach (array_keys($tasks) as $index) if (empty($options[$index])) $this->rules->fail($class->label.' / '.($tasks[$index]['subject_name'] ?? 'Relaxation').' ('.($tasks[$index]['minutes'] / 60).' consecutive hours): no time fits the physical room reservation/capacity, teacher duty hours, designated free periods and other-program commitments. No subject hours were removed.');
            $steps = 0;
            $rows = $this->search($options, [], $used, $limits, $names, $class->subjects->keyBy('id')->all(), $steps);
            if ($rows === null) $this->rules->fail('No valid timetable was found within the search limit. Review curriculum, room reservations and teacher capacity. No changes saved.');
            $usedTeachers = collect($rows)->pluck('teacher_id')->filter()->unique();
            return ['class_id' => $class->id, 'replace' => $replace, 'rows' => [...$fixed, ...$rows], 'new_breaks' => array_intersect_key($newBreaks, array_flip($usedTeachers->all())), 'fingerprint' => $this->fingerprint()];
        });
    }

    private function fits(array $row, array $busy, array $chosen, array $used, array $limits, array $names, array $subjects): bool
    {
        $teacher = $row['teacher_id'];
        $duration = $this->rules::duration($row['starts_at'], $row['ends_at']);
        if ($teacher) {
            $teacherRows = collect($chosen)->where('teacher_id', $teacher);
            if (($used[$teacher] ?? 0) + $teacherRows->sum(fn ($s) => $this->rules::duration($s['starts_at'], $s['ends_at'])) + $duration > $limits[$teacher]) return false;
            $assignedNames = array_merge($names[$teacher], $teacherRows->map(fn ($s) => $subjects[$s['subject_id']]->name)->all(), [$subjects[$row['subject_id']]->name]);
            if (count(array_unique($assignedNames)) > 2) return false;
            if (collect($chosen)->contains(fn ($s) => $s['subject_id'] === $row['subject_id'] && $s['teacher_id'] !== $teacher)) return false;
        }
        foreach ([...$busy, ...$chosen] as $s) {
            if ($s['day'] !== $row['day']) continue;
            $sameClass = ($s['school_class_id'] ?? null) === $row['school_class_id'];
            if ($sameClass && $row['entry_type'] === 'relaxation' && ($s['entry_type'] ?? null) === 'relaxation') return false;
            $sameRoom = ($s['classroom_id'] ?? null) === $row['classroom_id'];
            if (($sameClass || $sameRoom || ($teacher && ($s['teacher_id'] ?? null) === $teacher)) && $this->rules::overlaps($row['starts_at'], $row['ends_at'], $s['starts_at'], $s['ends_at'])) return false;
            if ($sameClass && $teacher && ($s['subject_id'] ?? null) === $row['subject_id'] && ($s['ends_at'] === $row['starts_at'] || $row['ends_at'] === $s['starts_at']) && $duration + $this->rules::duration($s['starts_at'], $s['ends_at']) > 120) return false;
        }
        return true;
    }

    private function search(array $options, array $chosen, array $used, array $limits, array $names, array $subjects, int &$steps): ?array
    {
        if (++$steps > 30000) return null;
        if (! $options) return $chosen;
        $filtered = [];
        foreach ($options as $index => $rows) {
            $filtered[$index] = array_values(array_filter($rows, fn ($row) => $this->fits($row, [], $chosen, $used, $limits, $names, $subjects)));
            if (! $filtered[$index]) return null;
        }
        uasort($filtered, fn ($a, $b) => count($a) <=> count($b));
        $key = array_key_first($filtered);
        $choices = $filtered[$key];
        unset($filtered[$key]);
        // Spread lessons across days, rather than filling an entire session with one subject.
        usort($choices, fn ($a, $b) => collect($chosen)->where('day', $a['day'])->count() <=> collect($chosen)->where('day', $b['day'])->count());
        foreach ($choices as $row) {
            $result = $this->search($filtered, [...$chosen, $row], $used, $limits, $names, $subjects, $steps);
            if ($result !== null) return $result;
        }
        return null;
    }

    public function confirm(array $plan): void
    {
        $this->rules->locked(function () use ($plan) {
            if (! hash_equals($plan['fingerprint'], $this->fingerprint())) $this->rules->fail('Scheduling data changed after preview. Generate a new preview.');
            $class = SchoolClass::with('subjects')->findOrFail($plan['class_id']);
            $rows = collect($plan['rows']);
            if ($rows->contains(fn ($row) => $row['school_class_id'] !== $class->id)) $this->rules->fail('Preview includes a different cohort.');
            foreach ($class->subjects as $subject) {
                \App\Support\KhmerLessonDuration::validateWeeklyHours($subject, (int) $subject->required_weekly_minutes);
                if ($class->program === 'Khmer' && $rows->where('subject_id', $subject->id)->contains(fn ($row) => $this->rules::duration($row['starts_at'], $row['ends_at']) !== \App\Support\KhmerLessonDuration::minutes($subject))) $this->rules->fail('Incorrect lesson duration for '.$subject->name.'. Generate a new preview.');
                $actual = $rows->where('subject_id', $subject->id)->sum(fn ($row) => $this->rules::duration($row['starts_at'], $row['ends_at']));
                if ($subject->required_weekly_minutes === null || $actual !== (int) $subject->required_weekly_minutes) $this->rules->fail('Preview does not cover approved hours for '.$subject->name.'.');
            }
            if ($rows->where('entry_type', 'relaxation')->sum(fn ($row) => $this->rules::duration($row['starts_at'], $row['ends_at'])) !== (int) $class->weekly_relaxation_minutes) $this->rules->fail('Preview does not include the required relaxation hours.');
            Schedule::where('school_class_id', $plan['class_id'])->update(['cancelled_at' => now()]);
            foreach ($plan['new_breaks'] as $id => $breaks) $this->rules->saveBreaks(User::findOrFail($id), $breaks);
            foreach ($plan['rows'] as $row) {
                if ($row['subject_id']) {
                    $subject = Subject::findOrFail($row['subject_id']);
                    if (! $subject->teacher_id) $subject->update(['teacher_id' => $row['teacher_id']]);
                    if ($subject->teacher_id !== $row['teacher_id']) $this->rules->fail('Subject assignment changed. Generate a new preview.');
                }
                Schedule::create($row + ['generated_at' => now(), 'academic_year' => now('Asia/Phnom_Penh')->year.'-'.(now('Asia/Phnom_Penh')->year + 1)]);
            }
        });
    }
}
