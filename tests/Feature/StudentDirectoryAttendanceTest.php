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
            ->assertSee('Attendance date')->assertSee('Not Marked')->assertSee('Excused');
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

    public function test_summary_counts_span_pages_and_ignore_status_tab_but_follow_other_filters(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $students = Student::factory()->count(15)->create(['name' => 'Matching Student']);
        foreach ($students as $index => $student) {
            if ($index < 12) Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => ['present', 'absent', 'late', 'excused'][$index % 4]]);
        }
        $other = Student::factory()->create(['name' => 'Other Person']);
        Attendance::create(['student_id' => $other->id, 'date' => today(), 'status' => 'present']);
        $expected = ['total' => 15, 'present' => 3, 'absent' => 3, 'late' => 3, 'excused' => 3, 'unrecorded' => 3];
        $this->actingAs($admin)->get(route('students.index', ['search' => 'Matching Student', 'page' => 2]))
            ->assertOk()->assertViewHas('attendanceCounts', $expected)
            ->assertViewHas('students', fn ($rows) => $rows->count() === 3 && $rows->total() === 15);
        $this->get(route('students.index', ['search' => 'Matching Student', 'attendance_status' => 'present']))
            ->assertOk()->assertViewHas('attendanceCounts', $expected)
            ->assertViewHas('students', fn ($rows) => $rows->total() === 3);
        $this->get(route('students.index', ['search' => 'Matching Student', 'attendance_status' => 'unrecorded']))
            ->assertOk()->assertViewHas('attendanceCounts', $expected)
            ->assertViewHas('students', fn ($rows) => $rows->total() === 3 && $rows->every(fn ($s) => $s->attendances->isEmpty()));
        $this->get(route('students.index', ['search' => 'Matching Student', 'attendance_date' => today()->subDay()->toDateString()]))
            ->assertOk()->assertViewHas('attendanceCounts', ['total' => 15, 'present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0, 'unrecorded' => 15]);
    }

    public function test_attendance_summary_respects_parent_visibility(): void
    {
        $parent = User::factory()->create(['role' => 'parent']);
        $child = Student::factory()->create(['parent_id' => $parent->id]);
        $other = Student::factory()->create();
        foreach ([$child, $other] as $student) Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'present']);
        $this->actingAs($parent)->get(route('students.index'))->assertOk()
            ->assertViewHas('attendanceCounts', ['total' => 1, 'present' => 1, 'absent' => 0, 'late' => 0, 'excused' => 0, 'unrecorded' => 0]);
    }
}
