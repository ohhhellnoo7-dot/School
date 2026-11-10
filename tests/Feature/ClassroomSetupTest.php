<?php

namespace Tests\Feature;

use App\Models\{Building, ClassGroup, ClassSession, Classroom, Schedule, SchoolClass, Subject, User};
use App\Services\TeacherManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_a_third_evening_time_and_schedule_a_class_in_it(): void
    {
        $f = $this->fixture('Chinese', 'Evening');
        $response = $this->postJson(route('evening-study-periods.store'), ['program' => 'Chinese', 'label' => 'Evening 3', 'starts_at' => '19:30', 'ends_at' => '20:30'])->assertCreated();
        $id = $response->json('id');
        $this->assertDatabaseHas('evening_study_periods', ['id' => $id, 'program' => 'Chinese', 'label' => 'Evening 3']);
        app(TeacherManagementService::class)->saveProfile($f['teacher'], [$f['subject']->name], 1200, [
            ['weekday' => 'Monday', 'start_time' => '19:30', 'end_time' => '20:30', 'unavailable' => false],
        ]);
        $data = $this->data($f);
        $data['evening_period'] = $id;
        $data['slots'][0]['starts_at'] = '19:30';
        $this->post(route('classrooms.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('schedules', ['school_class_id' => $f['class']->id, 'starts_at' => '19:30', 'ends_at' => '20:30']);
        $room = Classroom::where('room_number', 'NEW1')->sole();
        $this->get(route('classrooms.show', ['classroom' => $room, 'program' => 'Chinese', 'session' => 'Evening', 'evening_period' => $id, 'date' => '2026-11-02']))
            ->assertOk()->assertSee('Evening 3')->assertSee('7:30 PM')->assertViewHas('attendanceClasses', fn ($classes) => $classes->modelKeys() === [$f['class']->id]);
    }

    public function test_evening_times_reject_overlap_invalid_duration_and_unauthorized_users(): void
    {
        $this->fixture('Chinese', 'Evening');
        $data = ['program' => 'Chinese', 'label' => 'Extra', 'starts_at' => '18:00', 'ends_at' => '19:00'];
        $this->postJson(route('evening-study-periods.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('starts_at');
        $data['starts_at'] = '20:00'; $data['ends_at'] = '19:00';
        $this->postJson(route('evening-study-periods.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $data['ends_at'] = '20:30';
        $this->postJson(route('evening-study-periods.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $this->actingAs(User::factory()->create(['role' => 'teacher']))->postJson(route('evening-study-periods.store'), $data)->assertForbidden();
    }

    public function test_evening_times_are_scoped_to_their_program(): void
    {
        $f = $this->fixture('Chinese', 'Evening');
        \App\Models\Program::firstOrCreate(['name' => 'English'], ['sessions' => ['Evening']]);
        $id = $this->postJson(route('evening-study-periods.store'), ['program' => 'English', 'label' => 'English late class', 'starts_at' => '19:00', 'ends_at' => '20:00'])->assertCreated()->json('id');
        $data = $this->data($f); $data['evening_period'] = $id;
        $this->post(route('classrooms.store'), $data)->assertSessionHasErrors('slots');
        $this->assertDatabaseMissing('classrooms', ['room_number' => 'NEW1']);
        $this->assertArrayNotHasKey($id, \App\Models\EveningStudyPeriod::options('Chinese'));
        $this->assertArrayHasKey($id, \App\Models\EveningStudyPeriod::options('English'));
    }

    public function test_english_teacher_can_teach_different_evening_periods(): void
    {
        $f = $this->fixture('English', 'Evening');
        foreach ([['17:30', '18:30'], ['18:30', '19:30']] as $index => [$start, $end]) {
            \App\Models\EveningStudyPeriod::create(['program' => 'English', 'label' => 'Time '.$index, 'starts_at' => $start, 'ends_at' => $end]);
        }
        $room = Classroom::create(['room_number' => 'ENG1', 'name' => 'English room', 'building' => 'A', 'capacity' => 40, 'status' => 'active']);
        $existing = new Schedule(['school_class_id' => $f['class']->id, 'subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id, 'classroom_id' => $room->id, 'room' => 'ENG1', 'day' => 'Monday', 'starts_at' => '17:30', 'ends_at' => '18:30', 'entry_type' => 'academic', 'academic_year' => '2026-2027']);
        $existing->saveQuietly();
        $other = SchoolClass::create(['name' => 'Other English class', 'section' => 'B', 'program' => 'English', 'session' => 'Evening', 'class_group_id' => $f['class']->class_group_id]);
        $lesson = new Schedule(['teacher_id' => $f['teacher']->id, 'classroom_id' => $room->id, 'day' => 'Monday', 'starts_at' => '18:30', 'ends_at' => '19:30', 'academic_year' => '2026-2027']);
        app(TeacherManagementService::class)->validateEnglishSession($f['teacher'], $lesson, $other);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $lesson->starts_at = '17:30'; $lesson->ends_at = '18:30';
        app(TeacherManagementService::class)->validateEnglishSession($f['teacher'], $lesson, $other);
    }

    public function test_inline_section_picker_supports_section_z(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->postJson(route('class-groups.store'), [
            'name' => 'Grade 7 Z', 'program' => 'Khmer', 'grade' => 7, 'section' => 'Z',
            'capacity' => 30, 'status' => 'active', 'curriculum' => 'History',
        ])->assertCreated();
        $this->assertDatabaseHas('class_groups', ['program' => 'Khmer', 'grade' => 7, 'section' => 'Z']);
        $this->get(route('classrooms.create'))->assertOk()->assertSee('data-section-picker', false)
            ->assertSee('type="radio" name="section" value="Z"', false);
    }

    private function fixture(string $program = 'Khmer', string $session = 'Morning'): array
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => $program, 'status' => 'active']);
        [$start, $end] = match ($session) {
            'Morning' => ['07:00', '11:00'], 'Afternoon' => ['13:00', '17:00'], default => ['17:30', '19:30'],
        };
        $group = ClassGroup::firstOrCreate(['grade' => 7, 'section' => 'A', 'program' => $program], ['name' => 'Grade 7', 'status' => 'active']);
        ClassSession::create(['class_group_id' => $group->id, 'session' => $session, 'starts_at' => $start, 'ends_at' => $end]);
        $class = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A', 'program' => $program, 'session' => $session, 'class_group_id' => $group->id]);
        $subject = Subject::create(['name' => $program === 'Khmer' ? 'History' : $program, 'code' => uniqid('SUB'), 'school_class_id' => $class->id, 'lesson_minutes' => 60]);
        app(TeacherManagementService::class)->saveProfile($teacher, [$subject->name], 1200, [
            ['weekday' => 'Monday', 'start_time' => $start, 'end_time' => $end, 'unavailable' => false],
        ]);
        $this->actingAs($admin);
        return compact('admin', 'teacher', 'class', 'subject', 'start', 'end');
    }

    private function data(array $f): array
    {
        return ['room_number' => 'NEW1', 'name' => 'New Room', 'building' => 'A', 'capacity' => 40, 'status' => 'active',
            'plan_enabled' => 1, 'program' => $f['class']->program, 'session' => $f['class']->session,
            'school_class_id' => $f['class']->id, 'day' => 'Monday', 'academic_year' => '2026-2027',
            'slots' => [['starts_at' => $f['start'], 'subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id]]];
    }

    public function test_weekend_lessons_save_and_weekday_choice_rejects_weekend_slots(): void
    {
        $f = $this->fixture();
        app(TeacherManagementService::class)->saveProfile($f['teacher'], [$f['subject']->name], 1200, [
            ['weekday' => 'Saturday', 'start_time' => $f['start'], 'end_time' => $f['end'], 'unavailable' => false],
        ]);
        $data = $this->data($f);
        $data['day'] = 'Saturday';
        $data['slots'][0]['day'] = 'Saturday';
        $data['class_days'] = 'weekday';
        $this->post(route('classrooms.store'), $data)->assertSessionHasErrors('slots');
        $this->assertDatabaseMissing('classrooms', ['room_number' => 'NEW1']);
        $data['class_days'] = 'weekend';
        $this->post(route('classrooms.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('schedules', ['school_class_id' => $f['class']->id, 'day' => 'Saturday']);
    }

    public function test_room_card_shows_teacher_only_during_the_current_lesson(): void
    {
        $f = $this->fixture('Chinese', 'Evening');
        $this->post(route('classrooms.store'), $this->data($f))->assertSessionHasNoErrors();
        foreach (['17:29' => false, '17:30' => true, '18:29' => true, '18:30' => false] as $time => $visible) {
            $response = $this->getJson(route('classrooms.data', ['date' => '2026-11-02', 'time' => $time]));
            $response->assertOk();
            $html = $response->json('html');
            if ($visible) {
                $this->assertStringContainsString('data-current-teacher', $html);
                $this->assertStringContainsString(e($f['teacher']->name), $html);
                $this->assertStringContainsString('17:30&ndash;18:30', $html);
            } else {
                $this->assertStringNotContainsString('data-current-teacher', $html);
            }
        }
    }

    public function test_new_building_is_saved_and_appears_in_room_form_and_dashboard_filter(): void
    {
        $f = $this->fixture();
        $data = $this->data($f);
        $data['plan_enabled'] = 0;
        $data['building'] = '__new';
        $data['new_building_name'] = 'Science Building';
        $this->post(route('classrooms.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $building = Building::where('name', 'Science Building')->firstOrFail();
        $this->assertDatabaseHas('classrooms', ['room_number' => 'NEW1', 'building' => $building->code]);
        $this->get(route('classrooms.create'))->assertOk()->assertSee('Science Building')->assertSee('Program & lesson setup', false);
        $this->get(route('classrooms.index', ['building' => $building->code]))->assertOk()->assertSee('NEW1')->assertSee('Science Building');
    }

    public function test_room_and_hourly_lessons_are_saved_for_each_program_and_session(): void
    {
        foreach ([['Khmer', 'Morning'], ['Khmer', 'Afternoon'], ['English', 'Morning'], ['English', 'Afternoon'], ['English', 'Evening'], ['Chinese', 'Evening']] as $index => [$program, $session]) {
            $f = $this->fixture($program, $session);
            $data = $this->data($f);
            $data['room_number'] = 'NEW'.$index;
            $this->post(route('classrooms.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
            $room = Classroom::where('room_number', $data['room_number'])->firstOrFail();
            $this->assertDatabaseHas('schedules', ['classroom_id' => $room->id, 'school_class_id' => $f['class']->id, 'subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id, 'starts_at' => $f['start']]);
        }
    }

    public function test_second_slot_conflict_rolls_back_new_building_room_and_first_lesson(): void
    {
        $f = $this->fixture();
        $data = $this->data($f);
        $data['building'] = '__new';
        $data['new_building_name'] = 'Rollback Building';
        $data['slots'][] = $data['slots'][0];
        $this->post(route('classrooms.store'), $data)->assertSessionHasErrors('slots.1.teacher_id');
        $this->assertDatabaseMissing('buildings', ['name' => 'Rollback Building']);
        $this->assertDatabaseMissing('classrooms', ['room_number' => 'NEW1']);
        $this->assertSame(0, Schedule::count());
    }

    public function test_lessons_for_multiple_weekdays_are_saved_together(): void
    {
        $f = $this->fixture();
        app(TeacherManagementService::class)->saveProfile($f['teacher'], ['History'], 1200, [
            ['weekday' => 'Monday', 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false],
            ['weekday' => 'Tuesday', 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false],
        ]);
        $data = $this->data($f);
        $data['slots'][0]['day'] = 'Monday';
        $data['slots'][] = array_replace($data['slots'][0], ['day' => 'Tuesday']);
        $this->post(route('classrooms.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        foreach (['Monday', 'Tuesday'] as $day) {
            $this->assertDatabaseHas('schedules', ['subject_id' => $f['subject']->id, 'teacher_id' => $f['teacher']->id, 'day' => $day]);
        }
        $this->assertSame(2, Schedule::count());
    }

    public function test_creating_class_without_students_makes_curriculum_available_for_planning(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        \App\Models\Program::firstOrCreate(['name' => 'Japan'], ['sessions' => ['Evening']]);
        $this->post(route('class-groups.store'), [
            'name' => 'Japanese Beginners', 'program' => 'Japan', 'grade' => 1,
            'capacity' => 30, 'status' => 'active',
            'curriculum' => "Japanese Writing\nJapanese Reading",
        ])->assertSessionHasNoErrors()->assertRedirect();
        $class = SchoolClass::where('program', 'Japan')->where('session', 'Evening')->firstOrFail();
        $this->assertSame(0, $class->students()->count());
        $this->assertEqualsCanonicalizing(['Japanese Writing', 'Japanese Reading'], $class->subjects()->pluck('name')->all());
        $this->get(route('classrooms.create'))->assertOk()->assertSee('Japanese Writing')->assertSee('Japanese Beginners');
        $this->get(route('students.create'))->assertOk()->assertSee('Japanese Beginners')->assertSee('data-program="Japan"', false);
    }

    public function test_inline_class_creation_returns_the_new_class_and_curriculum_without_redirecting(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        \App\Models\Program::firstOrCreate(['name' => 'Japan'], ['sessions' => ['Evening']]);
        $this->postJson(route('class-groups.store'), [
            'name' => 'Japan Beginners', 'program' => 'Japan', 'grade' => 1, 'capacity' => 30,
            'status' => 'active', 'curriculum' => "Japanese Writing\nJapanese Vocabulary",
        ])->assertCreated()->assertJsonCount(1, 'classes')->assertJsonPath('classes.0.program', 'Japan')
            ->assertJsonPath('classes.0.session', 'Evening')->assertJsonCount(2, 'classes.0.subjects');
        $this->assertDatabaseHas('school_classes', ['name' => 'Japan Beginners', 'program' => 'Japan']);
    }

    public function test_duplicate_class_name_in_another_grade_returns_validation_instead_of_database_error(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        \App\Models\Program::firstOrCreate(['name' => 'Japan'], ['sessions' => ['Evening']]);
        $data = ['name' => 'C03', 'program' => 'Japan', 'grade' => 3, 'capacity' => 30, 'status' => 'active'];
        $this->postJson(route('class-groups.store'), $data)->assertCreated();
        $data['grade'] = 4;
        $this->postJson(route('class-groups.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertSame(1, ClassGroup::where('name', 'C03')->count());
        $this->assertSame(1, SchoolClass::where('name', 'C03')->count());
    }

    public function test_teacher_options_only_include_the_selected_program(): void
    {
        $f = $this->fixture();
        $other = User::factory()->create(['role' => 'teacher', 'program' => 'English', 'status' => 'active']);
        $response = $this->getJson(route('classrooms.setup-options', [
            'school_class_id' => $f['class']->id, 'subject_id' => $f['subject']->id,
            'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'academic_year' => '2026-2027',
        ]))->assertOk();
        $ids = collect($response->json('teachers'))->pluck('id')->all();
        $this->assertContains($f['teacher']->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_japanese_class_uses_one_teacher_for_all_planned_weekdays(): void
    {
        \App\Models\Program::firstOrCreate(['name' => 'Japan'], ['sessions' => ['Evening']]);
        $f = $this->fixture('Japan', 'Evening');
        $secondSubject = Subject::create(['name' => 'Japanese Writing', 'code' => 'JP-WRITING', 'school_class_id' => $f['class']->id]);
        app(TeacherManagementService::class)->saveProfile($f['teacher'], ['Japan', 'Japanese Writing'], 1200, [
            ['weekday' => 'Monday', 'start_time' => $f['start'], 'end_time' => $f['end'], 'unavailable' => false],
            ['weekday' => 'Tuesday', 'start_time' => $f['start'], 'end_time' => $f['end'], 'unavailable' => false],
        ]);
        $other = User::factory()->create(['role' => 'teacher', 'program' => 'Japan', 'status' => 'active']);
        $data = $this->data($f);
        $data['class_teacher_id'] = $f['teacher']->id;
        $data['slots'][0]['day'] = 'Monday';
        $data['slots'][0]['teacher_id'] = $other->id;
        $data['slots'][] = array_replace($data['slots'][0], ['day' => 'Tuesday', 'subject_id' => $secondSubject->id]);
        $this->post(route('classrooms.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([$f['teacher']->id], Schedule::pluck('teacher_id')->unique()->values()->all());
        $this->assertSame($f['teacher']->id, $f['class']->fresh()->teacher_id);
        $this->assertSame(2, Schedule::count());
        $this->getJson(route('classrooms.setup-options', [
            'school_class_id' => $f['class']->id, 'subject_id' => $f['subject']->id,
            'day' => 'Wednesday', 'starts_at' => $f['start'], 'ends_at' => $f['end'], 'academic_year' => '2026-2027',
        ]))->assertOk()->assertJsonCount(1, 'teachers')->assertJsonPath('teachers.0.id', $f['teacher']->id);
    }

    public function test_japanese_teacher_cannot_be_assigned_to_a_second_class(): void
    {
        \App\Models\Program::firstOrCreate(['name' => 'Japan'], ['sessions' => ['Evening']]);
        $f = $this->fixture('Japan', 'Evening');
        SchoolClass::create(['name' => 'Another class', 'section' => '', 'program' => 'Japan', 'session' => 'Evening', 'teacher_id' => $f['teacher']->id]);
        $data = $this->data($f);
        $data['class_teacher_id'] = $f['teacher']->id;
        $this->post(route('classrooms.store'), $data)->assertSessionHasErrors('slots');
        $this->assertDatabaseMissing('classrooms', ['room_number' => 'NEW1']);
        $this->assertSame(0, Schedule::count());
    }

    public function test_japan_teachers_do_not_receive_designated_free_periods(): void
    {
        \App\Models\Program::firstOrCreate(['name' => 'Japan'], ['sessions' => ['Evening']]);
        $f = $this->fixture('Japan', 'Evening');
        $rules = app(TeacherManagementService::class);
        $rules->saveProfile($f['teacher'], ['Japan'], 1200, collect(TeacherManagementService::DAYS)->map(fn ($day) => [
            'weekday' => $day, 'start_time' => $f['start'], 'end_time' => $f['end'], 'unavailable' => false,
        ])->all());
        $this->assertFalse($rules->eligibleForBreaks($f['teacher']));
        $data = $this->data($f);
        $data['class_teacher_id'] = $f['teacher']->id;
        $this->post(route('classrooms.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertCount(0, $rules->breaks($f['teacher']));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $rules->proposeBreaks($f['teacher']);
    }

    public function test_japan_weekly_plan_rejects_reusing_a_subject_on_another_day(): void
    {
        \App\Models\Program::firstOrCreate(['name' => 'Japan'], ['sessions' => ['Evening']]);
        $f = $this->fixture('Japan', 'Evening');
        $data = $this->data($f);
        $data['class_teacher_id'] = $f['teacher']->id;
        $data['slots'][0]['day'] = 'Monday';
        $data['slots'][] = array_replace($data['slots'][0], ['day' => 'Tuesday']);
        $this->post(route('classrooms.store'), $data)->assertSessionHasErrors('slots');
        $this->assertDatabaseMissing('classrooms', ['room_number' => 'NEW1']);
        $this->assertSame(0, Schedule::count());
    }

    public function test_editing_existing_room_does_not_count_its_own_lesson_as_busy_or_extra_workload(): void
    {
        $f = $this->fixture();
        $data = $this->data($f);
        $this->post(route('classrooms.store'), $data)->assertSessionHasNoErrors();
        $room = Classroom::where('room_number', 'NEW1')->firstOrFail();
        \Illuminate\Support\Facades\DB::table('teacher_profiles')->where('teacher_id', $f['teacher']->id)->update(['weekly_minutes' => 60]);
        $query = ['school_class_id' => $f['class']->id, 'subject_id' => $f['subject']->id, 'day' => 'Monday',
            'starts_at' => '07:00', 'ends_at' => '08:00', 'academic_year' => '2026-2027'];
        $rows = collect($this->getJson(route('classrooms.setup-options', $query))->assertOk()->json('teachers'));
        $this->assertFalse($rows->firstWhere('id', $f['teacher']->id)['available']);
        $query['classroom_id'] = $room->id;
        $rows = collect($this->getJson(route('classrooms.setup-options', $query))->assertOk()->json('teachers'));
        $this->assertTrue($rows->firstWhere('id', $f['teacher']->id)['available']);
        $this->put(route('classrooms.update', $room), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, Schedule::count());
        $this->assertSame(1, Classroom::count());
    }

    public function test_program_filter_includes_rooms_with_lessons_without_reservations(): void
    {
        $f = $this->fixture();
        $this->post(route('classrooms.store'), $this->data($f))->assertSessionHasNoErrors();
        $service = app(\App\Services\ClassroomAvailabilityService::class);
        foreach (['06:00' => 'upcoming', '07:30' => 'busy', '12:00' => 'available'] as $time => $status) {
            $rooms = $service->forMoment('2026-11-02 '.$time, ['program' => 'Khmer', 'session' => 'Morning', 'grade' => 7]);
            $this->assertSame(['NEW1'], $rooms->pluck('classroom.room_number')->all());
            $this->assertSame($status, $rooms->first()['status']);
        }
        $this->assertCount(0, $service->forMoment('2026-11-02 07:30', ['program' => 'English']));
        $this->assertCount(0, $service->forMoment('2026-11-02 07:30', ['program' => 'Khmer', 'grade' => 8]));
    }

    public function test_room_program_details_count_active_students_once_across_weekday_lessons(): void
    {
        $f = $this->fixture();
        app(TeacherManagementService::class)->saveProfile($f['teacher'], ['History'], 1200, [
            ['weekday' => 'Monday', 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false],
            ['weekday' => 'Tuesday', 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false],
        ]);
        $this->post(route('classrooms.store'), $this->data($f))->assertSessionHasNoErrors();
        \App\Models\Student::factory()->count(2)->create(['school_class_id' => $f['class']->id, 'status' => 'active']);
        \App\Models\Student::factory()->create(['school_class_id' => $f['class']->id, 'status' => 'inactive']);
        $lesson = Schedule::firstOrFail()->replicate();
        $lesson->day = 'Tuesday';
        $lesson->saveQuietly();
        $rooms = app(\App\Services\ClassroomAvailabilityService::class)->forMoment('2026-11-07 12:00');
        $details = $rooms->first()['programDetails']['Khmer'];
        $this->assertSame(2, $details['students']);
        $this->assertCount(1, $details['classes']);
        $this->get(route('classrooms.index'))->assertOk()->assertSee('data-room-program="Khmer"', false)->assertSee('data-enrolled-count="2"', false);
    }

    public function test_teacher_options_include_busy_and_free_teachers_with_real_schedule_data(): void
    {
        $f = $this->fixture();
        $busy = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer', 'status' => 'active']);
        app(TeacherManagementService::class)->saveProfile($busy, ['History'], 1200, [['weekday' => 'Monday', 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false]]);
        (new Schedule(['subject_id' => $f['subject']->id, 'school_class_id' => $f['class']->id, 'teacher_id' => $busy->id,
            'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'room' => 'BUSY', 'entry_type' => 'academic', 'academic_year' => '2026-2027']))->saveQuietly();
        $response = $this->getJson(route('classrooms.setup-options', ['school_class_id' => $f['class']->id, 'subject_id' => $f['subject']->id, 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'academic_year' => '2026-2027']))->assertOk();
        $rows = collect($response->json('teachers'));
        $this->assertTrue($rows->firstWhere('id', $f['teacher']->id)['available']);
        $busyRow = $rows->firstWhere('id', $busy->id);
        $this->assertFalse($busyRow['available']);
        $this->assertContains('Busy teaching', $busyRow['reasons']);
        $this->assertSame('History', $busyRow['lessons'][0]['subject']);
        $this->assertSame('07:00', $busyRow['working'][0]['start']);
    }

    public function test_tampered_program_or_subject_cannot_create_room_or_lesson(): void
    {
        $f = $this->fixture();
        $data = $this->data($f);
        $data['program'] = 'Chinese';
        $this->post(route('classrooms.store'), $data)->assertSessionHasErrors('slots');
        $this->assertDatabaseMissing('classrooms', ['room_number' => 'NEW1']);
    }

    public function test_non_admin_cannot_read_teacher_options_or_create_rooms(): void
    {
        $f = $this->fixture();
        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->getJson(route('classrooms.setup-options'))->assertForbidden();
        $this->post(route('classrooms.store'), $this->data($f))->assertForbidden();
    }

    public function test_break_and_off_duty_teachers_are_not_assignable(): void
    {
        $f = $this->fixture();
        $rules = app(TeacherManagementService::class);
        $periods = collect($rules::DAYS)->map(fn ($day) => ['weekday' => $day, 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false])->all();
        $rules->saveProfile($f['teacher'], ['History'], 1200, $periods);
        $rules->saveBreaks($f['teacher'], [
            ['weekday' => 'Monday', 'start_time' => '07:00', 'end_time' => '08:00'],
            ['weekday' => 'Wednesday', 'start_time' => '07:00', 'end_time' => '08:00'],
            ['weekday' => 'Friday', 'start_time' => '07:00', 'end_time' => '08:00'],
        ]);
        $params = ['school_class_id' => $f['class']->id, 'subject_id' => $f['subject']->id, 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'academic_year' => '2026-2027'];
        $rows = collect($this->getJson(route('classrooms.setup-options', $params))->assertOk()->json('teachers'));
        $row = $rows->firstWhere('id', $f['teacher']->id);
        $this->assertFalse($row['available']);
        $this->assertContains('Scheduled break', $row['reasons']);
        $this->assertCount(3, $row['breaks']);
        $this->post(route('classrooms.store'), $this->data($f))->assertSessionHasErrors('slots.0.teacher_id');
        $this->assertDatabaseMissing('classrooms', ['room_number' => 'NEW1']);
        $params['starts_at'] = '13:00'; $params['ends_at'] = '14:00';
        $row = collect($this->getJson(route('classrooms.setup-options', $params))->assertOk()->json('teachers'))->firstWhere('id', $f['teacher']->id);
        $this->assertFalse($row['available']);
        $this->assertContains('Outside working hours or unavailable', $row['reasons']);
    }

    public function test_khmer_mathematics_saves_as_a_consecutive_two_hour_block(): void
    {
        $f = $this->fixture();
        $f['subject']->update(['name' => 'Mathematics']);
        app(TeacherManagementService::class)->saveProfile($f['teacher'], ['Mathematics'], 1200, [['weekday' => 'Monday', 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false]]);
        $this->post(route('classrooms.store'), $this->data($f))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('schedules', ['subject_id' => $f['subject']->id, 'starts_at' => '07:00', 'ends_at' => '09:00']);
    }
}
