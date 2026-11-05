<?php

namespace App\Services;

use App\Models\{ClassGroup, ClassroomBooking, Schedule, SchoolClass, Subject, User};
use Illuminate\Validation\ValidationException;

class ClassTeacherSwapService
{
    public function swap(ClassGroup $source, ClassGroup $target, int $sourceTeacher, int $targetTeacher): void
    {
        app(TeacherManagementService::class)->locked(function () use ($source, $target, $sourceTeacher, $targetTeacher) {
            $source = $source->fresh();
            $target = $target->fresh();
            if ($source->is($target) || $source->program !== $target->program) {
                $this->fail('Choose another class in the same program.');
            }
            if (!$sourceTeacher || !$targetTeacher || $sourceTeacher === $targetTeacher ||
                (int) $source->teacher_id !== $sourceTeacher || (int) $target->teacher_id !== $targetTeacher) {
                $this->fail('Teacher assignments changed or are missing. Reload the page before swapping.');
            }
            foreach ([$sourceTeacher, $targetTeacher] as $teacherId) {
                if (!User::whereKey($teacherId)->where('role', 'teacher')->where('program', $source->program)
                    ->where(fn ($q) => $q->whereNull('status')->orWhere('status', 'active'))->exists()) {
                    $this->fail('Both class teachers must be active and belong to the same program.');
                }
            }

            $lessonIds = collect();
            $bookingIds = collect();
            foreach ([[$source, $sourceTeacher, $targetTeacher], [$target, $targetTeacher, $sourceTeacher]] as [$group, $old, $new]) {
                $classIds = $group->schoolClasses()->pluck('id');
                $lessons = Schedule::whereIn('school_class_id', $classIds)->where('teacher_id', $old);
                $lessonIds = $lessonIds->merge($lessons->pluck('id'));
                $lessons->update(['teacher_id' => $new]);
                Subject::whereIn('school_class_id', $classIds)->where('teacher_id', $old)->update(['teacher_id' => $new]);
                SchoolClass::whereIn('id', $classIds)->where('teacher_id', $old)->update(['teacher_id' => $new]);
                $bookings = $group->bookings()->where('teacher_id', $old);
                $bookingIds = $bookingIds->merge($bookings->pluck('id'));
                $bookings->update(['teacher_id' => $new]);
                $group->update(['teacher_id' => $new]);
            }

            // Validate the complete swap, after both sides have moved, in one transaction.
            try {
                foreach (Schedule::whereIn('id', $lessonIds)->get() as $lesson) {
                    app(LessonManagementService::class)->validate($lesson);
                    $groupId = SchoolClass::findOrFail($lesson->school_class_id)->class_group_id;
                    if (ClassroomBooking::where('teacher_id', $lesson->teacher_id)->where('class_group_id', '!=', $groupId)
                        ->where('day_of_week', $lesson->day)->where('starts_at', '<', $lesson->ends_at)->where('ends_at', '>', $lesson->starts_at)->exists()) {
                        $this->fail('The incoming teacher has an overlapping reservation for another class.');
                    }
                }
                foreach (ClassroomBooking::whereIn('id', $bookingIds)->get() as $booking) {
                    if (Schedule::where('teacher_id', $booking->teacher_id)->where('day', $booking->day_of_week)
                        ->where('starts_at', '<', $booking->ends_at)->where('ends_at', '>', $booking->starts_at)
                        ->whereHas('schoolClass', fn ($q) => $q->where('class_group_id', '!=', $booking->class_group_id)->orWhereNull('class_group_id'))->exists()) {
                        $this->fail('The incoming teacher has an overlapping lesson for another class.');
                    }
                    app(ClassroomBookingService::class)->update($booking, $booking->only([
                        'class_group_id', 'class_session_id', 'classroom_id', 'teacher_id',
                        'day_of_week', 'starts_at', 'ends_at',
                    ]));
                }
            } catch (ValidationException $error) {
                $this->fail('Teachers could not be swapped: '.collect($error->errors())->flatten()->join(' '));
            }
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['swap_class_group_id' => $message]);
    }
}
