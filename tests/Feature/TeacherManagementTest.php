<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, ClassSession, Schedule, SchoolClass, Subject, User};
use App\Services\TeacherManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $session = 'Morning'): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer', 'status' => 'active']);
        $group = ClassGroup::firstOrCreate(['grade' => 7, 'section' => 'A', 'program' => 'Khmer'], ['name' => 'Grade 7', 'status' => 'active']);
        [$from, $to] = TeacherManagementService::SESSIONS[$session];
        ClassSession::firstOrCreate(['class_group_id' => $group->id, 'session' => $session], ['starts_at' => $from, 'ends_at' => $to]);
        $class = SchoolClass::firstOrCreate(['name' => 'Grade 7', 'section' => 'A', 'program' => 'Khmer', 'session' => $session], ['class_group_id' => $group->id, 'room' => 'R1']);
        $subject = Subject::create(['name' => 'Mathematics', 'code' => uniqid('M'), 'school_class_id' => $class->id]);
        $periods = [];
        foreach (TeacherManagementService::DAYS as $day) $periods[] = ['weekday' => $day, 'start_time' => $from, 'end_time' => $to, 'unavailable' => false];
        $service = app(TeacherManagementService::class);
        $service->saveProfile($teacher, ['Mathematics'], null, $periods);
        return [$teacher, $subject, $service];
    }

    private function lesson(Subject $subject, string $start = '07:00', string $end = '08:00', string $day = 'Monday'): Schedule
    {
        return Schedule::create(['subject_id' => $subject->id, 'day' => $day, 'starts_at' => $start, 'ends_at' => $end, 'room' => 'R1']);
    }

    public function test_combined_schedule_preview_allocates_approved_demand_and_preserves_shared_lessons(): void
    {
        [$teacher, $subject, $service] = $this->fixture();
        $subject->update(['teacher_id' => $teacher->id, 'required_weekly_minutes' => 240]);
        $original = $this->lesson($subject)->fresh()->getAttributes();
        $generator = app(\App\Services\TeacherScheduleGenerator::class);
        $plan = $generator->preview($teacher);
        $this->assertCount(3, $plan['lessons']);
        $this->assertCount(3, $plan['breaks']);
        $this->assertSame(1, Schedule::count());
        $this->assertSame(0, DB::table('teacher_breaks')->count());
        $generator->confirm($plan, false);
        $this->assertSame($original, Schedule::find($original['id'])->getAttributes());
        $this->assertSame(4, Schedule::where('school_class_id', $subject->school_class_id)->count());
        $this->assertEquals(4, $service->workload($teacher)['weekly']);
        foreach ($service->lessons($teacher) as $slot) $service->validateLesson($slot->subject, $slot);
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->get(route('teachers.schedule', $teacher))->assertOk()->assertSee('FREE PERIOD')->assertSee('Print Schedule')->assertSee('R1');
    }

    public function test_combined_schedule_requires_confirmation_and_explicit_regeneration(): void
    {
        [$teacher, $subject, $service] = $this->fixture();
        $subject->update(['teacher_id' => $teacher->id, 'required_weekly_minutes' => 120]);
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->post(route('teacher-management.schedules.preview', $teacher))->assertOk()->assertSee('Proposed changes');
        $token = session('teacher_schedule_preview.token');
        $this->post(route('teacher-management.schedules.confirm'), ['token' => $token])->assertSessionHasErrors('confirm');
        $this->assertSame(0, Schedule::count());
        $this->post(route('teacher-management.schedules.confirm'), ['token' => $token, 'confirm' => 1])->assertSessionHasNoErrors()->assertRedirect(route('teachers.schedule', $teacher));
        $this->assertSame(2, Schedule::count());
        $this->post(route('teacher-management.schedules.confirm'), ['token' => $token, 'confirm' => 1])->assertSessionHasErrors();
        $this->post(route('teacher-management.schedules.preview', $teacher))->assertOk();
        $token = session('teacher_schedule_preview.token');
        $this->post(route('teacher-management.schedules.confirm'), ['token' => $token, 'confirm' => 1])->assertSessionHasErrors();
        $this->post(route('teacher-management.schedules.confirm'), ['token' => $token, 'confirm' => 1, 'replace' => 1])->assertSessionHasNoErrors();
        $this->assertSame(2, Schedule::count());
    }

    public function test_combined_schedule_rejects_stale_preview_without_partial_writes(): void
    {
        [$teacher, $subject] = $this->fixture();
        $subject->update(['teacher_id' => $teacher->id, 'required_weekly_minutes' => 60]);
        $generator = app(\App\Services\TeacherScheduleGenerator::class);
        $plan = $generator->preview($teacher);
        DB::table('teacher_profiles')->where('teacher_id', $teacher->id)->update(['weekly_minutes' => 60]);
        try { $generator->confirm($plan, false); $this->fail('Stale preview accepted'); }
        catch (ValidationException $e) { $this->assertStringContainsString('changed after preview', $e->getMessage()); }
        $this->assertSame(0, Schedule::count());
        $this->assertSame(0, DB::table('teacher_breaks')->count());
    }

    public function test_combined_schedule_leaves_unknown_curriculum_unscheduled_and_checks_capacity(): void
    {
        [$teacher, $subject] = $this->fixture();
        $subject->update(['teacher_id' => $teacher->id]);
        $generator = app(\App\Services\TeacherScheduleGenerator::class);
        $this->assertCount(0, $generator->preview($teacher)['lessons']);
        $subject->update(['required_weekly_minutes' => 1200]);
        $this->expectException(ValidationException::class);
        $generator->preview($teacher); // Twenty duty hours cannot contain twenty lessons and three free periods.
    }

    public function test_combined_schedule_routes_are_administrator_only(): void
    {
        [$teacher] = $this->fixture();
        $this->actingAs($teacher)->post(route('teacher-management.schedules.preview', $teacher))->assertForbidden();
        $this->post(route('teacher-management.schedules.confirm'), ['confirm' => 1, 'token' => 'fake'])->assertForbidden();
    }

    public function test_breaks_preview_three_different_days_one_hour_and_no_writes(): void
    {
        [$teacher, $subject, $service] = $this->fixture(); $subject->update(['teacher_id' => $teacher->id]);
        $this->lesson($subject); $before = $teacher->fresh()->getAttributes(); $original = Schedule::first()->getAttributes();
        $signatures = [];
        foreach (range(1, 20) as $attempt) {
            $plan = $service->proposeBreaks($teacher);
            $this->assertCount(3, $plan); $this->assertCount(3, array_unique(array_column($plan, 'weekday')));
            foreach ($plan as $b) { $this->assertSame(60, TeacherManagementService::duration($b['start_time'], $b['end_time'])); $service->validateBreak($teacher, $b); }
            $signatures[] = json_encode($plan);
        }
        $this->assertGreaterThan(1, count(array_unique($signatures)));
        $this->assertSame(0, DB::table('teacher_breaks')->count());
        $service->saveBreaks($teacher, $plan);
        $this->assertSame(3, DB::table('teacher_breaks')->count());
        $this->assertSame($before, $teacher->fresh()->getAttributes()); $this->assertSame($original, Schedule::first()->getAttributes());
        $this->assertEquals(1, $service->workload($teacher)['weekly']); $this->assertEquals(3, $service->workload($teacher)['breaks']);
    }

    public function test_afternoon_and_both_sessions_never_use_midday_or_off_duty_time(): void
    {
        [$teacher, $subject, $service] = $this->fixture('Afternoon');
        foreach ($service->proposeBreaks($teacher) as $b) { $this->assertGreaterThanOrEqual('13:00', $b['start_time']); $this->assertLessThanOrEqual('17:00', $b['end_time']); }
        $periods = $service->periods($teacher)->map(fn ($p) => collect((array)$p)->only(['weekday', 'start_time', 'end_time', 'unavailable'])->all())->all();
        foreach (TeacherManagementService::DAYS as $day) $periods[] = ['weekday' => $day, 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false];
        $periods[] = ['weekday' => 'Monday', 'start_time' => '13:00', 'end_time' => '17:00', 'unavailable' => true];
        $service->saveProfile($teacher, ['Mathematics'], 1200, $periods);
        foreach ($service->proposeBreaks($teacher) as $b) { $this->assertTrue($b['end_time'] <= '11:00' || $b['start_time'] >= '13:00'); $service->validateBreak($teacher, $b); }
    }

    public function test_missing_duty_hours_or_three_distinct_days_is_reported(): void
    {
        [$teacher, $subject, $service] = $this->fixture();
        DB::table('teacher_working_periods')->where('teacher_id', $teacher->id)->whereNotIn('weekday', ['Monday', 'Tuesday'])->delete();
        $this->expectException(ValidationException::class); $service->proposeBreaks($teacher);
    }

    public function test_teaching_cannot_overlap_breaks_or_another_class(): void
    {
        [$teacher, $subject, $service] = $this->fixture(); $subject->update(['teacher_id' => $teacher->id]);
        $plan = $service->proposeBreaks($teacher); $service->saveBreaks($teacher, $plan);
        $this->expectException(ValidationException::class);
        $this->lesson($subject, $plan[0]['start_time'], $plan[0]['end_time'], $plan[0]['weekday']);
    }

    public function test_one_and_two_subjects_allowed_but_third_is_rejected(): void
    {
        [$teacher, $math, $service] = $this->fixture(); $math->update(['teacher_id' => $teacher->id]);
        $physics = Subject::create(['name' => 'Physics', 'code' => 'P7', 'school_class_id' => $math->school_class_id]);
        $biology = Subject::create(['name' => 'Biology', 'code' => 'B7', 'school_class_id' => $math->school_class_id]);
        $periods = $service->periods($teacher)->map(fn ($p) => collect((array)$p)->only(['weekday', 'start_time', 'end_time', 'unavailable'])->all())->all();
        $service->saveProfile($teacher, ['Mathematics', 'Physics'], null, $periods); $physics->update(['teacher_id' => $teacher->id]);
        $this->assertCount(2, $service->workload($teacher)['subjects']);
        $this->expectException(ValidationException::class); $biology->update(['teacher_id' => $teacher->id]);
    }

    public function test_workload_uses_actual_minutes_and_rejects_overload(): void
    {
        [$teacher, $subject, $service] = $this->fixture(); $subject->update(['teacher_id' => $teacher->id]);
        DB::table('teacher_profiles')->where('teacher_id', $teacher->id)->update(['weekly_minutes' => 90]);
        $this->lesson($subject, '07:00', '08:30');
        $this->assertSame('FULL', $service->workload($teacher)['status']); $this->assertEquals(1.5, $service->workload($teacher)['weekly']);
        $this->expectException(ValidationException::class); $this->lesson($subject, '08:30', '09:30');
    }

    public function test_break_replacement_and_duplicate_day_require_validation(): void
    {
        [$teacher, $subject, $service] = $this->fixture(); $plan = $service->proposeBreaks($teacher); $service->saveBreaks($teacher, $plan);
        $before = DB::table('teacher_breaks')->get()->toJson();
        try { $service->saveBreaks($teacher, $plan); $this->fail('Replacement was accepted'); } catch (ValidationException $e) { $this->assertStringContainsString('replacement', $e->getMessage()); }
        $this->assertSame($before, DB::table('teacher_breaks')->get()->toJson());
        $plan[1]['weekday'] = $plan[0]['weekday'];
        $this->expectException(ValidationException::class); $service->saveBreaks($teacher, $plan, true);
    }

    public function test_break_preview_requires_confirmation_and_revalidates_after_a_new_lesson(): void
    {
        [$teacher, $subject, $service] = $this->fixture(); $subject->update(['teacher_id' => $teacher->id]);
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->post(route('teacher-management.breaks.preview', $teacher))->assertOk();
        $preview = session('teacher_break_preview'); $b = $preview['plans'][$teacher->id]['breaks'][0];
        $this->assertSame(0, DB::table('teacher_breaks')->count());
        $this->post(route('teacher-management.breaks.confirm'), ['token' => $preview['token']])->assertSessionHasErrors('confirm');
        $this->lesson($subject, $b['start_time'], $b['end_time'], $b['weekday']);
        $this->post(route('teacher-management.breaks.confirm'), ['token' => $preview['token'], 'confirm' => 1])->assertSessionHasErrors('teacher');
        $this->assertSame(0, DB::table('teacher_breaks')->count()); $this->assertSame(1, Schedule::count());
    }

    public function test_auto_assignment_preview_is_qualified_transactional_and_revalidated(): void
    {
        [$teacher, $subject, $service] = $this->fixture();
        DB::table('schedules')->insert(['subject_id' => $subject->id, 'school_class_id' => $subject->school_class_id, 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'room' => 'R1']);
        $proposal = $service->autoAssignments();
        $this->assertCount(1, $proposal['assignments']); $this->assertNull($subject->fresh()->teacher_id);
        $service->confirmAssignments($proposal['assignments']); $this->assertSame($teacher->id, $subject->fresh()->teacher_id);
        $this->assertSame([], $service->autoAssignments()['assignments']);
    }

    public function test_settings_demand_and_shared_subject_capacity(): void
    {
        [$teacher, $math, $service] = $this->fixture();
        $physics = Subject::create(['name' => 'Physics', 'code' => 'P7', 'school_class_id' => $math->school_class_id]);
        $math->update(['required_weekly_minutes' => 900]); $physics->update(['required_weekly_minutes' => 900]);
        $periods = $service->periods($teacher)->map(fn ($p) => collect((array)$p)->only(['weekday', 'start_time', 'end_time', 'unavailable'])->all())->all();
        $service->saveProfile($teacher, ['Mathematics', 'Physics'], null, $periods);
        $coverage = $service->coverage(); $this->assertEquals(20, array_sum(array_column($coverage, 'available'))); $this->assertEquals(10, array_sum(array_column($coverage, 'missing')));
        $this->assertEquals(17, $service->demand()['hours']); $this->assertSame(30, $service->demand()['target']);
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->post(route('teacher-management.settings'), ['target' => 35, 'weekly_hours' => 18])->assertSessionHasNoErrors();
        $this->assertSame(35, $service->demand()['target']); $this->assertSame(1080, $service->limit($teacher));
    }

    public function test_ui_keeps_hero_four_cards_themes_and_admin_only_management(): void
    {
        [$teacher] = $this->fixture(); $admin = User::factory()->create(['role' => 'administrator']);
        $page = $this->actingAs($admin)->get('/school/teachers')->assertOk()->assertSeeInOrder(['teacher-hero', 'Teacher overview', 'directory-panel', 'Quick Actions'])->assertSee('Add Teacher')->assertSee('Break Hours');
        $this->assertSame(4, substr_count($page->getContent(), '<article class="teacher-stat"'));
        foreach (['Light', 'Night', 'Relax', 'Cute'] as $theme) $page->assertSee($theme);
        $this->get(route('teacher-management.show', $teacher))->assertOk()->assertSee('Weekly Timetable')->assertSee('Break Schedule');
        $this->get(route('teacher-management.reports'))->assertOk()->assertSee('Subject Coverage');
        $this->actingAs($teacher)->post(route('teacher-management.breaks.preview', $teacher))->assertForbidden();
    }

    public function test_clean_teacher_form_creates_verified_profile_and_rejects_duplicate_email(): void
    {
        $this->fixture(); $admin = User::factory()->create(['role' => 'administrator']);
        $data = ['name' => 'Fictional Sok New', 'gender' => 'female', 'phone' => '012345678', 'email' => 'new@school.test', 'program' => 'Khmer', 'subject1' => 'Mathematics', 'subject2' => null, 'weekly_hours' => 20, 'working_sessions' => 'Both', 'status' => 'active'];
        $this->actingAs($admin)->get(route('teacher-management.create'))->assertOk()->assertSee('Subject 2 (Optional)');
        $this->post(route('teacher-management.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $teacher = User::where('email', 'new@school.test')->firstOrFail();
        $service = app(TeacherManagementService::class);
        $this->assertSame(['Mathematics'], $service->qualifications($teacher)->all()); $this->assertCount(10, $service->periods($teacher));
        $this->post(route('teacher-management.store'), $data)->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'new@school.test')->count());
    }

    public function test_successful_break_confirmation_saves_only_previewed_breaks_and_cannot_replay(): void
    {
        [$teacher, $subject, $service] = $this->fixture(); $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->post(route('teacher-management.breaks.preview', $teacher))->assertOk();
        $preview = session('teacher_break_preview');
        $this->post(route('teacher-management.breaks.confirm'), ['confirm' => 1, 'token' => $preview['token']])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertCount(3, $service->breaks($teacher));
        $this->post(route('teacher-management.breaks.confirm'), ['confirm' => 1, 'token' => $preview['token']])->assertSessionHasErrors('teacher');
        $this->assertCount(3, $service->breaks($teacher));
    }

    public function test_overlapping_classes_and_unavailable_periods_are_rejected(): void
    {
        [$teacher, $first, $service] = $this->fixture(); $first->update(['teacher_id' => $teacher->id]); $this->lesson($first);
        $class = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning']);
        $other = Subject::create(['name' => 'Mathematics', 'code' => 'M8', 'school_class_id' => $class->id, 'teacher_id' => $teacher->id]);
        try { $this->lesson($other, '07:30', '08:30'); $this->fail('Overlap accepted'); } catch (ValidationException $e) { $this->assertStringContainsString('conflicts', $e->getMessage()); }
        DB::table('teacher_working_periods')->insert(['teacher_id' => $teacher->id, 'weekday' => 'Tuesday', 'start_time' => '08:00', 'end_time' => '10:00', 'unavailable' => true]);
        $this->expectException(ValidationException::class); $this->lesson($first, '08:00', '09:00', 'Tuesday');
    }

    public function test_invalid_break_length_and_break_edits_preserve_existing_plan(): void
    {
        [$teacher, $subject, $service] = $this->fixture(); $plan = $service->proposeBreaks($teacher); $service->saveBreaks($teacher, $plan);
        $original = $service->breaks($teacher)->toJson(); $invalid = $plan;
        $invalid[0]['end_time'] = $invalid[0]['start_time'];
        try { $service->saveBreaks($teacher, $invalid, true); $this->fail('Invalid duration accepted'); } catch (ValidationException $e) { $this->assertStringContainsString('one hour', $e->getMessage()); }
        $this->assertSame($original, $service->breaks($teacher)->toJson());
        $admin = User::factory()->create(['role' => 'administrator']); $breaks = $service->breaks($teacher);
        $this->actingAs($admin)->put(route('teacher-management.breaks.edit', [$teacher, $breaks[0]->id]), ['confirm' => 1, 'weekday' => $breaks[1]->weekday, 'start_time' => substr($breaks[0]->start_time, 0, 5)])->assertSessionHasErrors('teacher');
        $this->assertSame($original, $service->breaks($teacher)->toJson());
    }

    public function test_sample_teachers_are_never_created_without_explicit_confirmation(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']); $before = User::count();
        $this->actingAs($admin)->post(route('teacher-management.samples.preview'), ['count' => 2])->assertOk()->assertSee('Fictional');
        $preview = session('teacher_sample_preview'); $this->assertSame($before, User::count());
        $this->post(route('teacher-management.samples.confirm'), ['token' => $preview['token']])->assertSessionHasErrors('confirm');
        $this->assertSame($before, User::count());
        $this->post(route('teacher-management.samples.confirm'), ['token' => $preview['token'], 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame($before + 2, User::count());
        $this->post(route('teacher-management.samples.confirm'), ['token' => $preview['token'], 'confirm' => 1])->assertSessionHasErrors('teacher');
        $this->assertSame($before + 2, User::count());
    }

    public function test_available_time_picker_excludes_breaks_and_existing_lessons(): void
    {
        [$teacher, $subject, $service] = $this->fixture(); $subject->update(['teacher_id' => $teacher->id]);
        $this->lesson($subject);
        $plan = $service->proposeBreaks($teacher); $service->saveBreaks($teacher, $plan);
        $admin = User::factory()->create(['role' => 'administrator']);
        $result = $this->actingAs($admin)->getJson(route('teacher-management.available-times', ['teacher' => $teacher, 'subject_id' => $subject->id, 'day' => 'Monday']))->assertOk()->json('times');
        $this->assertNotContains('07:00', array_column($result, 'start'));
        foreach ($plan as $b) {
            if ($b['weekday'] === 'Monday') $this->assertNotContains($b['start_time'], array_column($result, 'start'));
        }
        $this->assertSame(1, Schedule::count()); $this->assertCount(3, $service->breaks($teacher));
    }

    public function test_two_sessions_share_one_identity_but_both_contribute_to_demand(): void
    {
        [$teacher, $morning, $service] = $this->fixture();
        $groupId = $morning->schoolClass->class_group_id;
        ClassSession::create(['class_group_id' => $groupId, 'session' => 'Afternoon', 'starts_at' => '13:00', 'ends_at' => '17:00']);
        $class = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Afternoon', 'class_group_id' => $groupId]);
        $afternoon = Subject::create(['name' => 'Mathematics', 'code' => 'M7-AF', 'school_class_id' => $class->id]);
        $periods = $service->periods($teacher)->map(fn ($p) => collect((array)$p)->only(['weekday', 'start_time', 'end_time', 'unavailable'])->all())->all();
        foreach (TeacherManagementService::DAYS as $day) $periods[] = ['weekday' => $day, 'start_time' => '13:00', 'end_time' => '17:00', 'unavailable' => false];
        $service->saveProfile($teacher, ['Mathematics'], null, $periods);
        $morning->update(['teacher_id' => $teacher->id]); $afternoon->update(['teacher_id' => $teacher->id]);
        $this->lesson($morning); $this->lesson($afternoon, '13:00', '15:00');
        $this->assertEquals(34, $service->demand()['hours']); $this->assertSame(1, ClassGroup::count());
        $this->assertCount(1, $service->workload($teacher)['classes']); $this->assertEquals(3, $service->workload($teacher)['weekly']);
    }

    public function test_auto_assignment_will_not_assign_a_qualified_teacher_during_a_break(): void
    {
        [$teacher, $subject, $service] = $this->fixture();
        $plan = [];
        foreach (['Monday', 'Tuesday', 'Wednesday'] as $day) $plan[] = ['weekday' => $day, 'start_time' => '07:00', 'end_time' => '08:00'];
        $service->saveBreaks($teacher, $plan);
        DB::table('schedules')->insert(['subject_id' => $subject->id, 'school_class_id' => $subject->school_class_id, 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'room' => 'R1']);
        $proposal = $service->autoAssignments();
        $this->assertSame([], $proposal['assignments']); $this->assertCount(1, $proposal['missing']);
        $this->assertNull($subject->fresh()->teacher_id); $this->assertSame(1, Schedule::count());
    }
}
