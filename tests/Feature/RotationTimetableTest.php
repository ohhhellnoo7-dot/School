<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, ClassSession, Classroom, Schedule, SchoolClass, Subject, User};
use App\Services\{ClassroomAvailabilityService, ClassroomBookingService, RotationTimetableGenerator, TeacherManagementService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RotationTimetableTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $group = ClassGroup::create(['name' => 'Grade 10', 'grade' => 10, 'section' => 'A', 'program' => 'Khmer', 'status' => 'active']);
        $session = ClassSession::create(['class_group_id' => $group->id, 'session' => 'Morning', 'starts_at' => '07:00', 'ends_at' => '11:00']);
        $class = SchoolClass::create(['class_group_id' => $group->id, 'name' => 'Grade 10', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning', 'room' => '10A']);
        $room = Classroom::create(['room_number' => '10A', 'name' => 'Room 10A', 'capacity' => 40, 'status' => 'active']);
        foreach (TeacherManagementService::DAYS as $day) app(ClassroomBookingService::class)->create(['class_group_id' => $group->id, 'class_session_id' => $session->id, 'classroom_id' => $room->id, 'teacher_id' => null, 'day_of_week' => $day, 'starts_at' => '07:00', 'ends_at' => '11:00']);
        $math = Subject::create(['name' => 'Mathematics', 'code' => 'M10A', 'school_class_id' => $class->id, 'required_weekly_minutes' => 240, 'lesson_minutes' => 120]);
        $history = Subject::create(['name' => 'History', 'code' => 'H10A', 'school_class_id' => $class->id, 'required_weekly_minutes' => 180, 'lesson_minutes' => 60]);
        $teachers = [];
        foreach ([$math, $history] as $subject) {
            $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer', 'status' => 'active']);
            $periods = [];
            foreach (TeacherManagementService::DAYS as $day) $periods[] = ['weekday' => $day, 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false];
            app(TeacherManagementService::class)->saveProfile($teacher, [$subject->name], 1200, $periods);
            $subject->update(['teacher_id' => $teacher->id]);
            $teachers[] = $teacher;
        }
        $admin = User::factory()->create(['role' => 'administrator']);
        return [$class, $room, $math, $history, $teachers, $admin];
    }

    public function test_approved_curriculum_produces_mixed_durations_free_time_and_shared_lessons(): void
    {
        [$class, $room, $math, $history, $teachers, $admin] = $this->fixture();
        $generator = app(RotationTimetableGenerator::class);
        $plan = $generator->preview($class);
        $this->assertSame(0, Schedule::count());
        $this->assertSame(0, DB::table('teacher_breaks')->count());
        $this->assertCount(2, array_filter($plan['rows'], fn ($r) => $r['subject_id'] === $math->id));
        $this->assertCount(3, array_filter($plan['rows'], fn ($r) => $r['subject_id'] === $history->id));
        $generator->confirm($plan);
        $this->assertSame(8, Schedule::count()); // Seven teaching hours, three relaxation hours, ten unscheduled hours.
        foreach ($teachers as $teacher) {
            $rules = app(TeacherManagementService::class);
            $this->assertCount(3, $rules->breaks($teacher));
            foreach ($rules->lessons($teacher) as $lesson) {
                $rules->validateLesson($lesson->subject, $lesson);
                $this->assertSame($room->id, $lesson->classroom_id);
                $this->assertSame($class->id, $lesson->school_class_id);
            }
        }
        $page = $this->actingAs($admin)->get(route('classes.timetable', $class))->assertOk()->assertSee('rowspan="2"', false)->assertSee('FREE PERIOD')->assertSee('Scheduled relaxation')->assertSee('Scheduling Settings');
        foreach (['Light', 'Night', 'Relax', 'Cute'] as $theme) $page->assertSee($theme);
        $this->get(route('teachers.schedule', $teachers[0]))->assertOk()->assertSee('Mathematics')->assertSee('10A');
        $this->actingAs($teachers[0])->get(route('classrooms.show', $room))->assertOk()->assertSee('Weekly lesson rotation');
    }

    public function test_generation_and_regeneration_are_previews_until_confirmed_and_stale_tokens_fail(): void
    {
        [$class, , , , , $admin] = $this->fixture();
        $this->actingAs($admin)->post(route('classes.timetable.generate', $class))->assertOk()->assertSee('Schedule Preview');
        $token = session('rotation_preview.token');
        $this->assertSame(0, Schedule::count());
        $this->post(route('classes.timetable.confirm', $class), ['token' => $token])->assertSessionHasErrors('confirm');
        $this->post(route('classes.timetable.confirm', $class), ['token' => $token, 'confirm' => 1])->assertSessionHasNoErrors()->assertRedirect(route('classes.timetable', $class));
        $before = Schedule::pluck('id')->all();
        $this->post(route('classes.timetable.regenerate', $class))->assertOk()->assertSee('Existing schedule')->assertSee('Proposed schedule');
        $token = session('rotation_preview.token');
        $this->assertSame($before, Schedule::pluck('id')->all());
        $this->post(route('classes.timetable.confirm', $class), ['token' => $token, 'confirm' => 1])->assertSessionHasErrors();
        $this->post(route('classes.timetable.confirm', $class), ['token' => $token, 'confirm' => 1, 'replace' => 1])->assertRedirect(route('classes.timetable', $class))->assertSessionHasNoErrors();
        $this->assertSame(count($before), Schedule::withoutGlobalScope('active')->whereNotNull('cancelled_at')->count());
        $this->post(route('classes.timetable.confirm', $class), ['token' => $token, 'confirm' => 1, 'replace' => 1])->assertSessionHasErrors();
    }

    public function test_stale_room_or_availability_rejects_the_whole_plan(): void
    {
        [$class, $room] = $this->fixture();
        $generator = app(RotationTimetableGenerator::class);
        $plan = $generator->preview($class);
        $room->update(['status' => 'out_of_service']);
        try { $generator->confirm($plan); $this->fail('Stale plan accepted'); }
        catch (ValidationException $e) { $this->assertStringContainsString('changed after preview', $e->getMessage()); }
        $this->assertSame(0, Schedule::count());
        $this->assertSame(0, DB::table('teacher_breaks')->count());
    }

    public function test_unknown_and_excess_curriculum_are_rejected_without_writes(): void
    {
        [$class, , $math] = $this->fixture();
        foreach ([null, 1200] as $minutes) {
            $math->update(['required_weekly_minutes' => $minutes]);
            try { app(RotationTimetableGenerator::class)->preview($class); $this->fail('Invalid curriculum accepted'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
            $this->assertSame(0, Schedule::count());
        }
    }

    public function test_room_activity_changes_at_lesson_boundary_and_stays_occupied_in_free_time(): void
    {
        [$class, $room, $math, $history, $teachers, $admin] = $this->fixture();
        $first = Schedule::create(['subject_id' => $math->id, 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '09:00', 'room' => '10A', 'classroom_id' => $room->id]);
        $second = Schedule::create(['subject_id' => $history->id, 'day' => 'Monday', 'starts_at' => '09:00', 'ends_at' => '10:00', 'room' => '10A', 'classroom_id' => $room->id]);
        $service = app(ClassroomAvailabilityService::class);
        $at = fn ($time) => $service->forDateAndTime('2026-11-02', $time)->first();
        $this->assertSame($first->id, $at('08:30')['currentLesson']->id);
        $this->assertSame($second->id, $at('08:30')['nextLesson']->id);
        $this->assertSame($second->id, $at('09:00')['currentLesson']->id);
        $this->assertSame($teachers[1]->id, $at('09:00')['currentLesson']->subject->teacher_id);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 09:00', config('app.timezone')));
        $this->assertSame('Teaching', app(TeacherManagementService::class)->availability($teachers[1])['status']);
        $this->actingAs($teachers[1])->get(route('teachers.schedule.status', $teachers[1]))->assertOk()->assertJsonPath('status', 'Teaching');
        $this->get(route('teachers.schedule.status', $teachers[0]))->assertForbidden();
        CarbonImmutable::setTestNow();
        $this->assertSame('Occupied', $at('10:00')['occupancy']);
        $this->assertSame('Free Period', $at('10:00')['activity']);
        $this->assertNull($at('10:00')['currentLesson']);
        $this->assertSame('Available', $at('11:00')['occupancy']);
        $this->assertSame('No Class', $service->forDateAndTime('2026-11-07', '08:30')->first()['activity']);
        $this->actingAs($admin)->get(route('classrooms.lesson-status', ['classroom' => $room, 'date' => '2026-11-02', 'time' => '09:00']))->assertOk()->assertJsonPath('label', 'Mon, Nov 2, 2026 09:00');
        $this->get(route('classrooms.show', $room))->assertOk()->assertSee('Weekly lesson rotation')->assertSee('data-room-live', false);
    }

    public function test_teacher_can_rotate_rooms_and_overlap_is_rejected(): void
    {
        [$class, $room, $math, , $teachers] = $this->fixture();
        $other = SchoolClass::create(['name' => 'Grade 10', 'section' => 'B', 'program' => 'Khmer', 'session' => 'Morning', 'room' => '10B']);
        $otherRoom = Classroom::create(['room_number' => '10B', 'name' => 'Room 10B', 'capacity' => 40, 'status' => 'active']);
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'M10B', 'school_class_id' => $other->id, 'teacher_id' => $teachers[0]->id]);
        Schedule::create(['subject_id' => $math->id, 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '09:00', 'room' => '10A', 'classroom_id' => $room->id]);
        Schedule::create(['subject_id' => $subject->id, 'day' => 'Monday', 'starts_at' => '09:00', 'ends_at' => '11:00', 'room' => '10B', 'classroom_id' => $otherRoom->id]);
        $this->assertCount(2, app(TeacherManagementService::class)->lessons($teachers[0]));
        $this->expectException(ValidationException::class);
        Schedule::create(['subject_id' => $subject->id, 'day' => 'Monday', 'starts_at' => '08:00', 'ends_at' => '09:00', 'room' => '10B', 'classroom_id' => $otherRoom->id]);
    }

    public function test_cancelled_lessons_release_teacher_but_not_room_reservation(): void
    {
        [$class, $room, $math, , $teachers, $admin] = $this->fixture();
        $lesson = Schedule::create(['subject_id' => $math->id, 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '09:00', 'room' => '10A']);
        $this->actingAs($admin)->post(route('lessons.cancel', $lesson), ['confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, Schedule::count());
        $this->assertSame(1, Schedule::withoutGlobalScope('active')->whereNotNull('cancelled_at')->count());
        $this->assertEquals(0, app(TeacherManagementService::class)->workload($teachers[0])['weekly']);
        $snapshot = app(ClassroomAvailabilityService::class)->forDateAndTime('2026-11-02', '08:00')->first();
        $this->assertSame('Occupied', $snapshot['occupancy']);
        $this->assertSame('Free Period', $snapshot['activity']);
        $this->get(route('classes.timetable', $class))->assertOk()->assertSee('Cancelled lesson history');
    }

    public function test_curriculum_settings_require_admin_confirmation_and_preserve_lessons(): void
    {
        [$class, , $math, $history, $teachers, $admin] = $this->fixture();
        $data = ['subjects' => [['id' => $math->id, 'weekly_hours' => 4, 'lesson_minutes' => 120], ['id' => $history->id, 'weekly_hours' => 2, 'lesson_minutes' => 60]], 'relaxation_hours' => 3];
        $this->actingAs($teachers[0])->post(route('classes.timetable.settings', $class), $data)->assertForbidden();
        $this->actingAs($admin)->post(route('classes.timetable.settings', $class), $data)->assertSessionHasErrors('confirm');
        $this->post(route('classes.timetable.settings', $class), $data + ['confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame(120, (int) $history->fresh()->required_weekly_minutes);
        $this->assertSame(0, Schedule::count());
    }

    public function test_planner_can_select_a_qualified_teacher_for_a_vacant_subject(): void
    {
        [$class, , $math, , $teachers] = $this->fixture();
        $math->update(['teacher_id' => null]);
        $generator = app(RotationTimetableGenerator::class);
        $plan = $generator->preview($class);
        $this->assertNull($math->fresh()->teacher_id);
        $generator->confirm($plan);
        $this->assertSame($teachers[0]->id, $math->fresh()->teacher_id);
        $this->assertSame(2, Schedule::where('subject_id', $math->id)->where('teacher_id', $teachers[0]->id)->count());
    }

    public function test_planner_rejects_unqualified_teachers_and_weekly_overload(): void
    {
        [$class, , $math, , $teachers] = $this->fixture();
        DB::table('teacher_profiles')->where('teacher_id', $teachers[0]->id)->update(['weekly_minutes' => 60]);
        try { app(RotationTimetableGenerator::class)->preview($class); $this->fail('Overload accepted'); }
        catch (ValidationException $e) { $this->assertStringContainsString('weekly teaching limit', $e->getMessage()); }
        DB::table('teacher_profiles')->where('teacher_id', $teachers[0]->id)->update(['weekly_minutes' => 1200]);
        DB::table('teacher_qualifications')->where('teacher_id', $teachers[0]->id)->delete();
        $this->expectException(ValidationException::class);
        app(RotationTimetableGenerator::class)->preview($class);
    }

    public function test_old_preview_token_expires_without_cancelling_existing_lessons(): void
    {
        [$class, , , , $teachers, $admin] = $this->fixture();
        $this->actingAs($teachers[0])->post(route('classes.timetable.generate', $class))->assertForbidden();
        $this->actingAs($admin)->post(route('classes.timetable.generate', $class))->assertOk();
        $token = session('rotation_preview.token');
        $preview = session('rotation_preview');
        $preview['created'] = time() - 1801;
        $this->withSession(['rotation_preview' => $preview])->post(route('classes.timetable.confirm', $class), ['token' => $token, 'confirm' => 1])->assertSessionHasErrors();
        $this->assertSame(0, Schedule::withoutGlobalScope('active')->count());
    }

    public function test_teacher_only_generator_respects_two_hour_lesson_configuration(): void
    {
        [$class, $room, $math, , $teachers] = $this->fixture();
        $plan = app(\App\Services\TeacherScheduleGenerator::class)->preview($teachers[0]);
        $this->assertCount(2, $plan['lessons']);
        foreach ($plan['lessons'] as $row) {
            $this->assertSame(120, TeacherManagementService::duration($row['starts_at'], $row['ends_at']));
            $this->assertSame($room->id, $row['classroom_id']);
        }
        app(\App\Services\TeacherScheduleGenerator::class)->confirm($plan, false);
        $this->assertEquals(4, app(TeacherManagementService::class)->workload($teachers[0])['weekly']);
        $this->actingAs($teachers[0])->get(route('teachers.schedule', $teachers[0]))->assertOk()->assertSee('rowspan="2"', false);
    }
}
