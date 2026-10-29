<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_random_attendance_saves_balanced_totals_without_duplicates_or_changing_history(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        $students = Student::factory()->count(20)->create();
        Attendance::create(['student_id' => $students[0]->id, 'date' => today()->subDay(), 'status' => 'excused']);
        $this->actingAs($admin);
        foreach (range(1, 2) as $attempt) {
            $this->post(route('dashboard.attendance.random'))->assertRedirect(route('dashboard'))->assertSessionHas('success');
            $this->assertSame(20, Attendance::whereDate('date', today())->count());
            $this->getJson('/dashboard/data')->assertOk()
                ->assertJsonPath('summary.presentCount', 14)
                ->assertJsonPath('summary.lateCount', 4)
                ->assertJsonPath('summary.absentCount', 2)
                ->assertJsonPath('charts.attendance', [14, 2, 4, 0]);
        }
        $this->assertSame(1, Attendance::whereDate('date', today()->subDay())->where('status', 'excused')->count());
        $this->actingAs(User::factory()->create(['role' => 'teacher']))
            ->post(route('dashboard.attendance.random'))->assertForbidden();
    }

    public function test_admin_can_mark_every_student_present_today_without_duplicates_or_changing_history(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        $students = Student::factory()->count(4)->create();
        foreach (['absent', 'late', 'excused'] as $index => $status) {
            Attendance::create(['student_id' => $students[$index]->id, 'date' => today(), 'status' => $status, 'notes' => 'Original note']);
        }
        Attendance::create(['student_id' => $students[0]->id, 'date' => today()->subDay(), 'status' => 'absent']);
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee('Mark all present today');
        foreach (range(1, 2) as $attempt) {
            $this->post(route('dashboard.attendance.all-present'))->assertRedirect(route('dashboard'))->assertSessionHas('success');
            $this->assertSame(4, Attendance::whereDate('date', today())->count());
            $this->assertSame(4, Attendance::whereDate('date', today())->where('status', 'present')->count());
        }
        $this->assertDatabaseHas('attendances', ['student_id' => $students[0]->id, 'status' => 'present', 'notes' => 'Original note']);
        $this->assertSame(1, Attendance::whereDate('date', today()->subDay())->where('status', 'absent')->count());
        $this->getJson('/dashboard/data')->assertOk()->assertJsonPath('summary.presentCount', 4)->assertJsonPath('summary.absentCount', 0);
    }

    public function test_guests_and_non_admin_roles_cannot_mark_all_present(): void
    {
        $student = Student::factory()->create();
        $this->post(route('dashboard.attendance.all-present'))->assertRedirect('/login');
        foreach (['teacher', 'parent', 'student'] as $role) {
            $user = User::factory()->create();
            $user->role = $role;
            $user->save();
            $this->actingAs($user)->post(route('dashboard.attendance.all-present'))->assertForbidden();
            $this->get('/dashboard')->assertOk()->assertDontSee('Mark all present today');
        }
        $this->assertDatabaseCount('attendances', 0);
    }
}
