<?php

namespace App\Services;

use App\Models\{ClassGroup, ClassroomBooking, Schedule, Subject};
use Illuminate\Validation\ValidationException;

class KhmerSubjectSwapService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['subject_swap' => $message]);
    }

    private function slots(Subject $subject): array
    {
        return $subject->schedules->map(fn ($lesson) => implode('|', [$lesson->academic_year, $lesson->day, substr($lesson->starts_at, 0, 5), substr($lesson->ends_at, 0, 5)]))->sort()->values()->all();
    }

    public function matches(Subject $source, Subject $target): bool
    {
        return $source->schoolClass?->program === 'Khmer'
            && $target->schoolClass?->program === 'Khmer'
            && $source->schoolClass->class_group_id !== $target->schoolClass->class_group_id
            && $source->name === $target->name
            && $source->teacher_id && $target->teacher_id
            && $source->teacher_id !== $target->teacher_id
            && $source->schedules->isNotEmpty()
            && $this->slots($source) === $this->slots($target)
            && $source->schedules->every(fn ($s) => $s->teacher_id === $source->teacher_id)
            && $target->schedules->every(fn ($s) => $s->teacher_id === $target->teacher_id)
            && collect([$source->teacher, $target->teacher])->every(fn ($t) => $t && $t->role === 'teacher' && $t->program === 'Khmer' && (!$t->status || $t->status === 'active'));
    }

    public function formData(ClassGroup $group): array
    {
        $subjects = Subject::with(['schoolClass.classGroup', 'teacher', 'schedules.classroom'])
            ->whereHas('schoolClass', fn ($q) => $q->where('program', 'Khmer')->whereNotNull('class_group_id'))->orderBy('name')->get();
        return $subjects->filter(fn ($s) => $s->schoolClass->class_group_id === $group->id)->map(fn ($source) => [
            'id' => $source->id, 'teacher_id' => $source->teacher_id,
            'label' => $source->name.' - '.$source->schoolClass->session,
            'teacher' => $source->teacher?->name ?? 'Unassigned',
            'times' => $source->schedules->map(fn ($s) => $s->day.' '.substr($s->starts_at, 0, 5).'-'.substr($s->ends_at, 0, 5).' ('.$s->academic_year.')')->join(', '),
            'candidates' => $subjects->filter(fn ($target) => $this->matches($source, $target))->map(fn ($target) => [
                'id' => $target->id, 'teacher_id' => $target->teacher_id,
                'label' => $target->schoolClass->classGroup->display_name.' - '.$target->teacher->name.' - '.$target->schoolClass->session.' - Room '.$target->schedules->pluck('classroom.room_number')->filter()->unique()->join(', '),
            ])->values()->all(),
        ])->values()->all();
    }

    public function swap(ClassGroup $group, int $sourceId, int $targetId, int $sourceTeacher, int $targetTeacher): void
    {
        app(TeacherManagementService::class)->locked(function () use ($group, $sourceId, $targetId, $sourceTeacher, $targetTeacher) {
            $subjects = Subject::with(['schoolClass', 'teacher', 'schedules'])->whereIn('id', [$sourceId, $targetId])->get();
            $source = $subjects->firstWhere('id', $sourceId);
            $target = $subjects->firstWhere('id', $targetId);
            if ($group->fresh()->program !== 'Khmer' || !$source || !$target || $source->schoolClass->class_group_id !== $group->id
                || (int) $source->teacher_id !== $sourceTeacher || (int) $target->teacher_id !== $targetTeacher || !$this->matches($source, $target)) {
                $this->fail('Assignments changed or lesson times do not match. Reload and choose the same subject at the same times in another Khmer class.');
            }
            foreach ([[$source, $targetTeacher], [$target, $sourceTeacher]] as [$subject, $teacherId]) {
                Subject::whereKey($subject->id)->update(['teacher_id' => $teacherId]);
                Schedule::where('subject_id', $subject->id)->update(['teacher_id' => $teacherId]);
            }
            try {
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
