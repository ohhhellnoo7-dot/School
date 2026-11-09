<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendancePeopleTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_lists_respect_date_session_and_account_access(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $parent = User::factory()->create(['role' => 'parent']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $morning = SchoolClass::create(['name' => 'Morning class', 'section' => 'A', 'session' => 'Morning', 'teacher_id' => $teacher->id]);
        $evening = SchoolClass::create(['name' => 'Evening class', 'section' => 'A', 'session' => 'Evening', 'teacher_id' => $otherTeacher->id]);
        $child = Student::factory()->create(['name' => 'My absent child', 'parent_id' => $parent->id, 'school_class_id' => $morning->id]);
        $private = Student::factory()->create(['name' => 'Private absent student', 'school_class_id' => $evening->id]);
        foreach ([$child, $private] as $student) {
            Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'absent']);
        }
        foreach ([$teacher, $otherTeacher] as $person) {
            TeacherAttendance::create(['teacher_id' => $person->id, 'date' => today(), 'status' => 'absent', 'recorded_by' => $admin->id]);
        }
        $url = route('attendance.people', ['kind' => 'students', 'status' => 'absent', 'session' => 'Morning']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('My absent child')->assertDontSee('Private absent student');
        foreach (['present', 'late', 'excused'] as $status) {
            $this->get(route('attendance.people', ['kind' => 'students', 'status' => $status]))->assertOk()->assertDontSee('My absent child');
        }
        $this->get($url.'&date='.today()->subDay()->toDateString())->assertOk()->assertDontSee('My absent child');
        $this->actingAs($parent)->get(route('attendance.people', ['kind' => 'students', 'status' => 'absent']))->assertOk()->assertSee('My absent child')->assertDontSee('Private absent student');
        $teachersUrl = route('attendance.people', ['kind' => 'teachers', 'status' => 'absent']);
        $this->get($teachersUrl)->assertForbidden();
        $this->actingAs($teacher)->get($teachersUrl)->assertOk()->assertSee($teacher->name)->assertDontSee($otherTeacher->name);
        $this->actingAs($admin)->get($teachersUrl.'?session=Morning')->assertOk()->assertSee($teacher->name)->assertDontSee($otherTeacher->name);
        $this->get('/dashboard')->assertOk()->assertSee('hover-menus-toggle')->assertSee('View absent teachers');
    }
}
