<?php

namespace Tests\Feature;

use App\Models\ClassAttendance;
use App\Models\Classroom;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $room = Classroom::create(['room_number' => '10B', 'name' => 'Room 10B', 'capacity' => 40, 'status' => 'active']);
        $classes = [];
        foreach (['Morning', 'Afternoon'] as $session) {
            $class = SchoolClass::create(['name' => 'Grade 10', 'section' => $session, 'program' => 'Khmer', 'session' => $session, 'teacher_id' => $teacher->id]);
            $subject = Subject::create(['name' => 'Mathematics '.$session, 'code' => 'MATH-'.$session, 'school_class_id' => $class->id, 'teacher_id' => $teacher->id]);
            DB::table('schedules')->insert(['school_class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
                'classroom_id' => $room->id, 'room' => $room->room_number, 'day' => 'Monday', 'starts_at' => $session === 'Morning' ? '07:00' : '13:00',
                'ends_at' => $session === 'Morning' ? '08:00' : '14:00', 'entry_type' => 'academic', 'academic_year' => '2026-2027']);
            $classes[$session] = $class;
        }
        $student = Student::factory()->create(['school_class_id' => $classes['Morning']->id]);
        $student->studyClasses()->attach($classes['Afternoon']->id);

        return [$admin, $teacher, $room, $classes, $student];
    }

    public function test_class_records_are_separate_and_monthly_sheet_follows_program_and_session(): void
    {
        [$admin, $teacher, $room, $classes, $student] = $this->fixture();
        $this->actingAs($admin);
        foreach (['Morning' => 'present', 'Afternoon' => 'absent'] as $session => $status) {
            $this->post(route('classrooms.attendance.store', $room), [
                'school_class_id' => $classes[$session]->id, 'attendance_date' => '2026-11-09',
                'statuses' => [$student->id => $status],
            ])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseCount('class_attendances', 2);
        $this->assertDatabaseCount('attendances', 0);
        $response = $this->get(route('classrooms.show', ['classroom' => $room, 'date' => '2026-11-09', 'program' => 'Khmer', 'session' => 'Morning', 'attendance_month' => '2026-11']));
        $response->assertOk()->assertSee('Student attendance')->assertSee('Mathematics Morning')->assertDontSee('Mathematics Afternoon')
            ->assertSee('Yes — enrolled')->assertViewHas('classAttendance', fn ($rows) => $rows->count() === 1 && $rows->first()->status === 'present');
        $this->get(route('classrooms.show', ['classroom' => $room, 'date' => '2026-11-09', 'program' => 'Khmer', 'session' => 'Afternoon']))
            ->assertOk()->assertViewHas('classAttendance', fn ($rows) => $rows->first()->status === 'absent');
        $this->get(route('classrooms.show', ['classroom' => $room, 'attendance_month' => '2028-02']))
            ->assertOk()->assertViewHas('attendanceMonth', fn ($month) => $month->daysInMonth === 29);
        $this->post(route('classrooms.attendance.store', $room), ['school_class_id' => $classes['Morning']->id,
            'attendance_date' => '2026-11-09', 'statuses' => [$student->id => 'late']])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('class_attendances', 2);
        $this->post(route('classrooms.attendance.store', $room), ['school_class_id' => $classes['Morning']->id,
            'attendance_date' => '2026-11-09', 'statuses' => [$student->id => '']])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('class_attendances', 1);
    }

    public function test_writes_reject_unenrolled_students_other_rooms_and_unauthorized_users(): void
    {
        [$admin, $teacher, $room, $classes, $student] = $this->fixture();
        $other = SchoolClass::create(['name' => 'Grade 11', 'section' => 'C', 'program' => 'Khmer', 'session' => 'Morning']);
        $outsider = Student::factory()->create(['school_class_id' => $other->id]);
        $payload = ['school_class_id' => $classes['Morning']->id, 'attendance_date' => '2026-11-09', 'statuses' => [$student->id => 'present']];
        $this->actingAs($admin)->post(route('classrooms.attendance.store', $room), array_replace($payload, ['statuses' => [$student->id => 'present', $outsider->id => 'absent']]))
            ->assertSessionHasErrors('statuses');
        $this->assertDatabaseCount('class_attendances', 0);
        $this->post(route('classrooms.attendance.store', $room), array_replace($payload, ['school_class_id' => $other->id]))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'parent']))->post(route('classrooms.attendance.store', $room), $payload)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'teacher']))->post(route('classrooms.attendance.store', $room), $payload)->assertForbidden();
        $this->actingAs($teacher)->post(route('classrooms.attendance.store', $room), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('class_attendances', 1);
    }
}
