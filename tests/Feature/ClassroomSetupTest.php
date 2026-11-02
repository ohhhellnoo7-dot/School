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
