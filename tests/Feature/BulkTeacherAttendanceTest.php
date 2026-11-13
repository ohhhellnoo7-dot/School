<?php

namespace Tests\Feature;

use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkTeacherAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_absent_without_a_scan_is_marked_present(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => today(), 'status' => 'absent', 'recorded_by' => $teacher->id]);
        \App\Models\TeacherSessionAttendance::create(['teacher_id' => $teacher->id, 'date' => today(), 'session' => 'Morning', 'status' => 'absent']);

        $this->actingAs($admin)->post(route('dashboard.teacher-attendance.bulk', 'all-present'))->assertRedirect();

        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teacher->id, 'status' => 'present']);
        $this->assertDatabaseHas('teacher_session_attendances', ['teacher_id' => $teacher->id, 'session' => 'Morning', 'status' => 'present', 'scanned_at' => null]);
    }

    public function test_session_bulk_preserves_scans_and_does_not_mark_other_sessions_present(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        foreach (['Morning', 'Afternoon'] as $session) {
            \App\Models\SchoolClass::create(['name' => $session, 'section' => 'A', 'session' => $session, 'teacher_id' => $teacher->id]);
            \App\Models\TeacherSessionAttendance::create(['teacher_id' => $teacher->id, 'date' => today(), 'session' => $session, 'status' => $session === 'Morning' ? 'late' : 'absent', 'scanned_at' => $session === 'Morning' ? now() : null]);
        }
        TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => today(), 'status' => 'absent', 'recorded_by' => $teacher->id]);
        $this->actingAs($admin)->post(route('dashboard.teacher-attendance.bulk', 'all-present'), ['session' => 'Morning'])->assertRedirect();
        $this->assertDatabaseHas('teacher_session_attendances', ['teacher_id' => $teacher->id, 'session' => 'Afternoon', 'status' => 'absent']);
        $this->post(route('dashboard.teacher-attendance.bulk', 'all-present'), ['session' => 'Afternoon'])->assertRedirect();
        $this->assertDatabaseHas('teacher_session_attendances', ['teacher_id' => $teacher->id, 'session' => 'Morning', 'status' => 'late']);
        $this->assertDatabaseHas('teacher_session_attendances', ['teacher_id' => $teacher->id, 'session' => 'Afternoon', 'status' => 'present', 'scanned_at' => null]);
        $this->get('/teacher-attendance?session=Afternoon')->assertViewHas('counts', fn ($counts) => $counts->get('present') === 1);
        $this->get('/teacher-attendance?session=Morning')->assertViewHas('counts', fn ($counts) => $counts->get('late') === 1);
    }

    public function test_mark_all_present_preserves_self_scans_and_marks_unscanned_teachers(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $late = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $present = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $unscanned = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        foreach ([$late->id => 'late', $present->id => 'present'] as $teacherId => $status) {
            TeacherAttendance::create(['teacher_id' => $teacherId, 'date' => today(), 'status' => $status, 'recorded_by' => $teacherId]);
            \App\Models\TeacherSessionAttendance::create(['teacher_id' => $teacherId, 'date' => today(), 'session' => 'Morning', 'status' => $status, 'scanned_at' => now()]);
        }
        foreach (range(1, 2) as $attempt) {
            $this->actingAs($admin)->post(route('dashboard.teacher-attendance.bulk', 'all-present'))->assertRedirect();
            $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $late->id, 'status' => 'late', 'recorded_by' => $late->id]);
            $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $present->id, 'status' => 'present', 'recorded_by' => $present->id]);
            $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $unscanned->id, 'status' => 'present', 'recorded_by' => $admin->id]);
            $this->assertDatabaseHas('teacher_session_attendances', ['teacher_id' => $late->id, 'status' => 'late']);
        }
    }

    public function test_bulk_actions_update_active_teachers_and_preserve_history_and_notes(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teachers = User::factory()->count(20)->create(['role' => 'teacher', 'status' => 'active']);
        $inactive = User::factory()->create(['role' => 'teacher', 'status' => 'inactive']);
        TeacherAttendance::create(['teacher_id' => $teachers[0]->id, 'date' => today()->subDay(), 'status' => 'absent', 'recorded_by' => $admin->id]);
        TeacherAttendance::create(['teacher_id' => $teachers[0]->id, 'date' => today(), 'status' => 'late', 'notes' => 'Keep this note', 'recorded_by' => $admin->id]);
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Teacher attendance');
        $this->getJson('/dashboard/data')->assertOk()->assertSee('Teacher attendance');
        foreach (range(1, 2) as $attempt) {
            $this->post(route('dashboard.teacher-attendance.bulk', 'random'))->assertRedirect(route('dashboard'));
            $records = TeacherAttendance::whereDate('date', today())->get();
            $this->assertCount(20, $records);
            $this->assertEquals(['present' => 14, 'late' => 3, 'absent' => 2, 'excused' => 1], $records->countBy('status')->all());
        }
        foreach (range(1, 2) as $attempt) {
            $this->post(route('dashboard.teacher-attendance.bulk', 'all-present'))->assertRedirect(route('dashboard'));
            $this->assertSame(20, TeacherAttendance::whereDate('date', today())->where('status', 'present')->count());
        }
        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teachers[0]->id, 'notes' => 'Keep this note', 'recorded_by' => $admin->id]);
        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teachers[0]->id, 'date' => today()->subDay()->toDateTimeString(), 'status' => 'absent', 'recorded_by' => $admin->id]);
        $this->assertDatabaseMissing('teacher_attendances', ['teacher_id' => $inactive->id]);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_bulk_teacher_attendance_requires_an_administrator(): void
    {
        $this->post(route('dashboard.teacher-attendance.bulk', 'all-present'))->assertRedirect('/login');
        foreach (['teacher', 'parent', 'student'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            foreach (['all-present', 'random'] as $action) {
                $this->post(route('dashboard.teacher-attendance.bulk', $action))->assertForbidden();
            }
            $this->get('/dashboard')->assertOk()->assertDontSee('Teacher attendance');
        }
        $this->assertDatabaseCount('teacher_attendances', 0);
    }
}


