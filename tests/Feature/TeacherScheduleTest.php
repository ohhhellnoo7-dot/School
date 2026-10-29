<?php

namespace Tests\Feature;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use App\Support\TeacherTimetable;
use App\Support\TimetableConflicts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_rotates_classes_and_respects_hours_without_conflicts(): void
    {
        foreach (['Khmer', 'English', 'Chinese'] as $program) {
            $teacher = User::factory()->create(['program' => $program]);
            $teacher->role = 'teacher';
            $teacher->save();
            foreach (range(7, 9) as $grade) {
                SchoolClass::create(['name' => 'Grade '.$grade, 'section' => 'A', 'program' => $program, 'room' => $program.$grade]);
            }
        }
        $this->assertSame(50, TeacherTimetable::generate());
        $this->assertSame(50, TeacherTimetable::generate());
        foreach (Schedule::with('subject.teacher', 'subject.schoolClass')->get() as $slot) {
            $this->assertTrue(TeacherTimetable::withinHours($slot->subject, $slot->starts_at, $slot->ends_at));
            $this->assertFalse(TimetableConflicts::exists($slot->subject, $slot->day, $slot->starts_at, $slot->ends_at, $slot->room, $slot->id));
        }
        foreach (User::where('role', 'teacher')->get() as $teacher) {
            $slots = Schedule::whereHas('subject', fn ($q) => $q->where('teacher_id', $teacher->id))->with('subject')->get();
            $this->assertCount(3, $slots->pluck('subject.name')->unique());
            foreach ($slots->groupBy('day') as $daily) {
                $this->assertCount(match ($teacher->program) { 'Chinese' => 1, 'Khmer' => 4, default => 5 }, $daily);
                $this->assertGreaterThanOrEqual($teacher->program === 'Chinese' ? 1 : 2, $daily->pluck('subject.school_class_id')->unique()->count());
            }
        }
    }

    public function test_schedule_access_is_limited_to_admin_and_the_teacher(): void
    {
        $teacher = User::factory()->create(['program' => 'Chinese']);
        $teacher->role = 'teacher';
        $teacher->save();
        $other = User::factory()->create();
        $this->actingAs($teacher)->get(route('teachers.schedule', $teacher))->assertOk()->assertSee('4:30 PM')->assertDontSee('7:00 AM');
        $this->actingAs($other)->get(route('teachers.schedule', $teacher))->assertForbidden();
    }

    public function test_evening_only_and_break_times_are_rejected(): void
    {
        $teacher = User::factory()->create(['program' => 'Chinese']);
        $class = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A', 'program' => 'Chinese']);
        $subject = Subject::create(['name' => 'Chinese', 'code' => 'ZH', 'school_class_id' => $class->id, 'teacher_id' => $teacher->id]);
        $this->assertFalse(TeacherTimetable::withinHours($subject, '07:00', '09:00'));
        $this->assertFalse(TeacherTimetable::withinHours($subject, '16:00', '18:00'));
        $this->assertFalse(TeacherTimetable::withinHours($subject, '17:00', '19:00'));
        $this->assertTrue(TeacherTimetable::withinHours($subject, '16:30', '18:30'));
    }

    public function test_khmer_evening_lessons_are_removed_and_cannot_be_scheduled(): void
    {
        $khmer = User::factory()->create(['program' => 'Khmer']);
        $khmer->role = 'teacher';
        $khmer->save();
        $english = User::factory()->create(['program' => 'English']);
        $english->role = 'teacher';
        $english->save();
        $class = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A', 'program' => 'Khmer']);
        $subject = Subject::create(['name' => 'Khmer', 'code' => 'KH', 'school_class_id' => $class->id, 'teacher_id' => $khmer->id]);
        $other = Subject::create(['name' => 'English', 'code' => 'EN', 'school_class_id' => $class->id, 'teacher_id' => $english->id]);
        $morning = Schedule::create(['subject_id' => $subject->id, 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '09:00', 'room' => 'A']);
        $evening = Schedule::create(['subject_id' => $subject->id, 'day' => 'Monday', 'starts_at' => '16:30', 'ends_at' => '18:30', 'room' => 'A']);
        $untouched = Schedule::create(['subject_id' => $other->id, 'day' => 'Tuesday', 'starts_at' => '16:30', 'ends_at' => '18:30', 'room' => 'A']);
        $migration = require database_path('migrations/2026_10_27_000006_remove_khmer_evening_schedules.php');
        $migration->up();
        $this->assertDatabaseMissing('schedules', ['id' => $evening->id]);
        $this->assertDatabaseHas('schedules', ['id' => $morning->id]);
        $this->assertDatabaseHas('schedules', ['id' => $untouched->id]);
        $this->assertTrue(TeacherTimetable::withinHours($subject, '07:00', '09:00'));
        $this->assertTrue(TeacherTimetable::withinHours($subject, '13:00', '15:00'));
        $this->assertFalse(TeacherTimetable::withinHours($subject, '16:30', '18:30'));
        $this->actingAs($khmer)->get(route('teachers.schedule', $khmer))->assertOk()
            ->assertSee('schedule-morning')->assertSee('schedule-afternoon')->assertDontSee('schedule-evening');
    }
}
