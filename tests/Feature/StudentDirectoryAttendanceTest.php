<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentDirectoryAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_can_save_all_four_statuses_without_duplicate_daily_records(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $student = Student::factory()->create();
        $yesterday = today()->subDay()->toDateString();
        Attendance::create(['student_id' => $student->id, 'date' => $yesterday, 'status' => 'absent', 'notes' => 'Existing note']);
        $this->actingAs($admin)->get(route('students.index'))->assertOk()
            ->assertSee('Attendance date')->assertSee('Not recorded')->assertSee('Excused');
        foreach (['present', 'absent', 'late', 'excused'] as $status) {
            $this->postJson(route('students.attendance.store', $student), ['date' => today()->toDateString(), 'status' => $status])
                ->assertOk()->assertJsonPath('status', $status)->assertHeaderMissing('Location');
            $this->assertSame($status, Attendance::where('student_id', $student->id)->whereDate('date', today())->firstOrFail()->status);
        }
        $this->assertDatabaseCount('attendances', 2);
        $this->get(route('students.index', ['attendance_date' => $yesterday]))->assertOk()
            ->assertViewHas('students', fn ($students) => $students->first()->attendances->first()->status === 'absent');
        $this->postJson(route('students.attendance.store', $student), ['date' => $yesterday, 'status' => 'late'])->assertOk();
        $record = Attendance::where('student_id', $student->id)->whereDate('date', $yesterday)->firstOrFail();
        $this->assertSame('late', $record->status);
        $this->assertSame('Existing note', $record->notes);
        $this->get(route('students.index'))->assertViewHas('students', fn ($students) => $students->first()->attendances->first()->status === 'excused');
    }

    public function test_attendance_filter_matches_status_on_the_selected_date_and_combines_with_name_search(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $students = [];
        foreach (['present', 'absent', 'late', 'excused'] as $status) {
            $student = Student::factory()->create(['name' => 'Student '.$status]);
            $students[$status] = $student;
            Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => $status]);
        }
        $unrecorded = Student::factory()->create();
        Attendance::create(['student_id' => $students['present']->id, 'date' => today()->subDay(), 'status' => 'absent']);
        $this->actingAs($admin);
        foreach ($students as $status => $student) {
            $this->get(route('students.index', ['attendance_status' => $status]))->assertOk()
                ->assertViewHas('students', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $student->id);
        }
        $this->get(route('students.index', ['attendance_status' => 'absent', 'attendance_date' => today()->subDay()->toDateString()]))
            ->assertOk()->assertViewHas('students', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $students['present']->id);
        $this->get(route('students.index', ['attendance_status' => 'absent', 'search' => 'Student present']))
            ->assertOk()->assertViewHas('students', fn ($rows) => $rows->total() === 0);
        $this->get(route('students.index'))->assertViewHas('students', fn ($rows) => $rows->total() === 5);
        $this->getJson(route('students.index', ['attendance_status' => 'invalid']))
            ->assertUnprocessable()->assertJsonValidationErrors('attendance_status');
    }

    public function test_attendance_writes_require_admin_and_validate_status_and_date(): void
    {
        $student = Student::factory()->create();
        foreach (['teacher', 'parent', 'student'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->postJson(route('students.attendance.store', $student), ['date' => today()->toDateString(), 'status' => 'present'])->assertForbidden();
        }
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->postJson(route('students.attendance.store', $student), ['date' => today()->toDateString(), 'status' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson(route('students.attendance.store', $student), ['date' => today()->addDay()->toDateString(), 'status' => 'present'])
            ->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->getJson(route('students.index', ['attendance_date' => 'invalid']))->assertUnprocessable();
        $this->assertDatabaseCount('attendances', 0);
    }
}
