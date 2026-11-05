<?php

namespace App\Services;

use App\Models\{ClassGroup, ClassroomBooking, Schedule, SchoolClass, Subject, User};
use Illuminate\Validation\ValidationException;

class JapanClassTeacherService
{
    public function assign(ClassGroup $group, ?int $teacherId): void
    {
        app(TeacherManagementService::class)->locked(function () use ($group, $teacherId) {
            $classIds = $group->schoolClasses()->pluck('id');
            $lessons = Schedule::whereIn('school_class_id', $classIds)->where('entry_type', 'academic');
            $bookings = $group->bookings();
            if (!$teacherId && ($lessons->exists() || $bookings->exists())) {
                $this->fail('Choose a class teacher while this class has lessons or reservations.');
            }
            if ($teacherId) {
                if (!User::whereKey($teacherId)->where('role', 'teacher')->where('program', 'Japan')->where('status', 'active')->exists()) {
                    $this->fail('Choose an active Japanese teacher.');
                }
                if (ClassGroup::where('program', 'Japan')->whereKeyNot($group->id)->where('teacher_id', $teacherId)->exists() ||
                    SchoolClass::where('program', 'Japan')->whereNotIn('id', $classIds)->where('teacher_id', $teacherId)->exists()) {
                    $this->fail('This teacher is assigned to another Japanese class. Use Swap class teachers to exchange their assignments.');
                }
            }
            $previousTeachers = $group->schoolClasses()->pluck('teacher_id')->push($group->teacher_id)->filter()->unique();
            SchoolClass::whereIn('id', $classIds)->update(['teacher_id' => $teacherId]);
            Subject::whereIn('school_class_id', $classIds)->whereIn('teacher_id', $previousTeachers)->update(['teacher_id' => $teacherId]);
            $lessons->update(['teacher_id' => $teacherId]);
            $bookings->update(['teacher_id' => $teacherId]);
            $group->update(['teacher_id' => $teacherId]);
            try {
                foreach ($lessons->get() as $lesson) {
                    app(LessonManagementService::class)->validate($lesson);
                }
                foreach ($bookings->get() as $booking) {
                    app(ClassroomBookingService::class)->update($booking, $booking->only([
                        'class_group_id', 'class_session_id', 'classroom_id', 'teacher_id', 'day_of_week', 'starts_at', 'ends_at',
                    ]));
                }
            } catch (ValidationException $error) {
                $this->fail(collect($error->errors())->flatten()->join(' '));
            }
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['teacher_id' => $message]);
    }
}
