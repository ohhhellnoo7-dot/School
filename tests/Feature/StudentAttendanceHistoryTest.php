<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use Tests\TestCase;

class StudentAttendanceHistoryTest extends TestCase
{
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
