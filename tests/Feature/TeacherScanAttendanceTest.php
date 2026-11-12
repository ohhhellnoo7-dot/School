<?php

namespace Tests\Feature;

use App\Models\{Schedule, SchoolClass, SchoolLocation, Subject, User};
use App\Services\TeacherScanAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherScanAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(): User
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active', 'program' => 'Khmer']);
        foreach (['Morning' => ['07:00', '11:00'], 'Afternoon' => ['13:00', '16:00'], 'Evening' => ['16:30', '18:30']] as $session => [$start, $end]) {
            $class = SchoolClass::create(['name' => $session, 'section' => 'A', 'program' => 'Khmer', 'session' => $session]);
            $subject = Subject::create(['name' => $session, 'code' => $session, 'school_class_id' => $class->id, 'teacher_id' => $teacher->id]);
            Schedule::create(['subject_id' => $subject->id, 'day' => 'Wednesday', 'starts_at' => $start, 'ends_at' => $end, 'room' => 'A']);
        }
        return $teacher;
    }

    public function test_ten_minute_boundary_and_first_scan_are_preserved_for_every_session(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 10));
        $teacher = $this->teacher();
        $service = app(TeacherScanAttendance::class);
        $this->assertSame('present', $service->scan($teacher)['status']);
        $this->travelTo(now()->setTime(7, 11));
        $this->assertSame('present', $service->scan($teacher)['status']);
        $this->travelTo(now()->setTime(13, 10, 1));
        $this->assertSame('late', $service->scan($teacher)['status']);
        $this->travelTo(now()->setTime(16, 39));
        $this->assertSame('present', $service->scan($teacher)['status']);
        $this->assertDatabaseCount('teacher_session_attendances', 3);
        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teacher->id, 'status' => 'late']);
    }

    public function test_missing_scan_is_absent_only_after_grace_and_can_be_replaced_by_late_scan(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 10));
        $teacher = $this->teacher();
        $service = app(TeacherScanAttendance::class);
        $service->refreshDate('2026-11-11');
        $this->assertDatabaseCount('teacher_session_attendances', 0);
        $this->travelTo(now()->setTime(7, 10, 1));
        $this->artisan('attendance:refresh-teachers')->assertSuccessful();
        $this->assertDatabaseHas('teacher_session_attendances', ['teacher_id' => $teacher->id, 'session' => 'Morning', 'status' => 'absent']);
        $this->assertSame('late', $service->scan($teacher)['status']);
        $this->travelTo(now()->setTime(13, 11));
        $service->refreshDate('2026-11-11');
        $this->assertDatabaseHas('teacher_session_attendances', ['teacher_id' => $teacher->id, 'session' => 'Afternoon', 'status' => 'absent']);
        $this->assertDatabaseMissing('teacher_session_attendances', ['session' => 'Evening']);
    }

    public function test_verified_scan_updates_directory_and_records_each_session_check_in_once(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 11));
        $teacher = $this->teacher();
        $admin = User::factory()->create(['role' => 'administrator']);
        SchoolLocation::create(['name' => 'School', 'is_active' => true, 'latitude' => 11.6, 'longitude' => 104.94, 'radius_meters' => 100, 'created_by' => $admin->id]);
        $scan = fn () => ['latitude' => 11.6, 'longitude' => 104.94, 'accuracy' => 10, 'sampled_at' => now()->toIso8601String()];
        $this->actingAs($teacher)->postJson(route('school.map.scan'), $scan())->assertOk()->assertJsonPath('status', 'late')->assertJsonPath('session', 'Morning');
        $this->postJson(route('school.map.scan'), $scan())->assertOk();
        $this->assertDatabaseCount('school_location_check_ins', 1);
        $this->travelTo(now()->setTime(13, 5));
        $this->postJson(route('school.map.scan'), $scan())->assertOk()->assertJsonPath('status', 'present')->assertJsonPath('session', 'Afternoon');
        $this->assertDatabaseCount('school_location_check_ins', 2);
        $this->actingAs($admin)->get(route('modules.index', ['module' => 'teachers', 'session' => 'Afternoon']))
            ->assertOk()->assertViewHas('rowAttendance', fn ($records) => $records->get($teacher->id)?->status === 'present');
    }
}
