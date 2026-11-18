<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, ClassSession, Classroom, Schedule, SchoolClass, Student, Subject, User};
use App\Services\{ClassroomAvailabilityService, ClassroomBookingService, LessonManagementService, TeacherManagementService, UnifiedTimetableGenerator};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UnifiedTimetableTest extends TestCase
{
    use RefreshDatabase;

    public function test_compact_teacher_schedule_shows_all_subjects_inside_preview(): void
    {
        $f = $this->fixture();
        app(LessonManagementService::class)->save($this->data($f));
        $this->actingAs($f['admin'])->get(route('timetables.lessons.create'))->assertOk()->assertSee('id="teacher-schedule-preview"', false)->assertSee('Close teacher schedule');
        $html = $this->getJson(route('timetables.data', ['view' => 'teachers', 'program' => 'Khmer', 'teacher' => $f['primary']->id, 'academic_year' => '2026-2027', 'compact' => 1]))->assertOk()->json('html');
        $this->assertStringContainsString('teacher-preview-week', $html);
        $this->assertStringContainsString('Mathematics', $html);
        $this->assertStringContainsString('Free period', $html);
        $this->assertStringNotContainsString('Edit lesson', $html);
    }

    public function test_teacher_subject_focus_keeps_other_subjects_visible_on_page_and_refresh(): void
    {
        $f = $this->fixture();
        $math = app(LessonManagementService::class)->save($this->data($f));
        DB::table('teacher_qualifications')->insert(['teacher_id' => $f['primary']->id, 'subject_name' => 'History', 'slot' => 2]);
        $history = Subject::create(['name' => 'History', 'code' => 'H-FOCUS', 'school_class_id' => $f['classes'][0]->id, 'teacher_id' => $f['primary']->id, 'lesson_minutes' => 60]);
        $other = DB::table('schedules')->insertGetId(['school_class_id' => $f['classes'][0]->id, 'subject_id' => $history->id, 'teacher_id' => $f['primary']->id, 'classroom_id' => $f['rooms'][0]->id, 'room' => $f['rooms'][0]->room_number, 'entry_type' => 'academic', 'day' => 'Tuesday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'academic_year' => '2026-2027']);
        $filters = ['view' => 'teachers', 'program' => 'Khmer', 'subject' => 'Mathematics', 'teacher' => $f['primary']->id, 'academic_year' => '2026-2027'];
        $this->actingAs($f['admin'])->get(route('timetables.index', $filters))->assertOk()
            ->assertViewHas('lessons', fn ($lessons) => $lessons->contains('id', $math->id) && $lessons->contains('id', $other))
            ->assertSee('Busy · Other subject')->assertSee('lesson-other-subject', false)->assertSee('History');
        $html = $this->getJson(route('timetables.data', $filters))->assertOk()->json('html');
        $this->assertStringContainsString('History', $html);
        $this->assertStringContainsString('Busy · Other subject', $html);
        $this->assertStringContainsString('data-lesson-id="'.$other.'" class="shared-lesson lesson-other-subject', $html);
        $this->assertStringNotContainsString('data-lesson-id="'.$math->id.'" class="shared-lesson lesson-other-subject', $html);

        foreach ([
            ['view' => 'students', 'class' => $f['classes'][0]->id],
            ['view' => 'classrooms', 'classroom' => $f['rooms'][0]->id],
        ] as $selection) {
            $query = $selection + array_diff_key($filters, ['teacher' => true]);
            $html = $this->getJson(route('timetables.data', $query + ['fragment' => 1]))->assertOk()->json('html');
            $this->assertStringContainsString('data-lesson-id="'.$other.'" class="shared-lesson lesson-other-subject', $html);
            $this->assertStringContainsString('data-lesson-id="'.$math->id.'" class="shared-lesson', $html);
            $this->assertStringNotContainsString('data-lesson-id="'.$math->id.'" class="shared-lesson lesson-other-subject', $html);
        }
    }

    public function test_auto_fill_chooses_teacher_with_time_for_all_math_blocks(): void
    {
        $f = $this->fixture();
        $f['subjects'][0]->update(['required_weekly_minutes' => 480]);
        DB::table('teacher_working_periods')->where('teacher_id', $f['primary']->id)->where('weekday', '!=', 'Monday')->delete();
        DB::table('teacher_working_periods')->where('teacher_id', $f['primary']->id)->update(['end_time' => '09:00']);
        $this->actingAs($f['admin'])->postJson(route('timetables.lessons.auto-fill'), ['school_class_id' => $f['classes'][0]->id, 'academic_year' => '2026-2027'])
            ->assertOk()->assertJsonPath('complete', true)->assertJsonCount(4, 'slots')
            ->assertJsonPath('slots.0.teacher_id', $f['substitute']->id);
        $this->assertSame(0, Schedule::count());
    }

    public function test_khmer_auto_fill_assigns_teachers_and_reports_subjects_without_teachers_without_saving(): void
    {
        $f = $this->fixture();
        Subject::create(['name' => 'History', 'code' => 'H-AUTO', 'school_class_id' => $f['classes'][0]->id, 'required_weekly_minutes' => 60, 'lesson_minutes' => 60]);
        $count = Schedule::count();
        $response = $this->actingAs($f['admin'])->postJson(route('timetables.lessons.auto-fill'), ['school_class_id' => $f['classes'][0]->id, 'academic_year' => '2026-2027'])
            ->assertOk()->assertJsonPath('complete', false)->assertJsonCount(1, 'slots')->assertJsonPath('unfilled.0.subject', 'History');
        $this->assertStringContainsString('No active qualified', $response->json('unfilled.0.reason'));
        $row = $response->json('slots.0');
        $this->assertSame($f['subjects'][0]->id, $row['subject_id']);
        $this->assertContains($row['teacher_id'], [$f['primary']->id, $f['substitute']->id]);
        app(LessonManagementService::class)->validate(new Schedule($row));
        $this->assertSame($count, Schedule::count());
        $this->post(route('timetables.lessons.store'), ['school_class_id' => $f['classes'][0]->id, 'academic_year' => '2026-2027', 'slots' => [$row]])->assertSessionHasNoErrors();
        $this->assertSame($count + 1, Schedule::count());
        $this->postJson(route('timetables.lessons.auto-fill'), ['school_class_id' => $f['classes'][0]->id, 'academic_year' => '2026-2027'])
            ->assertOk()->assertJsonCount(0, 'slots');
        $this->assertSame($count + 1, Schedule::count());
    }

    public function test_khmer_auto_fill_checks_staged_weekly_hours_and_conflicts(): void
    {
        $f = $this->fixture();
        $f['subjects'][0]->update(['required_weekly_minutes' => 480]);
        $payload = ['school_class_id' => $f['classes'][0]->id, 'academic_year' => '2026-2027'];
        $response = $this->actingAs($f['admin'])->postJson(route('timetables.lessons.auto-fill'), $payload)
            ->assertOk()->assertJsonPath('complete', true)->assertJsonCount(4, 'slots');
        $this->assertSame(1, collect($response->json('slots'))->pluck('teacher_id')->unique()->count());
        $this->assertSame(0, Schedule::count());
        $this->post(route('timetables.lessons.store'), $payload + ['slots' => $response->json('slots')])->assertSessionHasNoErrors();
        $this->assertSame(4, Schedule::count());
    }

    public function test_auto_fill_rejects_other_programs_and_reports_unavailable_teachers(): void
    {
        $f = $this->fixture();
        DB::table('teacher_working_periods')->update(['unavailable' => true]);
        $payload = ['school_class_id' => $f['classes'][0]->id, 'academic_year' => '2026-2027'];
        $response = $this->actingAs($f['admin'])->postJson(route('timetables.lessons.auto-fill'), $payload)
            ->assertOk()->assertJsonCount(0, 'slots')->assertJsonPath('complete', false)->assertJsonPath('unfilled.0.subject', 'Mathematics');
        $this->assertStringContainsString('unavailable', $response->json('unfilled.0.reason'));
        $f['classes'][0]->update(['program' => 'English']);
        $this->postJson(route('timetables.lessons.auto-fill'), $payload)->assertUnprocessable();
        $this->actingAs($f['student'])->postJson(route('timetables.lessons.auto-fill'), $payload)->assertForbidden();
    }

    public function test_history_selection_shows_assigned_history_teachers_and_replaces_unrelated_teacher(): void
    {
        $f = $this->fixture();
        $historyTeacher = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer', 'name' => 'History Teacher']);
        Subject::create(['name' => 'History', 'code' => 'HISTORY-FILTER', 'school_class_id' => $f['classes'][0]->id, 'teacher_id' => $historyTeacher->id, 'lesson_minutes' => 60]);
        $filters = ['view' => 'teachers', 'program' => 'Khmer', 'subject' => 'History', 'teacher' => $f['primary']->id, 'academic_year' => '2026-2027'];
        $this->actingAs($f['admin'])->get(route('timetables.index', $filters))->assertOk()
            ->assertViewHas('teachers', fn ($teachers) => $teachers->modelKeys() === [$historyTeacher->id])
            ->assertViewHas('selected', $historyTeacher->id)
            ->assertViewHas('lessons', fn ($lessons) => $lessons->isEmpty());
        $this->getJson(route('timetables.data', $filters))->assertOk();
    }

    public function test_khmer_subject_search_filters_schedule_and_live_refresh(): void
    {
        $f = $this->fixture();
        $lesson = app(LessonManagementService::class)->save($this->data($f));
        $filters = ['view' => 'teachers', 'program' => 'Khmer', 'subject' => $lesson->subject->name, 'academic_year' => $lesson->academic_year];
        $this->actingAs($f['admin'])->get(route('timetables.index', $filters))
            ->assertOk()->assertSee('<select class="form-select" name="subject">', false)->assertDontSee('Search subject')
            ->assertViewHas('lessons', fn ($lessons) => $lessons->modelKeys() === [$lesson->id]);
        $this->getJson(route('timetables.data', $filters))->assertOk();
        $filters['subject'] = 'No matching subject';
        $this->get(route('timetables.index', $filters))->assertOk()
            ->assertViewHas('lessons', fn ($lessons) => $lessons->isEmpty());
        $this->get(route('timetables.index', ['view' => 'teachers', 'program' => 'English']))
            ->assertOk()->assertDontSee('Search subject');
    }

    public function test_teacher_options_match_full_validation_for_multiple_rooms(): void
    {
        $f = $this->fixture();
        DB::table('classroom_bookings')->delete();
        $row = $this->data($f);
        $response = $this->actingAs($f['admin'])->getJson(route('timetables.lessons.options', $row))->assertOk();
        $this->assertGreaterThan(1, count($response->json('rooms')));
        foreach ($response->json('teacher_statuses') as $teacher) {
            $expected = [];
            foreach (Classroom::where('status', 'active')->get() as $room) {
                $slot = new Schedule(array_replace($row, ['teacher_id' => $teacher['id'], 'classroom_id' => $room->id, 'room' => $room->room_number, 'entry_type' => 'academic']));
                try {
                    app(LessonManagementService::class)->validate($slot);
                    $expected[] = $room->id;
                } catch (ValidationException $error) {
                    // Unavailable rooms must not be offered by the optimized lookup.
                }
            }
            $this->assertEqualsCanonicalizing($expected, $teacher['room_ids']);
        }
    }

    public function test_teacher_choices_include_qualified_busy_teachers_with_reasons(): void
    {
        $f = $this->fixture();
        $row = array_replace($this->data($f), ['day' => 'Wednesday']);
        $response = $this->actingAs($f['admin'])->getJson(route('timetables.lessons.options', $row))
            ->assertOk()->assertJsonCount(0, 'teachers')->assertJsonCount(2, 'teacher_statuses');
        foreach ($response->json('teacher_statuses') as $teacher) {
            $this->assertFalse($teacher['available']);
            $this->assertStringContainsString('designated free period', $teacher['reason']);
        }
        $response = $this->getJson(route('timetables.lessons.options', $this->data($f)))
            ->assertOk()->assertJsonCount(2, 'teacher_statuses');
        foreach ($response->json('teacher_statuses') as $teacher) {
            $this->assertTrue($teacher['available']);
            $this->assertNotEmpty($teacher['room_ids']);
        }
    }

    public function test_subject_weekly_hours_block_a_third_two_hour_lesson(): void
    {
        $f = $this->fixture();
        $row = $this->data($f);
        Subject::findOrFail($row['subject_id'])->update(['required_weekly_minutes' => 240]);
        $service = app(LessonManagementService::class);
        $first = $service->save($row);
        $service->save(array_replace($row, ['day' => 'Tuesday']));
        // Editing an existing lesson does not count its duration twice.
        $service->save($row, $first);
        try {
            $service->save(array_replace($row, ['day' => 'Thursday']));
            $this->fail('A third two-hour lesson exceeded the weekly subject limit.');
        } catch (ValidationException $error) {
            $this->assertStringContainsString('weekly limit of 4 hours', $error->getMessage());
        }
        $this->assertSame(2, Schedule::count());
        // A different academic year has its own allowance.
        $service->save(array_replace($row, ['academic_year' => '2027-2028']));
        $this->assertSame(3, Schedule::count());
    }

    public function test_week_planner_saves_days_together_and_rolls_back_conflicts(): void
    {
        $f = $this->fixture();
        $f['subjects'][0]->update(['required_weekly_minutes' => 480]);
        $row = $this->data($f);
        $payload = ['school_class_id' => $row['school_class_id'], 'academic_year' => $row['academic_year'], 'slots' => [$row, array_replace($row, ['day' => 'Tuesday'])]];
        $this->actingAs($f['admin'])->post(route('timetables.lessons.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, Schedule::count());
        $payload['slots'] = [array_replace($row, ['day' => 'Wednesday']), $row];
        $this->post(route('timetables.lessons.store'), $payload)->assertSessionHasErrors();
        $this->assertSame(2, Schedule::count());
        $this->assertFalse(Schedule::where('day', 'Wednesday')->exists());
    }

    public function test_a_subject_cannot_use_different_teachers_on_different_weekdays(): void
    {
        $f = $this->fixture();
        $f['subjects'][0]->update(['required_weekly_minutes' => 480]);
        $service = app(LessonManagementService::class);
        $service->save($this->data($f));
        $service->save(array_replace($this->data($f), ['day' => 'Tuesday']));
        $differentTeacher = array_replace($this->data($f), ['day' => 'Thursday', 'teacher_id' => $f['substitute']->id]);
        foreach ([false, true] as $modelWrite) {
            try {
                if ($modelWrite) {
                    Schedule::create(collect($differentTeacher)->except(['program', 'session'])->all());
                } else {
                    $service->save($differentTeacher);
                }
                $this->fail('A second teacher was assigned to the same class subject.');
            } catch (ValidationException $error) {
                $this->assertStringContainsString('Each subject must have one teacher', $error->getMessage());
            }
        }
        $this->assertSame(2, Schedule::count());
        $this->assertSame([$f['primary']->id], Schedule::pluck('teacher_id')->unique()->all());
        // Another class can assign a different teacher for the same subject name.
        $service->save(array_replace($this->data($f, 1), ['teacher_id' => $f['substitute']->id]));
        $this->assertSame(3, Schedule::count());
    }

    public function test_subject_teacher_consistency_ignores_cancelled_and_previous_year_lessons(): void
    {
        $f = $this->fixture();
        $service = app(LessonManagementService::class);
        $old = $service->save($this->data($f));
        DB::table('schedules')->where('id', $old->id)->update(['academic_year' => '2025-2026']);
        $cancelled = $service->save(array_replace($this->data($f), ['day' => 'Tuesday']));
        $service->cancel($cancelled);
        $new = $service->save(array_replace($this->data($f), ['teacher_id' => $f['substitute']->id]));
        $this->assertSame($f['substitute']->id, $new->teacher_id);
    }

    private function reservePhysicalRoom(SchoolClass $class, string $number): Classroom
    {
        $grade = (int) preg_replace('/\D/', '', $class->name);
        $group = ClassGroup::create(['name' => $class->name, 'grade' => $grade, 'section' => $class->section, 'program' => $class->program, 'status' => 'active']);
        [$from, $to] = $class->session === 'Evening' ? ['17:30','18:30'] : SchoolClass::SESSION_HOURS[$class->session];
        $session = ClassSession::create(['class_group_id' => $group->id, 'session' => $class->session, 'starts_at' => $from, 'ends_at' => $to]);
        $class->update(['class_group_id' => $group->id]);
        $room = Classroom::create(['room_number' => $number, 'name' => $number, 'capacity' => 40, 'status' => 'active']);
        foreach (TeacherManagementService::DAYS as $day) app(ClassroomBookingService::class)->create(['class_group_id' => $group->id, 'class_session_id' => $session->id, 'classroom_id' => $room->id, 'teacher_id' => null, 'day_of_week' => $day, 'starts_at' => $from, 'ends_at' => $to]);
        return $room;
    }

    public function test_global_preview_covers_more_than_one_page_and_all_three_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        foreach (['Morning', 'Afternoon', 'Evening'] as $index => $session) {
            $class = SchoolClass::create(['name' => 'Grade '.($index + 7), 'section' => 'A', 'program' => $session === 'Evening' ? 'English' : 'Khmer', 'session' => $session]);
            $room = $this->reservePhysicalRoom($class, 'PHYSICAL-'.($index + 1));
            [$from, $to] = $session === 'Evening' ? ['17:30', '18:30'] : SchoolClass::SESSION_HOURS[$session];
            for ($row = 0; $row < 15; $row++) DB::table('schedules')->insert(['school_class_id' => $class->id, 'entry_type' => 'relaxation', 'day' => 'Monday', 'starts_at' => $from, 'ends_at' => $to, 'room' => $room->room_number, 'academic_year' => (2026 - $row).'-'.(2027 - $row), 'cancelled_at' => $row === 0 ? null : now()]);
        }
        $scan = app(\App\Services\TimetableRoomReconciliation::class)->scan();
        $this->assertSame(45, $scan['total_lessons']);
        $this->assertSame(3, $scan['affected_classrooms']);
        $this->assertCount(45, $scan['verified']);
        $this->actingAs($admin)->post(route('timetables.rooms.preview'), ['all_verified' => 1, 'page' => 2, 'classroom' => 1])->assertOk();
        $this->assertCount(45, session('room_correction_preview.plan.rows'));
        $this->assertSame(0, DB::table('schedules')->whereNotNull('classroom_id')->count());
    }

    public function test_failed_backup_aborts_the_entire_room_repair(): void
    {
        $f = $this->fixture();
        $lesson = app(LessonManagementService::class)->save($this->data($f));
        DB::table('schedules')->where('id', $lesson->id)->update(['classroom_id' => null]);
        $service = app(\App\Services\TimetableRoomReconciliation::class);
        $plan = $service->globalPreview();
        \Illuminate\Support\Facades\Storage::shouldReceive('disk')->with('local')->andReturnSelf();
        \Illuminate\Support\Facades\Storage::shouldReceive('put')->once()->andReturn(false);
        try { $service->confirm($plan, $f['admin']->id); $this->fail('A failed backup must abort repairs.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('Backup failed', $e->getMessage()); }
        $this->assertNull($lesson->fresh()->classroom_id);
        $this->assertSame(0, DB::table('lesson_room_corrections')->count());
    }

    public function test_legacy_lesson_writes_cannot_create_another_label_only_assignment(): void
    {
        $f = $this->fixture();
        DB::table('classroom_bookings')->where('class_group_id', $f['classes'][0]->class_group_id)->delete();
        $data = $this->data($f);
        unset($data['classroom_id']);
        $data['room'] = $f['rooms'][0]->room_number;
        try { app(LessonManagementService::class)->save($data, strict: false); $this->fail('A label must not establish physical room identity.'); }
        catch (ValidationException $e) { $this->assertStringContainsString('physical classroom', $e->getMessage()); }
        $this->assertSame(0, Schedule::count());
    }

    public function test_school_wide_room_repair_ignores_page_filters_and_preserves_uncertain_and_valid_lessons(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $f = $this->fixture();
        $a = app(LessonManagementService::class)->save($this->data($f));
        $b = app(LessonManagementService::class)->save(array_replace($this->data($f, 1), ['day' => 'Tuesday']));
        $f['subjects'][0]->update(['required_weekly_minutes' => 240]);
        $valid = app(LessonManagementService::class)->save(array_replace($this->data($f), ['day' => 'Thursday']));
        DB::table('schedules')->whereIn('id', [$a->id, $b->id])->update(['classroom_id' => null]);
        // A name alone never establishes the physical ID without a reservation.
        $uncertain = DB::table('schedules')->insertGetId(['subject_id' => $f['subjects'][0]->id, 'school_class_id' => $f['classes'][0]->id, 'teacher_id' => $f['primary']->id, 'day' => 'Friday', 'starts_at' => '07:00', 'ends_at' => '09:00', 'room' => 'UNKNOWN-ALIAS', 'entry_type' => 'academic']);
        $originalValid = $valid->getAttributes();
        $service = app(\App\Services\TimetableRoomReconciliation::class);
        $scan = $service->scan();
        $this->assertSame(2, $scan['total_classrooms']);
        $this->assertSame(3, $scan['affected_lessons']);
        $this->assertCount(2, $scan['verified']);
        $this->assertCount(1, $scan['manual_review']);
        $this->actingAs($f['admin'])->get(route('timetables.rooms.review', ['scan_all' => 1, 'classroom' => $f['rooms'][0]->id]))->assertOk()->assertSee('Scan All Classrooms')->assertSee('Fix All Verified Room Assignments');
        $this->post(route('timetables.rooms.preview'), ['all_verified' => 1, 'classroom' => $f['rooms'][0]->id, 'page' => 2])->assertOk();
        $this->assertCount(2, session('room_correction_preview.plan.rows'));
        $this->assertNull($a->fresh()->classroom_id);
        $this->post(route('timetables.rooms.confirm'), ['token' => session('room_correction_preview.token'), 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame($f['rooms'][0]->id, $a->fresh()->classroom_id);
        $this->assertSame($f['rooms'][1]->id, $b->fresh()->classroom_id);
        $this->assertSame($originalValid, $valid->fresh()->getAttributes());
        $this->assertNull(Schedule::findOrFail($uncertain)->classroom_id);
        $this->assertSame(4, Schedule::count());
        $this->assertSame(2, DB::table('lesson_room_corrections')->count());
        $report = session('room_repair_report');
        $this->assertSame(2, $report['repaired']);
        $this->assertSame(1, $report['after']['affected_lessons']);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($report['backup']);
        $backup = json_decode(\Illuminate\Support\Facades\Storage::disk('local')->get($report['backup']), true);
        $this->assertNull(collect($backup['tables']['schedules'])->firstWhere('id', $a->id)['classroom_id']);
        $this->assertCount(1, $service->scan()['manual_review']);
    }

    public function test_global_room_scan_includes_historical_years_and_rejects_label_only_evidence(): void
    {
        $f = $this->fixture();
        $lesson = app(LessonManagementService::class)->save($this->data($f));
        DB::table('schedules')->where('id', $lesson->id)->update(['classroom_id' => null, 'academic_year' => '2020-2021', 'cancelled_at' => now()]);
        // Historical lessons are scanned even when excluded from active schedules.
        $service = app(\App\Services\TimetableRoomReconciliation::class);
        $this->assertSame(1, $service->scan()['total_lessons']);
        $this->assertCount(1, $service->scan()['verified']);
        DB::table('classroom_bookings')->where('class_group_id', $f['classes'][0]->class_group_id)->delete();
        $this->assertCount(0, $service->scan()['verified']);
        $this->assertCount(1, $service->scan()['manual_review']);
    }

    private function fixture(): array
    {
        $rules = app(TeacherManagementService::class);
        $admin = User::factory()->create(['role' => 'administrator']);
        $primary = User::factory()->create(['role' => 'teacher', 'name' => 'Primary Mathematics Teacher', 'program' => 'Khmer', 'status' => 'active']);
        $substitute = User::factory()->create(['role' => 'teacher', 'name' => 'Replacement Mathematics Teacher', 'program' => 'Khmer', 'status' => 'active']);
        $classes = $rooms = $subjects = [];
        foreach ([[7, 'A'], [8, 'B']] as [$grade, $section]) {
            $group = ClassGroup::create(['name' => 'Grade '.$grade, 'grade' => $grade, 'section' => $section, 'program' => 'Khmer', 'status' => 'active']);
            $session = ClassSession::create(['class_group_id' => $group->id, 'session' => 'Morning', 'starts_at' => '07:00', 'ends_at' => '11:00']);
            $room = Classroom::create(['room_number' => $grade.$section, 'name' => 'Room '.$grade.$section, 'capacity' => 40, 'status' => 'active']);
            $class = SchoolClass::create(['class_group_id' => $group->id, 'name' => 'Grade '.$grade, 'section' => $section, 'program' => 'Khmer', 'session' => 'Morning', 'room' => $room->room_number]);
            $subject = Subject::create(['name' => 'Mathematics', 'code' => 'M'.$grade.$section, 'school_class_id' => $class->id, 'teacher_id' => $primary->id, 'required_weekly_minutes' => 120, 'lesson_minutes' => 120]);
            foreach ($rules::DAYS as $day) app(ClassroomBookingService::class)->create(['class_group_id' => $group->id, 'class_session_id' => $session->id, 'classroom_id' => $room->id, 'teacher_id' => null, 'day_of_week' => $day, 'starts_at' => '07:00', 'ends_at' => '11:00']);
            $classes[] = $class; $rooms[] = $room; $subjects[] = $subject;
        }
        $periods = [];
        foreach ($rules::DAYS as $day) $periods[] = ['weekday' => $day, 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false];
        foreach ([$primary, $substitute] as $teacher) $rules->saveProfile($teacher, ['Mathematics'], 1200, $periods);
        foreach ([$primary, $substitute] as $teacher) $rules->saveBreaks($teacher, [
            ['weekday' => 'Monday', 'start_time' => '10:00', 'end_time' => '11:00'],
            ['weekday' => 'Wednesday', 'start_time' => '07:00', 'end_time' => '08:00'],
            ['weekday' => 'Friday', 'start_time' => '10:00', 'end_time' => '11:00'],
        ]);
        $parent = User::factory()->create(['role' => 'parent']);
        $student = User::factory()->create(['role' => 'student']);
        $otherStudent = User::factory()->create(['role' => 'student']);
        Student::factory()->create(['user_id' => $student->id, 'parent_id' => $parent->id, 'school_class_id' => $classes[0]->id, 'status' => 'active']);
        Student::factory()->create(['user_id' => $otherStudent->id, 'school_class_id' => $classes[1]->id, 'status' => 'active']);
        return compact('admin', 'primary', 'substitute', 'classes', 'rooms', 'subjects', 'student', 'otherStudent', 'parent');
    }

    private function completeCurriculumFixture(): array
    {
        $f = $this->fixture();
        $rules = app(TeacherManagementService::class);
        DB::table('teacher_management_settings')->where('id', 1)->update(['khmer_teacher_free_periods' => 0]);
        DB::table('teacher_break_plans')->delete();
        foreach ($f['classes'] as $index => $class) {
            $f['subjects'][$index]->update(['required_weekly_minutes' => 600, 'teacher_id' => $index === 0 ? $f['primary']->id : $f['substitute']->id]);
            $history = Subject::create(['name' => 'History', 'code' => 'H-FULL-'.$class->id, 'school_class_id' => $class->id, 'required_weekly_minutes' => 600, 'lesson_minutes' => 60]);
            $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer', 'status' => 'active']);
            $rules->saveProfile($teacher, ['History'], 1200, array_map(fn ($day) => ['weekday' => $day, 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false], $rules::DAYS));
            $history->update(['teacher_id' => $teacher->id]);
        }
        return $f;
    }

    private function data(array $f, int $index = 0): array
    {
        return ['program' => 'Khmer', 'session' => 'Morning', 'school_class_id' => $f['classes'][$index]->id, 'subject_id' => $f['subjects'][$index]->id, 'teacher_id' => $f['primary']->id, 'classroom_id' => $f['rooms'][$index]->id, 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '09:00', 'academic_year' => '2026-2027'];
    }

    public function test_complete_school_preparation_reports_missing_identities_without_publishing_partial_cohorts(): void
    {
        $f = $this->fixture();
        $before = DB::table('teacher_breaks')->orderBy('id')->get()->toJson();
        $draft = app(\App\Services\KhmerTimetableDraft::class)->prepare('2026-2027');
        $this->assertSame('blocked', $draft['status']);
        $this->assertNull($draft['plan']);
        $this->assertCount(28, collect($draft['blockers'])->where('type', 'identity'));
        $this->assertSame(0, Schedule::withoutGlobalScope('active')->count());
        $this->assertSame($before, DB::table('teacher_breaks')->orderBy('id')->get()->toJson());
        $this->actingAs($f['admin'])->post(route('timetables.generate.preview'), ['academic_year' => '2026-2027'])->assertOk()->assertSee('Khmer replacement timetable is INCOMPLETE')->assertDontSee('Publish approved timetable');
        $this->assertNull(session('unified_timetable_preview'));
    }

    public function test_legacy_khmer_endpoints_use_the_central_room_and_confirmation_rules(): void
    {
        $f = $this->fixture();
        $data = $this->data($f);
        unset($data['classroom_id']);
        $data['room'] = '7A';
        $this->actingAs($f['admin'])->post('/school/schedules', $data)->assertSessionHasErrors();
        $this->assertSame(0, Schedule::count());
        $lesson = app(LessonManagementService::class)->save($this->data($f));
        $this->get('/school/schedules/'.$lesson->id.'/edit')->assertOk()->assertSee('Available Qualified Teacher');
        $this->put('/school/schedules/'.$lesson->id, $this->data($f))->assertSessionHasErrors('confirm');
        $this->assertSame('Monday', $lesson->fresh()->day);
    }

    public function test_a_teacher_cannot_be_double_booked_between_khmer_and_english(): void
    {
        $f = $this->fixture();
        $f['classes'][1]->update(['program' => 'English']);
        $lesson = app(LessonManagementService::class)->save($this->data($f));
        $before = $lesson->getAttributes();
        try {
            app(LessonManagementService::class)->save(array_replace($this->data($f, 1), ['program' => 'English']));
            $this->fail('Cross-program teacher overlap was accepted.');
        } catch (ValidationException $e) { $this->assertStringContainsString('overlaps', $e->getMessage()); }
        $this->assertSame(1, Schedule::count());
        $this->assertSame($before, $lesson->fresh()->getAttributes());
    }

    public function test_one_lesson_is_created_edited_and_cancelled_in_all_three_views_without_copies(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->get(route('timetables.lessons.create'))->assertOk()->assertSee('Monday subjects');
        $this->post(route('timetables.lessons.store'), $this->data($f))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, Schedule::count());
        $lesson = Schedule::firstOrFail();
        $marker = 'data-lesson-id="'.$lesson->id.'"';
        foreach ([['view' => 'teachers', 'teacher' => $f['primary']->id], ['view' => 'students', 'class' => $f['classes'][0]->id], ['view' => 'classrooms', 'classroom' => $f['rooms'][0]->id]] as $query) {
            $page = $this->get(route('timetables.index', $query))->assertOk()->assertSee($marker, false)
                ->assertDontSee('rowspan="2"', false)->assertSee('Continued lesson')->assertSee('Primary Mathematics Teacher');
            $this->assertSame(2, substr_count($page->getContent(), $marker));
        }
        $update = array_replace($this->data($f), ['teacher_id' => $f['substitute']->id, 'version' => app(LessonManagementService::class)->version($lesson), 'confirm' => 1]);
        $this->put(route('timetables.lessons.update', $lesson), $update)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, Schedule::count());
        $this->assertSame($f['primary']->id, $f['subjects'][0]->fresh()->teacher_id);
        $this->assertSame($f['substitute']->id, $lesson->fresh()->teacher_id);
        $this->get(route('timetables.index', ['view' => 'teachers', 'teacher' => $f['primary']->id]))->assertOk()->assertDontSee($marker, false);
        foreach ([['view' => 'teachers', 'teacher' => $f['substitute']->id], ['view' => 'students', 'class' => $f['classes'][0]->id], ['view' => 'classrooms', 'classroom' => $f['rooms'][0]->id]] as $query) $this->get(route('timetables.index', $query))->assertOk()->assertSee($marker, false)->assertSee('Replacement Mathematics Teacher');
        $snapshot = app(ClassroomAvailabilityService::class)->forDateAndTime('2026-11-02', '08:30')->firstWhere('classroom.id', $f['rooms'][0]->id);
        $this->assertSame($f['substitute']->id, $snapshot['currentLesson']->teacher_id);
        $status = $this->getJson(route('classrooms.lesson-status', ['classroom' => $f['rooms'][0], 'date' => '2026-11-02', 'time' => '08:30']))->assertOk();
        $this->assertStringContainsString('Replacement Mathematics Teacher', $status->json('html'));
        $this->assertStringNotContainsString('Primary Mathematics Teacher', $status->json('html'));
        $this->post(route('timetables.lessons.cancel', $lesson), ['confirm' => 1, 'version' => app(LessonManagementService::class)->version($lesson->fresh())])->assertSessionHasNoErrors();
        $this->assertSame(0, Schedule::count());
        $this->assertSame(1, Schedule::withoutGlobalScope('active')->count());
        foreach ([['view' => 'teachers', 'teacher' => $f['substitute']->id], ['view' => 'students', 'class' => $f['classes'][0]->id], ['view' => 'classrooms', 'classroom' => $f['rooms'][0]->id]] as $query) {
            $response = $this->get(route('timetables.data', $query))->assertOk();
            $this->assertStringNotContainsString($marker, $response->json('html'));
        }
        $this->assertSame('Occupied', app(ClassroomAvailabilityService::class)->forDateAndTime('2026-11-02', '08:30')->firstWhere('classroom.id', $f['rooms'][0]->id)['occupancy']);
    }

    public function test_students_and_parents_only_see_their_enrolled_cohort_in_all_views(): void
    {
        $f = $this->fixture();
        $manager = app(LessonManagementService::class);
        $own = $manager->save($this->data($f));
        $other = $manager->save(array_replace($this->data($f, 1), ['day' => 'Tuesday']));
        foreach ([$f['student'], $f['parent']] as $user) {
            $this->actingAs($user)->get(route('timetables.index'))->assertOk()->assertSee('data-lesson-id="'.$own->id.'"', false)->assertDontSee('data-lesson-id="'.$other->id.'"', false);
            $this->get(route('timetables.index', ['view' => 'students', 'class' => $f['classes'][1]->id]))->assertNotFound();
            $this->get(route('timetables.index', ['view' => 'classrooms', 'classroom' => $f['rooms'][1]->id]))->assertNotFound();
            $this->get(route('timetables.index', ['view' => 'teachers', 'teacher' => $f['primary']->id]))->assertNotFound();
            $this->get(route('timetables.lessons.create'))->assertForbidden();
            $this->post(route('timetables.lessons.store'), $this->data($f))->assertForbidden();
            $this->get(route('timetables.data', ['view' => 'students', 'class' => $f['classes'][1]->id]))->assertNotFound();
        }
    }

    public function test_conflicts_invalid_duration_and_unqualified_teacher_are_rejected_on_save(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->post(route('timetables.lessons.store'), $this->data($f))->assertSessionHasNoErrors();
        foreach ([
            $this->data($f, 1), // Teacher overlap in a different room and class.
            array_replace($this->data($f), ['teacher_id' => $f['substitute']->id]), // Class overlap.
            array_replace($this->data($f, 1), ['teacher_id' => $f['substitute']->id, 'classroom_id' => $f['rooms'][0]->id]), // Physical reservation.
            array_replace($this->data($f), ['day' => 'Tuesday', 'ends_at' => '08:30']),
            array_replace($this->data($f), ['day' => 'Saturday']),
            array_replace($this->data($f), ['session' => 'Afternoon']),
            array_replace($this->data($f), ['subject_id' => $f['subjects'][1]->id]),
        ] as $data) $this->post(route('timetables.lessons.store'), $data)->assertSessionHasErrors();
        DB::table('teacher_qualifications')->where('teacher_id', $f['substitute']->id)->delete();
        $this->post(route('timetables.lessons.store'), array_replace($this->data($f, 1), ['teacher_id' => $f['substitute']->id, 'day' => 'Wednesday']))->assertSessionHasErrors();
        $this->assertSame(1, Schedule::count());
    }

    public function test_available_options_filter_busy_teachers_and_reserved_classrooms(): void
    {
        $f = $this->fixture();
        app(LessonManagementService::class)->save($this->data($f));
        $response = $this->actingAs($f['admin'])->getJson(route('timetables.lessons.options', $this->data($f, 1)))->assertOk();
        $this->assertSame([$f['substitute']->id], array_column($response->json('teachers'), 'id'));
        $this->assertSame([$f['rooms'][1]->id], array_column($response->json('rooms'), 'id'));
        $this->getJson(route('timetables.lessons.options', array_replace($this->data($f), ['ignore_lesson' => Schedule::first()->id])))->assertOk()->assertJsonCount(2, 'teachers');
    }

    public function test_lesson_setup_is_accessible_from_class_and_room_assignment_pages(): void
    {
        $f = $this->fixture();
        $class = $f['classes'][1];
        $this->actingAs($f['admin'])->get(route('class-groups.show', $class->class_group_id))->assertOk()
            ->assertSee('Set up subject lessons and teachers')->assertSee('school_class_id='.$class->id, false);
        $this->get(route('classroom-bookings.create', ['class_group_id' => $class->class_group_id]))->assertOk()
            ->assertSee('Set up lessons &amp; teachers', false)->assertSee('data-open-lesson-setup', false);
        $page = $this->get(route('timetables.lessons.create', ['school_class_id' => $class->id]))->assertOk()
            ->assertSee('Set up class schedule')->assertSee('Monday subjects')->assertSee('data-periods', false);
        $this->assertMatchesRegularExpression('/value="'.$class->id.'"[^>]*selected/s', $page->getContent());
    }

    public function test_available_teachers_match_the_program_and_existing_subject_assignment(): void
    {
        $f = $this->fixture();
        $f['subjects'][0]->update(['required_weekly_minutes' => 240]);
        $this->actingAs($f['admin']);
        $f['substitute']->update(['program' => 'English']);
        $this->getJson(route('timetables.lessons.options', $this->data($f)))->assertOk()
            ->assertJsonCount(1, 'teachers')->assertJsonPath('teachers.0.id', $f['primary']->id);
        $this->post(route('timetables.lessons.store', []), array_replace($this->data($f), ['teacher_id' => $f['substitute']->id]))->assertSessionHasErrors();
        $f['substitute']->update(['program' => 'Khmer']);
        app(LessonManagementService::class)->save($this->data($f));
        $this->getJson(route('timetables.lessons.options', array_replace($this->data($f), ['day' => 'Tuesday'])))->assertOk()
            ->assertJsonCount(1, 'teachers')->assertJsonPath('teachers.0.id', $f['primary']->id)
            ->assertJsonPath('teachers.0.room_ids.0', $f['rooms'][0]->id);
    }

    public function test_subject_from_another_class_is_rejected_by_available_options(): void
    {
        $f = $this->fixture();
        $this->actingAs($f['admin'])->getJson(route('timetables.lessons.options', array_replace($this->data($f), ['subject_id' => $f['classes'][1]->subjects()->firstOrFail()->id])))
            ->assertUnprocessable()->assertJsonValidationErrors('subject_id');
    }

    public function test_stale_edits_and_missing_confirmation_leave_the_shared_lesson_intact(): void
    {
        $f = $this->fixture();
        $lesson = app(LessonManagementService::class)->save($this->data($f));
        $data = $this->data($f) + ['version' => app(LessonManagementService::class)->version($lesson)];
        $this->actingAs($f['admin'])->put(route('timetables.lessons.update', $lesson), $data)->assertSessionHasErrors('confirm');
        $this->post(route('timetables.lessons.cancel', $lesson), ['version' => $data['version']])->assertSessionHasErrors('confirm');
        app(LessonManagementService::class)->save(array_replace($this->data($f), ['day' => 'Tuesday']), $lesson);
        $this->put(route('timetables.lessons.update', $lesson), $data + ['confirm' => 1])->assertSessionHasErrors();
        $this->assertSame('Tuesday', $lesson->fresh()->day);
        $this->assertSame(1, Schedule::count());
    }

    public function test_batch_preview_rolls_back_simulation_and_saves_all_cohorts_atomically(): void
    {
        $f = $this->completeCurriculumFixture();
        $generator = app(UnifiedTimetableGenerator::class);
        DB::table('teacher_break_plans')->delete();
        $batch = $generator->preview(array_column(array_map(fn ($c) => ['id' => $c->id], $f['classes']), 'id'));
        $this->assertCount(2, $batch['plans']);
        $this->assertSame(0, Schedule::withoutGlobalScope('active')->count());
        $this->assertSame(0, DB::table('teacher_breaks')->count());
        $generator->confirm($batch);
        $this->assertSame(30, Schedule::count());
        $this->assertSame(30, Schedule::where('entry_type', 'academic')->count());
        $this->assertSame(0, DB::table('teacher_breaks')->count());
        foreach (app(TeacherManagementService::class)->lessons($f['primary']) as $lesson) app(TeacherManagementService::class)->validateLesson($lesson->subject, $lesson);
    }

    public function test_an_infeasible_batch_never_keeps_a_partial_class_schedule(): void
    {
        $f = $this->fixture();
        $f['subjects'][1]->update(['required_weekly_minutes' => null]);
        $originalBreaks = DB::table('teacher_breaks')->orderBy('id')->get()->toJson();
        try { app(UnifiedTimetableGenerator::class)->preview(array_map(fn ($c) => $c->id, $f['classes'])); $this->fail('Incomplete curriculum was accepted'); }
        catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        $this->assertSame(0, Schedule::withoutGlobalScope('active')->count());
        $this->assertSame($originalBreaks, DB::table('teacher_breaks')->orderBy('id')->get()->toJson());
    }

    public function test_batch_http_flow_requires_confirmation_and_cannot_be_replayed(): void
    {
        $f = $this->completeCurriculumFixture();
        $this->actingAs($f['admin'])->post(route('timetables.generate.preview'), ['classes' => array_map(fn ($c) => $c->id, $f['classes'])])->assertOk()->assertSee('Unified Timetable Preview');
        $token = session('unified_timetable_preview.token');
        $this->post(route('timetables.generate.confirm'), ['token' => $token])->assertSessionHasErrors('confirm');
        $this->assertSame(0, Schedule::count());
        $this->post(route('timetables.generate.confirm'), ['token' => $token, 'confirm' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('timetables.generate.confirm'), ['token' => $token, 'confirm' => 1])->assertSessionHasErrors();
        $page = $this->get(route('timetables.index'))->assertOk()->assertSee('unified-timetable-hero')->assertSee('Teachers')->assertSee('Students')->assertSee('Classrooms');
        foreach (['Light', 'Night', 'Relax', 'Cute'] as $theme) $page->assertSee($theme);
    }

    public function test_a_shared_room_does_not_expose_another_cohort_to_students_or_parents(): void
    {
        $f = $this->fixture();
        $class = $f['classes'][1];
        $session = ClassSession::create(['class_group_id' => $class->class_group_id, 'session' => 'Afternoon', 'starts_at' => '13:00', 'ends_at' => '17:00']);
        $class->update(['session' => 'Afternoon', 'room' => $f['rooms'][0]->room_number]);
        app(ClassroomBookingService::class)->create(['class_group_id' => $class->class_group_id, 'class_session_id' => $session->id, 'classroom_id' => $f['rooms'][0]->id, 'teacher_id' => null, 'day_of_week' => 'Monday', 'starts_at' => '13:00', 'ends_at' => '17:00']);
        $periods = [];
        foreach (TeacherManagementService::DAYS as $day) $periods[] = ['weekday' => $day, 'start_time' => '13:00', 'end_time' => '17:00', 'unavailable' => false];
        DB::table('teacher_break_plans')->where('teacher_id', $f['substitute']->id)->delete();
        app(TeacherManagementService::class)->saveProfile($f['substitute'], ['Mathematics'], 1200, $periods);
        app(LessonManagementService::class)->save($this->data($f));
        $other = app(LessonManagementService::class)->save(array_replace($this->data($f, 1), ['session' => 'Afternoon', 'teacher_id' => $f['substitute']->id, 'classroom_id' => $f['rooms'][0]->id, 'starts_at' => '13:00', 'ends_at' => '15:00']));
        foreach ([$f['student'], $f['parent']] as $user) {
            $this->actingAs($user)->get(route('timetables.index', ['view' => 'classrooms', 'classroom' => $f['rooms'][0]->id]))->assertOk()->assertDontSee('data-lesson-id="'.$other->id.'"', false)->assertDontSee('Replacement Mathematics Teacher');
            $this->get(route('classrooms.show', ['classroom' => $f['rooms'][0], 'date' => '2026-11-02', 'time' => '13:30']))->assertOk()->assertDontSee('Replacement Mathematics Teacher')->assertDontSee('Grade 8B');
            $status = $this->getJson(route('classrooms.lesson-status', ['classroom' => $f['rooms'][0], 'date' => '2026-11-02', 'time' => '13:30']))->assertOk();
            $this->assertStringNotContainsString('Replacement Mathematics Teacher', $status->json('html'));
            $this->assertStringContainsString('Reserved for another cohort', $status->json('html'));
        }
    }

    public function test_central_creation_respects_teacher_free_periods_student_relaxation_and_workload(): void
    {
        $f = $this->fixture();
        $rules = app(TeacherManagementService::class);
        $rules->saveBreaks($f['primary'], [
            ['weekday' => 'Monday', 'start_time' => '07:00', 'end_time' => '08:00'],
            ['weekday' => 'Wednesday', 'start_time' => '07:00', 'end_time' => '08:00'],
            ['weekday' => 'Friday', 'start_time' => '10:00', 'end_time' => '11:00'],
        ], true);
        $this->actingAs($f['admin'])->post(route('timetables.lessons.store'), $this->data($f))->assertSessionHasErrors();
        app(LessonManagementService::class)->saveRelaxation(['school_class_id' => $f['classes'][0]->id, 'classroom_id' => $f['rooms'][0]->id, 'entry_type' => 'relaxation', 'day' => 'Tuesday', 'starts_at' => '08:00', 'ends_at' => '09:00']);
        $this->post(route('timetables.lessons.store'), array_replace($this->data($f), ['day' => 'Tuesday']))->assertSessionHasErrors();
        DB::table('teacher_profiles')->where('teacher_id', $f['primary']->id)->update(['weekly_minutes' => 120]);
        $this->post(route('timetables.lessons.store'), array_replace($this->data($f), ['day' => 'Thursday']))->assertSessionHasNoErrors();
        $this->post(route('timetables.lessons.store'), array_replace($this->data($f, 1), ['day' => 'Wednesday', 'starts_at' => '09:00', 'ends_at' => '10:00']))->assertSessionHasErrors();
        $this->assertSame(1, Schedule::where('entry_type', 'academic')->count());
    }

    public function test_stale_batch_and_unconfirmed_replacement_preserve_existing_lessons(): void
    {
        $f = $this->completeCurriculumFixture();
        $lesson = app(LessonManagementService::class)->save($this->data($f));
        $original = $lesson->getAttributes();
        $this->actingAs($f['admin'])->post(route('timetables.generate.preview'), ['classes' => [$f['classes'][0]->id], 'academic_year' => '2027-2028'])->assertOk()->assertSee('2027-2028');
        $token = session('unified_timetable_preview.token');
        $this->post(route('timetables.generate.confirm'), ['token' => $token, 'confirm' => 1])->assertSessionHasErrors();
        $this->assertSame($original, $lesson->fresh()->getAttributes());
        $f['rooms'][0]->update(['capacity' => 45]);
        $this->post(route('timetables.generate.confirm'), ['token' => $token, 'confirm' => 1, 'replace' => 1])->assertSessionHasErrors();
        $this->assertSame($original, $lesson->fresh()->getAttributes());
        $this->assertSame(1, Schedule::withoutGlobalScope('active')->count());
    }

    public function test_manual_creation_automatically_reserves_three_free_periods_for_an_eligible_teacher(): void
    {
        $f = $this->fixture();
        DB::table('teacher_break_plans')->where('teacher_id', $f['primary']->id)->delete();
        $this->actingAs($f['admin'])->post(route('timetables.lessons.store'), $this->data($f))->assertSessionHasNoErrors();
        $rules = app(TeacherManagementService::class);
        $breaks = $rules->breaks($f['primary']);
        $this->assertCount(3, $breaks);
        $this->assertCount(3, $breaks->pluck('weekday')->unique());
        foreach ($breaks as $break) $rules->validateBreak($f['primary'], (array) $break);
        $this->assertSame(1, Schedule::count());
    }

    public function test_part_time_teachers_with_fewer_than_three_working_days_do_not_get_fake_free_periods(): void
    {
        $f = $this->fixture();
        DB::table('teacher_break_plans')->where('teacher_id', $f['primary']->id)->delete();
        DB::table('teacher_working_periods')->where('teacher_id', $f['primary']->id)->whereNotIn('weekday', ['Monday', 'Tuesday'])->delete();
        $this->actingAs($f['admin'])->post(route('timetables.lessons.store'), $this->data($f))->assertSessionHasNoErrors();
        $this->assertFalse(app(TeacherManagementService::class)->eligibleForBreaks($f['primary']));
        $this->assertCount(0, app(TeacherManagementService::class)->breaks($f['primary']));
        $this->assertSame(1, Schedule::count());
    }

    public function test_a_late_batch_failure_rolls_back_every_previously_saved_cohort(): void
    {
        $f = $this->completeCurriculumFixture();
        $batch = app(UnifiedTimetableGenerator::class)->preview(array_map(fn ($c) => $c->id, $f['classes']));
        foreach ($batch['plans'][1]['rows'] as &$row) if ($row['subject_id']) { $row['classroom_id'] = 999999; break; }
        unset($row);
        $originalBreaks = DB::table('teacher_breaks')->orderBy('id')->get()->toJson();
        try { app(UnifiedTimetableGenerator::class)->confirm($batch); $this->fail('Invalid late plan was accepted'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { $this->assertNotEmpty($e->getMessage()); }
        $this->assertSame(0, Schedule::withoutGlobalScope('active')->count());
        $this->assertSame($originalBreaks, DB::table('teacher_breaks')->orderBy('id')->get()->toJson());
    }

    public function test_cancellation_history_prevents_deleting_its_subject_or_substitute_teacher(): void
    {
        $f = $this->fixture();
        $lesson = app(LessonManagementService::class)->save(array_replace($this->data($f), ['teacher_id' => $f['substitute']->id]));
        app(LessonManagementService::class)->cancel($lesson);
        $this->actingAs($f['admin'])->delete(route('modules.destroy', ['subjects', $f['subjects'][0]->id]))->assertSessionHasErrors();
        $this->delete(route('modules.destroy', ['users', $f['substitute']->id]))->assertSessionHasErrors();
        $this->assertSame(1, Schedule::withoutGlobalScope('active')->count());
        $this->assertDatabaseHas('users', ['id' => $f['substitute']->id]);
        $this->assertDatabaseHas('subjects', ['id' => $f['subjects'][0]->id]);
    }
}
