<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, Classroom, ClassroomBooking, ClassSession, Schedule, SchoolClass, Subject, User};
use App\Services\{ChineseProgramSetup, TeacherManagementService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class ChineseProgramSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_chinese_teachers_and_grades_have_two_evening_lessons_every_weekday(): void
    {
        Storage::fake('local');
        $teachers = User::factory()->count(10)->create(['role' => 'teacher', 'program' => 'Chinese', 'status' => 'active']);
        $plan = DB::table('teacher_break_plans')->insertGetId(['teacher_id' => $teachers->first()->id]);
        foreach (['Monday', 'Tuesday', 'Wednesday'] as $day) DB::table('teacher_breaks')->insert(['teacher_break_plan_id' => $plan, 'weekday' => $day, 'start_time' => '17:30', 'end_time' => '18:30', 'duration_minutes' => 60]);
        foreach (range(1, 12) as $grade) {
            $group = ClassGroup::create(['name' => 'Grade '.$grade, 'grade' => $grade, 'program' => 'Chinese', 'status' => 'active']);
            $session = ClassSession::create(['class_group_id' => $group->id, 'session' => 'Evening', 'starts_at' => '17:30', 'ends_at' => '18:30']);
            $room = Classroom::create(['room_number' => 'C'.$grade, 'name' => 'Chinese '.$grade, 'capacity' => 40, 'status' => 'active']);
            $owner = SchoolClass::create(['name' => 'Grade '.$grade, 'section' => 'A', 'program' => 'Chinese', 'session' => 'Evening', 'class_group_id' => $group->id]);
            SchoolClass::create(['name' => 'Grade '.$grade, 'section' => 'B', 'program' => 'Chinese', 'session' => 'Evening', 'class_group_id' => $group->id, 'shared_timetable_class_id' => $owner->id]);
            foreach (['Chinese language', 'Chinese reading', 'Chinese writing'] as $slot => $name) Subject::create(['school_class_id' => $owner->id, 'name' => $name, 'code' => 'C-'.$grade.'-'.$slot]);
            foreach (TeacherManagementService::DAYS as $day) ClassroomBooking::create(['class_group_id' => $group->id, 'class_session_id' => $session->id, 'classroom_id' => $room->id, 'day_of_week' => $day, 'starts_at' => '17:30', 'ends_at' => '18:30', 'starts_minute' => 1050, 'ends_minute' => 1110]);
        }
        $report = app(ChineseProgramSetup::class)->apply('2026-2027');
        $this->assertCount(2, $report['added_teachers']);
        $this->assertSame(120, $report['lessons']);
        $this->assertSame(12, User::where('program', 'Chinese')->count());
        $this->assertSame(0, DB::table('teacher_break_plans')->count());
        $rules = app(TeacherManagementService::class);
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin);
        foreach (User::where('program', 'Chinese')->get() as $teacher) {
            $lessons = $rules->lessons($teacher);
            $this->assertCount(10, $lessons);
            $this->assertSame(600, $rules->limit($teacher));
            $this->assertFalse($rules->eligibleForBreaks($teacher));
            foreach (TeacherManagementService::DAYS as $day) {
                $this->assertCount(2, $lessons->where('day', $day));
                $this->assertTrue($rules->onDuty($teacher, $day, '17:30', '19:30'));
                $this->assertFalse($rules->onDuty($teacher, $day, '07:00', '08:00'));
            }
            $this->get(route('timetables.index', ['view' => 'teachers', 'program' => 'Chinese', 'teacher' => $teacher->id, 'academic_year' => '2026-2027']))
                ->assertOk()->assertSee('Evening schedule')->assertDontSee('Morning schedule')->assertDontSee('Afternoon schedule')->assertDontSee('No lesson scheduled')->assertDontSee('Designated teacher free period');
        }
        foreach (SchoolClass::all() as $class) {
            $this->assertCount(10, Schedule::where('school_class_id', $class->timetableOwnerId())->get());
            $this->assertSame(0, $class->weekly_relaxation_minutes);
            $this->assertSame(600, (int) $class->subjects->sum('required_weekly_minutes'));
            $this->get(route('timetables.index', ['view' => 'students', 'class' => $class->id, 'academic_year' => '2026-2027']))->assertOk()->assertDontSee('No lesson scheduled');
        }
        // Re-running replaces the same year safely without duplicating staff or active lessons.
        $again = app(ChineseProgramSetup::class)->apply('2026-2027');
        $this->assertSame([], $again['added_teachers']);
        $this->assertSame(120, Schedule::count());
        $this->assertSame(120, Schedule::withoutGlobalScopes()->whereNotNull('cancelled_at')->count());
    }
}
