<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\ClassroomBooking;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Support\KhmerLessonDuration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KhmerWeekPlanner
{
    public function __construct(private TeacherManagementService $rules, private LessonManagementService $lessons) {}

    public function preview(SchoolClass $class, string $year): array
    {
        if ($class->program !== 'Khmer') {
            $this->rules->fail('Automatic subject and teacher planning is available for Khmer only.');
        }
        if ($class->shared_timetable_class_id) {
            $this->rules->fail('Choose the shared timetable owner to auto-fill this class.');
        }

        return $this->rules->locked(function () use ($class, $year) {
            DB::beginTransaction();
            try {
                $deadline = microtime(true) + 12;
                $commitments = Schedule::where(fn ($q) => $q->where('academic_year', $year)->orWhereNull('academic_year'))->get();
                $bookings = ClassroomBooking::with('classSession')->get();
                $teachers = User::where('role', 'teacher')->where('status', 'active')->where('program', 'Khmer')->orderBy('id')->get();
                $qualifications = DB::table('teacher_qualifications')->whereIn('teacher_id', $teachers->modelKeys())->get()->groupBy('teacher_id');
                $subjects = $class->subjects()->where(fn ($q) => $q->whereNull('required_weekly_minutes')->orWhere('required_weekly_minutes', '>', 0))->get();
                $candidates = $subjects->mapWithKeys(fn ($subject) => [$subject->id => $teachers->filter(fn ($teacher) => $qualifications->get($teacher->id, collect())->contains('subject_name', $subject->name))->values()]);
                // Reserve consecutive blocks before flexible one-hour lessons.
                // Teacher count alone can put Mathematics last and fragment all
                // of its otherwise available two-hour periods.
                $subjects = $subjects->sortBy(fn ($subject) => sprintf('%04d-%04d-%04d', 120 - KhmerLessonDuration::minutes($subject), 10080 - (int) $subject->required_weekly_minutes, $candidates[$subject->id]->count()));
                $rooms = Classroom::where('status', 'active')->orderBy('id')->get();
                [$from, $until] = $class->sessionHours();
                $rows = $unfilled = [];
                foreach ($subjects as $subject) {
                    $duration = KhmerLessonDuration::minutes($subject);
                    $used = Schedule::where('subject_id', $subject->id)->where(fn ($q) => $q->where('academic_year', $year)->orWhereNull('academic_year'))->get()->sum(fn ($s) => $this->rules::duration($s->starts_at, $s->ends_at));
                    if ($subject->required_weekly_minutes === null) {
                        $unfilled[] = ['subject' => $subject->name, 'remaining_minutes' => null, 'reason' => 'Set weekly subject hours before auto-filling.'];

                        continue;
                    }
                    $remaining = max(0, $subject->required_weekly_minutes - $used);
                    // Try teachers with enough shared free blocks before locking
                    // the subject to the first teacher free on Monday.
                    $ranked = $candidates[$subject->id]->sortByDesc(function ($teacher) use ($class, $commitments, $duration, $from, $until) {
                        $periods = $this->rules->periods($teacher);
                        $breaks = $this->rules->breaks($teacher);
                        $score = 0;
                        foreach (TeacherManagementService::DAYS as $day) {
                            for ($m = $this->rules::minute($from); $m + $duration <= $this->rules::minute($until); $m += 60) {
                                $start = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                                $end = sprintf('%02d:%02d', intdiv($m + $duration, 60), ($m + $duration) % 60);
                                if (! $periods->contains(fn ($p) => $p->weekday === $day && ! $p->unavailable && $this->rules::minute($p->start_time) <= $m && $this->rules::minute($p->end_time) >= $m + $duration)) {
                                    continue;
                                }
                                if ($periods->contains(fn ($p) => $p->weekday === $day && $p->unavailable && $this->rules::overlaps($start, $end, $p->start_time, $p->end_time))) {
                                    continue;
                                }
                                if ($breaks->contains(fn ($b) => $b->weekday === $day && $this->rules::overlaps($start, $end, $b->start_time, $b->end_time))) {
                                    continue;
                                }
                                if ($commitments->contains(fn ($s) => $s->day === $day && ($s->teacher_id === $teacher->id || $s->school_class_id === $class->id) && $this->rules::overlaps($start, $end, $s->starts_at, $s->ends_at))) {
                                    continue;
                                }
                                $score++;
                                $m += $duration - 60;
                            }
                        }

                        return $score;
                    });
                    $reason = $candidates[$subject->id]->isEmpty() ? 'No active qualified Khmer teacher for this subject.' : 'No available teacher and classroom fit the remaining periods.';
                    while ($remaining >= $duration) {
                        $placed = false;
                        $roomCache = [];
                        $assignedTeacher = $commitments->firstWhere('subject_id', $subject->id)?->teacher_id;
                        foreach ($ranked as $teacher) {
                            if ($assignedTeacher && $teacher->id !== $assignedTeacher) {
                                continue;
                            }
                            foreach (TeacherManagementService::DAYS as $day) {
                                for ($start = $this->rules::minute($from); $start + $duration <= $this->rules::minute($until); $start += 60) {
                                    $format = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                                    $base = ['school_class_id' => $class->id, 'subject_id' => $subject->id, 'academic_year' => $year, 'entry_type' => 'academic', 'day' => $day, 'starts_at' => $format($start), 'ends_at' => $format($start + $duration)];
                                    if ($candidates[$subject->id]->isEmpty()) {
                                        break 2;
                                    }
                                    if (microtime(true) >= $deadline) {
                                        $reason = 'Planning time limit reached. Save the filled lessons, then run auto-fill again for the remaining hours.';
                                        break 2;
                                    }
                                    $busy = $commitments->filter(fn ($s) => $s->day === $day && $this->rules::overlaps($base['starts_at'], $base['ends_at'], $s->starts_at, $s->ends_at));
                                    if ($busy->contains('school_class_id', $class->id)) {
                                        continue;
                                    }
                                    if ($busy->contains('teacher_id', $teacher->id)) {
                                        $reason = 'No shared free block remains for this class and its qualified teachers; draft lessons also occupy time.';

                                        continue;
                                    }
                                    if (! $this->rules->onDuty($teacher, $day, $base['starts_at'], $base['ends_at'])) {
                                        $reason = 'Qualified teachers are unavailable during the remaining periods.';

                                        continue;
                                    }
                                    $reservations = $bookings->filter(fn ($b) => $b->day_of_week === $day && $this->rules::overlaps($base['starts_at'], $base['ends_at'], $b->starts_at, $b->ends_at));
                                    $assigned = $reservations->filter(fn ($b) => $b->class_group_id === $class->class_group_id && $b->classSession?->session === $class->session);
                                    $possibleRooms = $rooms->reject(fn ($r) => $busy->contains('classroom_id', $r->id)
                                        || ($assigned->isNotEmpty() && ! $assigned->contains('classroom_id', $r->id))
                                        || $reservations->contains(fn ($b) => $b->classroom_id === $r->id && ($b->class_group_id !== $class->class_group_id || $b->classSession?->session !== $class->session)));
                                    // Validate each room once before trying teachers.
                                    $key = $day.'-'.$start;
                                    if (! array_key_exists($key, $roomCache)) {
                                        $roomCache[$key] = $possibleRooms->first(function ($room) use ($base) {
                                            try {
                                                $this->lessons->validate(new Schedule($base + ['classroom_id' => $room->id, 'room' => $room->room_number]), false);

                                                return true;
                                            } catch (ValidationException $e) {
                                                return false;
                                            }
                                        });
                                    }
                                    $room = $roomCache[$key];
                                    if (! $room) {
                                        continue;
                                    }
                                    $row = $base + ['teacher_id' => $teacher->id, 'classroom_id' => $room->id, 'room' => $room->room_number];
                                    try {
                                        $slot = new Schedule($row);
                                        $this->lessons->validate($slot);
                                        // Staging makes later choices see workload, teacher consistency,
                                        // room occupancy and weekly limits. Always rolled back below.
                                        $slot->saveQuietly();
                                        $commitments->push($slot);
                                        $rows[] = $row;
                                        $remaining -= $duration;
                                        $placed = true;
                                        break 3;
                                    } catch (ValidationException $e) {
                                        $reason = $e->getMessage();
                                    }
                                }
                            }
                        }
                        if (! $placed) {
                            break;
                        }
                    }
                    if ($remaining > 0) {
                        $unfilled[] = ['subject' => $subject->name, 'remaining_minutes' => $remaining, 'reason' => $remaining < $duration ? 'Weekly hours must fit a complete lesson block.' : $reason];
                    }
                }

                return ['slots' => $rows, 'unfilled' => $unfilled, 'complete' => empty($unfilled)];
            } finally {
                DB::rollBack();
            }
        });
    }
}
