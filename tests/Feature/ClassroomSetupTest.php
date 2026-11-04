<?php

namespace Tests\Feature;

use App\Models\{Building, ClassGroup, ClassSession, Classroom, Schedule, SchoolClass, Subject, User};
use App\Services\TeacherManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomSetupTest extends TestCase
{
    use RefreshDatabase;

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
