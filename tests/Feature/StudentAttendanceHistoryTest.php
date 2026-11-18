<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentAttendanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_defaults_to_current_month_and_month_navigation_updates_the_report(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18));
        $student = Student::factory()->create();
        $student->attendances()->createMany([
            ['date' => '2026-11-01', 'status' => 'late', 'notes' => 'November record'],
            ['date' => '2026-10-31', 'status' => 'absent', 'notes' => 'October record'],
            ['date' => '2026-10-01', 'status' => 'present'],
        ]);
        $this->actingAs(User::factory()->create(['role' => 'administrator']))
            ->get(route('students.show', $student))
            ->assertOk()->assertSee('November 2026')->assertSee('100%')
            ->assertSee('November record')->assertDontSee('October record');

        $html = $this->getJson(route('students.show', ['student' => $student, 'month' => '2026-10']))
            ->assertOk()->json('html');
        $this->assertStringContainsString('October 2026', $html);
        $this->assertStringContainsString('October record', $html);
        $this->assertStringContainsString('50%', $html);
        $this->assertStringNotContainsString('November record', $html);

        $empty = $this->getJson(route('students.show', ['student' => $student, 'month' => '2026-09']))
            ->assertOk()->json('html');
        $this->assertStringContainsString('No attendance recorded this month.', $empty);

        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->getJson(route('students.show', ['student' => $student, 'month' => '2026-10']))
            ->assertForbidden();
    }

    public function test_history_includes_weekend_dates_and_preserves_recorded_statuses(): void
    {
        $student = new Student;
        $student->setRelation('attendances', collect([
            new Attendance(['date' => '2026-11-16', 'status' => 'present']),
            new Attendance(['date' => '2026-11-14', 'status' => 'late', 'notes' => 'Saturday class']),
            new Attendance(['date' => '2026-11-13', 'status' => 'present']),
        ]));

        $html = view('school.students._attendance-history-rows', compact('student'))->render();

        $this->assertStringContainsString('Nov 15, 2026', $html);
        $this->assertStringContainsString('Sunday', $html);
        $this->assertStringContainsString('Nov 14, 2026', $html);
        $this->assertStringContainsString('Saturday', $html);
        $this->assertStringContainsString('status-badge late', $html);
        $this->assertStringContainsString('Saturday class', $html);
        $this->assertStringNotContainsString('Weekend', $html);
        $this->assertStringNotContainsString('absent', $html);
        $this->assertCount(3, $student->attendances);
    }

    public function test_empty_history_does_not_invent_attendance_records(): void
    {
        $student = new Student;
        $student->setRelation('attendances', collect());

        $html = view('school.students._attendance-history-rows', compact('student'))->render();

        $this->assertStringContainsString('No attendance recorded yet.', $html);
    }
}
