<?php

namespace App\Services;

use App\Models\{Schedule, Subject, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Builds additive weekly plans; shared student lessons are never silently moved. */
class TeacherScheduleGenerator
{
    public function __construct(private TeacherManagementService $rules) {}

    public function fingerprint(): string
    {
        $state = [];
        foreach (['users', 'subjects', 'schedules', 'school_classes', 'class_groups', 'class_sessions', 'teacher_profiles', 'teacher_qualifications', 'teacher_working_periods', 'teacher_break_plans', 'teacher_breaks', 'teacher_management_settings', 'classrooms', 'classroom_bookings'] as $table) {
            $state[$table] = DB::table($table)->orderBy($table === 'teacher_profiles' ? 'teacher_id' : 'id')->get();
        }
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    public function preview(User $teacher): array
    {
        return $this->rules->locked(function () use ($teacher) {
            $teacher->refresh();
            if ($teacher->role !== 'teacher' || $teacher->status !== 'active') $this->rules->fail('Choose an active teacher.');
            $subjects = Subject::with('schoolClass')->where('teacher_id', $teacher->id)->get();
            foreach ($subjects as $subject) $this->rules->validateSubject($subject);
            foreach ($this->rules->lessons($teacher) as $lesson) $this->rules->validateLesson($lesson->subject, $lesson);

            $tasks = [];
            foreach ($subjects as $subject) {
                $existing = $this->rules->lessons($teacher)->where('subject_id', $subject->id)->sum(fn ($s) => $this->rules::duration($s->starts_at, $s->ends_at));
                // Unknown curriculum is never replaced with invented teaching demand.
                $missing = max(0, ($subject->required_weekly_minutes ?? $existing) - $existing);
                if ($missing % 60 !== 0) $this->rules->fail('Approved remaining curriculum must use whole hours for '.$subject->name.'.');
                if ($missing && blank($subject->schoolClass?->room)) $this->rules->fail('Assign a classroom for '.$subject->name.' first.');
                while ($missing > 0) {
                    $minutes = min((int) $subject->lesson_minutes, $missing);
                    if (! in_array($minutes, [60, 120], true)) $this->rules->fail('Configure a lesson duration of one or two hours.');
                    $tasks[] = ['subject' => $subject, 'minutes' => $minutes];
                    $missing -= $minutes;
                }
            }
            if ($this->rules->workload($teacher)['weekly'] * 60 + array_sum(array_column($tasks, 'minutes')) > $this->rules->limit($teacher)) $this->rules->fail('Approved curriculum exceeds this teacher’s weekly teaching limit.');

            $candidates = [];
            foreach (collect($tasks)->unique(fn ($t) => $t['subject']->id.'|'.$t['minutes']) as $task) {
                $subject = $task['subject'];
                $minutes = $task['minutes'];
                foreach ($this->rules::DAYS as $day) foreach (array_slice($this->rules::SESSIONS, 0, 2) as [$from, $to]) {
                    for ($m = $this->rules::minute($from); $m + $minutes <= $this->rules::minute($to); $m += 60) {
                        $row = ['subject_id' => $subject->id, 'day' => $day, 'starts_at' => sprintf('%02d:00', intdiv($m, 60)), 'ends_at' => sprintf('%02d:00', intdiv($m + $minutes, 60)), 'room' => $subject->schoolClass->room];
                        $reservation = \App\Models\ClassroomBooking::with('classroom')->where('class_group_id', $subject->schoolClass->class_group_id)->where('day_of_week', $day)->whereHas('classSession', fn ($q) => $q->where('session', $subject->schoolClass->session))->first();
                        if ($reservation) {
                            if ($m < $reservation->starts_minute || $m + $minutes > $reservation->ends_minute) continue;
                            $row['classroom_id'] = $reservation->classroom_id;
                            $row['room'] = $reservation->classroom->room_number;
                        }
                        try {
                            $this->rules->validateLesson($subject, new Schedule($row));
                            $candidates[$subject->id.'|'.$minutes][] = $row;
                        } catch (ValidationException) { /* Exclude occupied or unavailable slots. */ }
                    }
                }
            }
            for ($attempt = 0; $attempt < 40; $attempt++) {
                $breaks = $this->rules->proposeBreaks($teacher);
                $options = [];
                foreach ($tasks as $index => $task) {
                    $options[$index] = array_values(array_filter($candidates[$task['subject']->id.'|'.$task['minutes']] ?? [], fn ($row) => ! collect($breaks)->contains(fn ($b) => $b['weekday'] === $row['day'] && $this->rules::overlaps($b['start_time'], $b['end_time'], $row['starts_at'], $row['ends_at']))));
                }
                $distinct = [];
                foreach ($options as $slots) foreach ($slots as $row) $distinct[$row['day'].'|'.$row['starts_at']] = true;
                if (count($distinct) < count($tasks)) continue;
                $steps = 0;
                $lessons = $this->search($options, [], $steps);
                if ($lessons !== null) return ['teacher_id' => $teacher->id, 'breaks' => $breaks, 'lessons' => $lessons, 'fingerprint' => $this->fingerprint()];
            }
            $this->rules->fail('No schedule fits the approved curriculum, working hours and three free periods. Choose Another Time or adjust the approved workload.');
        });
    }

    private function search(array $options, array $chosen, int &$steps): ?array
    {
        if (++$steps > 20000) return null;
        if (! $options) return $chosen;
        uasort($options, fn ($a, $b) => count($a) <=> count($b));
        $key = array_key_first($options);
        $choices = $options[$key];
        unset($options[$key]);
        foreach ($choices as $row) {
            $remaining = [];
            foreach ($options as $index => $slots) {
                // Every task belongs to this teacher, so any simultaneous lesson conflicts.
                $remaining[$index] = array_values(array_filter($slots, fn ($s) => $s['day'] !== $row['day'] || ! $this->rules::overlaps($s['starts_at'], $s['ends_at'], $row['starts_at'], $row['ends_at'])));
                if (! $remaining[$index]) continue 2;
            }
            $result = $this->search($remaining, [...$chosen, $row], $steps);
            if ($result !== null) return $result;
        }
        return null;
    }

    public function confirm(array $plan, bool $replace): void
    {
        $this->rules->locked(function () use ($plan, $replace) {
            if (! hash_equals($plan['fingerprint'], $this->fingerprint())) $this->rules->fail('Scheduling data changed after preview. Generate a new preview.');
            $teacher = User::findOrFail($plan['teacher_id']);
            $this->rules->saveBreaks($teacher, $plan['breaks'], $replace);
            foreach ($plan['lessons'] as $row) {
                $subject = Subject::findOrFail($row['subject_id']);
                if ($subject->teacher_id !== $teacher->id) $this->rules->fail('The assigned teacher changed. Generate a new preview.');
                $slot = new Schedule($row);
                $this->rules->validateLesson($subject, $slot);
                $slot->save();
            }
        });
    }
}
