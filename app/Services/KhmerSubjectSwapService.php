<?php

namespace App\Services;

use App\Models\{ClassGroup, ClassroomBooking, Schedule, Subject};
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class KhmerSubjectSwapService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['subject_swap' => $message]);
    }

    public function matches(Subject $source, Subject $target): bool
    {
        return $source->schoolClass?->program === 'Khmer'
            && $target->schoolClass?->program === 'Khmer'
            && $source->schoolClass->session === $target->schoolClass->session
            && $source->schoolClass->class_group_id !== $target->schoolClass->class_group_id
            && $source->name === $target->name
            && $source->teacher_id && $target->teacher_id
            && $source->teacher_id !== $target->teacher_id
            && $source->schedules->every(fn ($s) => $s->teacher_id === $source->teacher_id)
            && $target->schedules->every(fn ($s) => $s->teacher_id === $target->teacher_id)
            && collect([$source->teacher, $target->teacher])->every(fn ($t) => $t && $t->role === 'teacher' && $t->program === 'Khmer' && (!$t->status || $t->status === 'active'));
    }

    public function formData(ClassGroup $group): array
    {
        $subjects = Subject::with(['schoolClass.classGroup', 'teacher', 'schedules.classroom'])
            ->whereHas('schoolClass', fn ($q) => $q->where('program', 'Khmer')->whereNotNull('class_group_id'))->orderBy('name')->orderBy('id')->get()
            // Legacy imports can leave empty copies alongside the actual assignment.
            // Prefer the record carrying lessons, then an assigned teacher.
            ->sortByDesc(fn ($subject) => ($subject->schedules->isNotEmpty() ? 2 : 0) + ($subject->teacher_id ? 1 : 0))
            ->unique(fn ($subject) => json_encode([$subject->schoolClass->class_group_id, $subject->schoolClass->session, $subject->name]))
            ->sortBy('name')->values();
        $teacherIds = $subjects->pluck('teacher_id')->filter()->unique();
        $lessons = Schedule::whereIn('teacher_id', $teacherIds)->get()->groupBy('teacher_id');
        $bookings = ClassroomBooking::whereIn('teacher_id', $teacherIds)->get()->groupBy('teacher_id');

        return $subjects->filter(fn ($s) => $s->schoolClass->class_group_id === $group->id)->map(fn ($source) => [
            'id' => $source->id, 'teacher_id' => $source->teacher_id, 'session' => $source->schoolClass->session,
            'name' => $source->name,
            'label' => $source->name.' - '.$source->schoolClass->session,
            'teacher' => $source->teacher?->name ?? 'Unassigned',
            'times' => $source->schedules->map(fn ($s) => $s->day.' '.substr($s->starts_at, 0, 5).'-'.substr($s->ends_at, 0, 5).' ('.$s->academic_year.')')->join(', '),
            'candidates' => $subjects->filter(fn ($target) => $this->matches($source, $target))->sortBy('teacher.name')->map(fn ($target) => [
                'id' => $target->id, 'teacher_id' => $target->teacher_id,
                'label' => $target->teacher->name.' - '.$target->name.' - '.$target->schoolClass->classGroup->display_name.' - '.$target->schoolClass->session,
                'availability' => $this->availabilityForSwap($source, $target, $lessons, $bookings),
            ])->values()->all(),
        ])->values()->all();
    }

    private function availabilityForSwap(Subject $source, Subject $target, Collection $lessons, Collection $bookings): string
    {
        $rules = app(TeacherManagementService::class);
        $status = 'No lesson times';

        foreach ([[$source, $target], [$target, $source]] as [$destination, $incoming]) {
            if ($destination->schedules->isEmpty()) continue;

            $teacher = $incoming->teacher;
            $teacherLessons = $lessons->get($teacher->id, collect())->where('subject_id', '!=', $incoming->id);
            $teacherBookings = $bookings->get($teacher->id, collect());
            $breaks = $rules->breaks($teacher);

            foreach ($destination->schedules as $slot) {
                $overlaps = fn ($start, $end) => $rules::overlaps($slot->starts_at, $slot->ends_at, $start, $end);
                if ($teacherLessons->contains(fn ($lesson) => $lesson->day === $slot->day
                    && (!$slot->academic_year || !$lesson->academic_year || $lesson->academic_year === $slot->academic_year)
                    && $overlaps($lesson->starts_at, $lesson->ends_at))
                    || $teacherBookings->contains(fn ($booking) => $booking->day_of_week === $slot->day
                        && $booking->class_group_id !== $destination->schoolClass->class_group_id
                        && $overlaps($booking->starts_at, $booking->ends_at))) {
                    return 'Busy';
                }

                if (!$rules->onDuty($teacher, $slot->day, $slot->starts_at, $slot->ends_at)
                    || $breaks->contains(fn ($break) => $break->weekday === $slot->day && $overlaps($break->start_time, $break->end_time))) {
                    $status = 'Unavailable';
                } elseif ($status === 'No lesson times') {
                    $status = 'Free';
                }
            }
        }

        return $status;
    }

    public function swap(ClassGroup $group, int $sourceId, int $targetId, int $sourceTeacher, int $targetTeacher): void
    {
        app(TeacherManagementService::class)->locked(function () use ($group, $sourceId, $targetId, $sourceTeacher, $targetTeacher) {
            $subjects = Subject::with(['schoolClass', 'teacher', 'schedules'])->whereIn('id', [$sourceId, $targetId])->get();
            $source = $subjects->firstWhere('id', $sourceId);
            $target = $subjects->firstWhere('id', $targetId);
            if ($group->fresh()->program !== 'Khmer' || !$source || !$target || $source->schoolClass->class_group_id !== $group->id
                || (int) $source->teacher_id !== $sourceTeacher || (int) $target->teacher_id !== $targetTeacher || !$this->matches($source, $target)) {
                $this->fail('Assignments changed. Reload and choose another active teacher teaching the same subject in another Khmer class in the selected session.');
            }
            foreach ([[$source, $targetTeacher], [$target, $sourceTeacher]] as [$subject, $teacherId]) {
                Subject::whereKey($subject->id)->update(['teacher_id' => $teacherId]);
                Schedule::where('subject_id', $subject->id)->update(['teacher_id' => $teacherId]);
            }
            try {
                foreach ([$source, $target] as $subject) {
                    if ($subject->schedules->isEmpty()) {
                        app(TeacherManagementService::class)->validateSubject($subject->fresh());
                    }
                }
                foreach (Schedule::whereIn('subject_id', [$sourceId, $targetId])->get() as $lesson) {
                    app(LessonManagementService::class)->validate($lesson);
                    if (ClassroomBooking::where('teacher_id', $lesson->teacher_id)->where('day_of_week', $lesson->day)
                        ->where('class_group_id', '!=', $lesson->schoolClass->class_group_id)
                        ->where('starts_at', '<', $lesson->ends_at)->where('ends_at', '>', $lesson->starts_at)->exists()) {
                        $this->fail('The incoming teacher has a reservation for another class during this lesson.');
                    }
                }
            } catch (ValidationException $error) {
                $this->fail(collect($error->errors())->flatten()->join(' '));
            }
        });
    }
}
