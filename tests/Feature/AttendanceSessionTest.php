<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_filters_students_teachers_live_reports_and_bulk_actions(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teachers = [];
        $students = [];
        foreach (['Morning', 'Afternoon', 'Evening'] as $session) {
            $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
            $class = SchoolClass::create(['name' => $session, 'section' => 'A', 'session' => $session, 'teacher_id' => $teacher->id]);
            $student = Student::factory()->create(['school_class_id' => $class->id]);
            Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'absent']);
            TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => today(), 'status' => 'absent', 'recorded_by' => $admin->id]);
            $teachers[$session] = $teacher;
            $students[$session] = $student;
        }
        // One person can belong to multiple sessions without inflating the all-session count.
        $students['Morning']->studyClasses()->attach($students['Afternoon']->school_class_id);
        $this->actingAs($admin)->getJson('/dashboard/data')->assertOk()
            ->assertJsonPath('summary.attendanceCount', 3)->assertJsonPath('charts.teacherAttendance', [0, 3, 0, 0]);
        foreach (['Morning' => 1, 'Afternoon' => 2, 'Evening' => 1] as $session => $studentCount) {
            $this->get('/dashboard?session='.$session)->assertOk()->assertSee('All sessions')->assertSee('value="'.$session.'" selected', false);
            $this->getJson('/dashboard/data?session='.$session)->assertOk()
                ->assertJsonPath('summary.attendanceCount', $studentCount)
                ->assertJsonPath('charts.attendance', [0, $studentCount, 0, 0])
                ->assertJsonPath('charts.teacherAttendance', [0, 1, 0, 0]);
            $this->get('/teacher-attendance?session='.$session)->assertOk()->assertViewHas('total', 1);
        }
        $this->post(route('dashboard.attendance.all-present'), ['session' => 'Evening'])->assertRedirect(route('dashboard', ['session' => 'Evening']));
        $this->assertSame(1, Attendance::where('status', 'present')->count());
        $this->post(route('dashboard.teacher-attendance.bulk', 'all-present'), ['session' => 'Morning'])->assertRedirect(route('dashboard', ['session' => 'Morning']));
        $this->assertSame(1, TeacherAttendance::where('status', 'present')->count());
        $this->post(route('dashboard.attendance.random'), ['session' => 'Evening'])->assertRedirect();
        $this->post(route('dashboard.teacher-attendance.bulk', 'random'), ['session' => 'Morning'])->assertRedirect();
        $this->assertSame(2, Attendance::where('status', 'absent')->count());
        $this->assertSame(2, TeacherAttendance::where('status', 'absent')->count());
        $this->getJson('/dashboard/data?session=Invalid')->assertUnprocessable();
        $this->postJson(route('dashboard.teacher-attendance.bulk', 'all-present'), ['session' => 'Invalid'])->assertUnprocessable();
    }
}
