<?php

namespace App\Services;

use App\Models\{Classroom, Schedule, SchoolClass, Subject, User};
use App\Support\KhmerLessonDuration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KhmerWeekPlanner
{
    public function __construct(private TeacherManagementService $rules, private LessonManagementService $lessons) {}

    public function preview(SchoolClass $class, string $year): array
    {
        if ($class->program !== 'Khmer') $this->rules->fail('Automatic subject and teacher planning is available for Khmer only.');
        if ($class->shared_timetable_class_id) $this->rules->fail('Choose the shared timetable owner to auto-fill this class.');

        return $this->rules->locked(function () use ($class, $year) {
            DB::beginTransaction();
            try {
                $deadline = microtime(true) + 12;
                $commitments = Schedule::where(fn ($q) => $q->where('academic_year', $year)->orWhereNull('academic_year'))->get();
                $bookings = \App\Models\ClassroomBooking::with('classSession')->get();
                $teachers = User::where('role', 'teacher')->where('status', 'active')->where('program', 'Khmer')->orderBy('id')->get();
                $qualifications = DB::table('teacher_qualifications')->whereIn('teacher_id', $teachers->modelKeys())->get()->groupBy('teacher_id');
                $subjects = $class->subjects()->where(fn ($q) => $q->whereNull('required_weekly_minutes')->orWhere('required_weekly_minutes', '>', 0))->get();
                $candidates = $subjects->mapWithKeys(fn ($subject) => [$subject->id => $teachers->filter(fn ($teacher) => $qualifications->get($teacher->id, collect())->contains('subject_name', $subject->name))->values()]);
                // Schedule scarce qualifications and longer blocks first.
                $subjects = $subjects->sortBy(fn ($subject) => sprintf('%04d-%04d', $candidates[$subject->id]->count(), 120 - KhmerLessonDuration::minutes($subject)));
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
                    $reason = $candidates[$subject->id]->isEmpty() ? 'No active qualified Khmer teacher for this subject.' : 'No available teacher and classroom fit the remaining periods.';
                    while ($remaining >= $duration) {
                        $placed = false;
                        foreach (TeacherManagementService::DAYS as $day) {
                            for ($start = $this->rules::minute($from); $start + $duration <= $this->rules::minute($until); $start += 60) {
                                $format = fn ($m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                                $base = ['school_class_id' => $class->id, 'subject_id' => $subject->id, 'academic_year' => $year, 'entry_type' => 'academic', 'day' => $day, 'starts_at' => $format($start), 'ends_at' => $format($start + $duration)];
                                if ($candidates[$subject->id]->isEmpty()) break 2;
                                if (microtime(true) >= $deadline) {
                                    $reason = 'Planning time limit reached. Save the filled lessons, then run auto-fill again for the remaining hours.';
                                    break 2;
                                }
                                $busy = $commitments->filter(fn ($s) => $s->day === $day && $this->rules::overlaps($base['starts_at'], $base['ends_at'], $s->starts_at, $s->ends_at));
                                if ($busy->contains('school_class_id', $class->id)) continue;
                                $reservations = $bookings->filter(fn ($b) => $b->day_of_week === $day && $this->rules::overlaps($base['starts_at'], $base['ends_at'], $b->starts_at, $b->ends_at));
                                $assigned = $reservations->filter(fn ($b) => $b->class_group_id === $class->class_group_id && $b->classSession?->session === $class->session);
                                $possibleRooms = $rooms->reject(fn ($r) => $busy->contains('classroom_id', $r->id)
                                    || ($assigned->isNotEmpty() && ! $assigned->contains('classroom_id', $r->id))
                                    || $reservations->contains(fn ($b) => $b->classroom_id === $r->id && ($b->class_group_id !== $class->class_group_id || $b->classSession?->session !== $class->session)));
                                // Validate each room once before trying teachers.
                                $room = $possibleRooms->first(function ($room) use ($base) {
                                    try {
                                        $this->lessons->validate(new Schedule($base + ['classroom_id' => $room->id, 'room' => $room->room_number]), false);
                                        return true;
                                    } catch (ValidationException $e) { return false; }
                                });
                                if (! $room) continue;
                                foreach ($candidates[$subject->id] as $teacher) {
                                    if ($busy->contains('teacher_id', $teacher->id)) {
                                        $reason = 'Qualified teachers are busy during the remaining periods.';
                                        continue;
                                    }
                                    if (! $this->rules->onDuty($teacher, $day, $base['starts_at'], $base['ends_at'])) {
                                        $reason = 'Qualified teachers are unavailable during the remaining periods.';
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
                                    } catch (ValidationException $e) { $reason = $e->getMessage(); }
                                }
                            }
                        }
                        if (! $placed) break;
                    }
                    if ($remaining > 0) $unfilled[] = ['subject' => $subject->name, 'remaining_minutes' => $remaining, 'reason' => $remaining < $duration ? 'Weekly hours must fit a complete lesson block.' : $reason];
                }
                return ['slots' => $rows, 'unfilled' => $unfilled, 'complete' => empty($unfilled)];
            } finally {
                DB::rollBack();
            }
        });
    }
}
