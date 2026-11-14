<?php

namespace Tests\Feature;

use App\Models\{Schedule, SchoolClass, SchoolLocation, Subject, User};
use App\Services\TeacherScanAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherScanAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_status_can_be_filtered_to_a_specific_day(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'English']);
        foreach (['2026-11-09' => 'absent', '2026-11-13' => 'present'] as $date => $status) {
            \App\Models\TeacherSessionAttendance::create(['teacher_id' => $teacher->id, 'date' => $date, 'session' => 'Morning', 'status' => $status]);
        }
        $filters = ['month' => '2026-11', 'program' => 'English', 'session' => 'Morning', 'status' => 'absent'];
        $this->actingAs($admin)->get(route('teacher-attendance.monthly', $filters))
            ->assertOk()->assertViewHas('groups', fn ($groups) => $groups->has('English'));
        $this->get(route('teacher-attendance.monthly', $filters + ['date' => '2026-11-13']))
            ->assertOk()->assertViewHas('groups', fn ($groups) => $groups->isEmpty());
        $filters['status'] = 'present';
        $this->get(route('teacher-attendance.monthly', $filters + ['date' => '2026-11-13']))
            ->assertOk()->assertViewHas('groups', fn ($groups) => $groups->has('English'))->assertSee('13 Nov 2026');
        $this->getJson(route('teacher-attendance.monthly', ['date' => 'invalid']))->assertUnprocessable();
    }

    public function test_monthly_grid_shows_program_and_three_separate_sessions(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 10));
        $teacher = $this->teacher();
        app(TeacherScanAttendance::class)->scan($teacher);
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->get(route('teacher-attendance.monthly', ['month' => '2026-11']))
            ->assertOk()->assertSee($teacher->name)->assertSee('Khmer')->assertSee('Morning, Afternoon, Evening')
            ->assertSee('Scanned 07:10')->assertSee('Afternoon · Not recorded')->assertSee('Evening · Not recorded');
        $this->get(route('teacher-attendance.monthly', ['month' => 'bad']))->assertSessionHasErrors('month');
        $this->actingAs(User::factory()->create(['role' => 'teacher']))
            ->get(route('teacher-attendance.monthly', ['month' => '2026-11']))->assertOk()->assertDontSee($teacher->name);
        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->get(route('teacher-attendance.monthly'))->assertForbidden();
    }

    public function test_monthly_cells_show_actual_arrival_and_minutes_late_without_inventing_admin_scan_times(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 23));
        $teacher = $this->teacher();
        app(TeacherScanAttendance::class)->scan($teacher);
        $admin = User::factory()->create(['role' => 'administrator']);
        $url = route('teacher-attendance.monthly', ['month' => '2026-11', 'session' => 'Morning']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('In 07:23')->assertSee('23 min late');
        $this->travelTo(now()->setTime(7, 45));
        app(TeacherScanAttendance::class)->scan($teacher);
        $this->get($url)->assertSee('In 07:23')->assertSee('23 min late')->assertDontSee('In 07:45');
        $this->travelTo(now()->addWeek()->setTime(7, 0));
        app(TeacherScanAttendance::class)->scan($teacher);
        $this->get($url)->assertSee('In 07:00');
        \App\Models\TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => '2026-11-12',
            'program' => 'Khmer', 'status' => 'late', 'recorded_by' => $admin->id]);
        $this->get($url)->assertOk()->assertDontSee('0 min late');
    }

    public function test_monthly_scan_out_remains_visible_after_an_administrator_overrides_status(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 23));
        $teacher = $this->teacher();
        $service = app(TeacherScanAttendance::class);
        $service->scan($teacher);
        $admin = User::factory()->create(['role' => 'administrator']);
        $url = route('teacher-attendance.monthly', ['month' => '2026-11', 'session' => 'Morning']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Not scanned')->assertDontSee('Out 11:30');
        $this->travelTo(now()->setTime(11, 30));
        $service->checkout($teacher, []);
        \App\Models\TeacherAttendance::where('teacher_id', $teacher->id)->update([
            'status' => 'excused', 'recorded_by' => $admin->id,
        ]);
        $this->get($url)->assertOk()->assertSee('In 07:23')->assertSee('Out 11:30')
            ->assertSee('23 min late')->assertSee('Administrator daily mark')
            ->assertSee('mark-excused')->assertDontSee('Out <span>Not scanned</span>', false);
    }

    public function test_monthly_scan_out_changes_from_pending_to_missing_and_complete(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 0));
        $teacher = $this->teacher();
        $service = app(TeacherScanAttendance::class);
        $service->scan($teacher);
        $admin = User::factory()->create(['role' => 'administrator']);
        $url = route('teacher-attendance.monthly', ['month' => '2026-11']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Scan in')->assertSee('Scan out')
            ->assertDontSee('scan-out-missing')->assertDontSee('scan-out-complete')
            ->assertDontSee('$allCheckedOut')->assertDontSee('@else')
            ->assertSee('<span class="attendance-out-symbol" aria-hidden="true">—</span>', false);
        $this->travelTo(now()->setTime(11, 31));
        $this->get($url)->assertOk()->assertSee('scan-out-missing')->assertSee('Missing scan-out');
        $service->checkout($teacher, []);
        $this->get($url)->assertOk()->assertSee('scan-out-complete')->assertSee('Out 11:31')
            ->assertDontSee('scan-out-missing');
    }

    public function test_monthly_status_buttons_filter_effective_session_marks(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 0));
        $teacher = $this->teacher();
        app(TeacherScanAttendance::class)->scan($teacher);
        $admin = User::factory()->create(['role' => 'administrator']);
        foreach (['present', 'absent', 'late', 'excused'] as $status) {
            \App\Models\TeacherAttendance::updateOrCreate(['teacher_id' => $teacher->id, 'date' => today()],
                ['program' => 'Khmer', 'status' => $status, 'recorded_by' => $admin->id]);
            $this->actingAs($admin)->get(route('teacher-attendance.monthly', [
                'month' => '2026-11', 'program' => 'Khmer', 'session' => 'Morning', 'status' => $status,
            ]))->assertOk()->assertSee('All statuses')->assertViewHas('groups', fn ($groups) => $groups->flatten(1)->count() === 1);
            $other = $status === 'present' ? 'absent' : 'present';
            $this->get(route('teacher-attendance.monthly', ['month' => '2026-11', 'session' => 'Morning', 'status' => $other]))
                ->assertOk()->assertViewHas('groups', fn ($groups) => $groups->isEmpty());
        }
        $this->getJson(route('teacher-attendance.monthly', ['status' => 'invalid']))->assertUnprocessable();
    }

    public function test_monthly_daily_summary_uses_the_same_administrator_marks_as_the_overview(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer']);
        \App\Models\TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => today(), 'program' => 'Khmer', 'status' => 'excused', 'recorded_by' => $admin->id]);
        $this->actingAs($admin)->get(route('teacher-attendance.monthly'))
            ->assertOk()->assertSee($teacher->name)->assertSee('Daily summary')
            ->assertSee('Daily summary · excused')->assertSee('mark-excused');
    }

    public function test_monthly_session_honors_admin_marks_like_the_overview_without_claiming_a_scan(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(19, 0));
        $teacher = $this->teacher();
        $admin = User::factory()->create(['role' => 'administrator']);
        \App\Models\TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => today(), 'program' => 'Khmer', 'status' => 'present', 'recorded_by' => $admin->id]);
        app(TeacherScanAttendance::class)->refreshDate(today()->toDateString());
        $this->actingAs($admin)->get(route('teacher-attendance.monthly', ['month' => '2026-11', 'session' => 'Evening']))
            ->assertOk()->assertSee('Evening · present · Administrator daily mark')->assertDontSee('>Admin</small>', false);
        $this->assertDatabaseHas('teacher_session_attendances', ['teacher_id' => $teacher->id, 'session' => 'Evening', 'status' => 'absent', 'scanned_at' => null]);
    }

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

    public function test_checkout_requires_verified_location_arrival_and_session_end_and_is_idempotent(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 0));
        $teacher = $this->teacher();
        $admin = User::factory()->create(['role' => 'administrator']);
        $location = SchoolLocation::create(['name' => 'Campus', 'latitude' => 11.6, 'longitude' => 104.94,
            'radius_meters' => 100, 'is_active' => true, 'created_by' => $admin->id]);
        \App\Models\AttendanceSessionSetting::create(['session' => 'Morning', 'starts_at' => '07:00', 'ends_at' => '11:30', 'grace_minutes' => 10]);
        $payload = fn ($action) => ['action' => $action, 'latitude' => 11.6, 'longitude' => 104.94,
            'accuracy' => 10, 'sampled_at' => now()->toIso8601String()];
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee('Start work scan')->assertDontSee('data-finish-work', false);
        $this->postJson(route('school.map.scan'), $payload('check_out'))->assertUnprocessable()->assertJsonPath('success', false);
        $this->postJson(route('school.map.scan'), $payload('check_in'))->assertOk()->assertJsonPath('status', 'present');
        $this->travelTo(now()->setTime(11, 29));
        $this->postJson(route('school.map.scan'), $payload('check_out'))->assertUnprocessable();
        $this->travelTo(now()->setTime(11, 30));
        $this->postJson(route('school.map.scan'), array_replace($payload('check_out'), ['latitude' => 0]))->assertUnprocessable();
        $this->assertDatabaseMissing('teacher_session_attendances', ['teacher_id' => $teacher->id, 'checked_out_at' => now()]);
        $stamp = now()->toIso8601String();
        $this->postJson(route('school.map.scan'), $payload('check_out'))->assertOk()
            ->assertJsonPath('action', 'check_out')->assertJsonPath('scanned_at', $stamp)->assertJsonPath('status', 'present');
        $this->travelTo(now()->addMinutes(5));
        $this->postJson(route('school.map.scan'), $payload('check_out'))->assertOk()->assertJsonPath('scanned_at', $stamp)
            ->assertJsonPath('already_scanned', true)->assertJsonPath('encouragement', 'You already scanned.');
        $this->postJson(route('school.map.scan'), $payload('check_out'))->assertOk()->assertJsonPath('scanned_at', $stamp)
            ->assertJsonPath('encouragement', 'You already scanned.');
        $record = \App\Models\TeacherSessionAttendance::where('teacher_id', $teacher->id)->where('session', 'Morning')->firstOrFail();
        $this->assertSame('07:00', $record->scanned_at->format('H:i'));
        $this->assertSame('11:30', $record->checked_out_at->format('H:i'));
        $this->assertSame($location->id, $record->checkout_location_id);
        $this->assertDatabaseCount('school_location_check_ins', 1);
        $this->actingAs($admin)->get(route('teacher-attendance.monthly'))->assertOk()->assertSee('Out 11:30');
    }

    public function test_single_work_scan_switches_at_session_end_and_resets_for_next_session(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 0));
        $teacher = $this->teacher();
        $this->actingAs($teacher)->getJson(route('school.map.scan-state'))->assertOk()
            ->assertJsonPath('action', 'check_in')->assertJsonPath('label', 'Start work scan');
        app(TeacherScanAttendance::class)->scan($teacher);
        $this->travelTo(now()->setTime(10, 59));
        $this->getJson(route('school.map.scan-state'))->assertJsonPath('action', 'check_in');
        $this->travelTo(now()->setTime(11, 0));
        $this->getJson(route('school.map.scan-state'))->assertJsonPath('action', 'check_out')
            ->assertJsonPath('label', 'Finish work scan');
        app(TeacherScanAttendance::class)->checkout($teacher, []);
        $this->getJson(route('school.map.scan-state'))->assertJsonPath('action', 'check_out');
        $this->travelTo(now()->setTime(13, 0));
        $this->getJson(route('school.map.scan-state'))->assertJsonPath('action', 'check_in');
        $this->travelTo(now()->addDay()->setTime(7, 0));
        $this->getJson(route('school.map.scan-state'))->assertJsonPath('action', 'check_in');
        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->getJson(route('school.map.scan-state'))->assertForbidden();
    }

    public function test_administrator_can_configure_each_session_and_other_roles_cannot(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $sessions = [];
        foreach (['Morning' => '07:15', 'Afternoon' => '13:15', 'Evening' => '16:45'] as $session => $start) {
            $sessions[$session] = ['starts_at' => $start, 'ends_at' => null, 'grace_minutes' => 0];
        }
        $this->actingAs($admin)->get(route('attendance-settings.edit'))->assertOk()->assertSee('Work start time');
        $this->put(route('attendance-settings.update'), ['sessions' => $sessions])->assertRedirect(route('attendance-settings.edit'));
        $this->assertDatabaseHas('attendance_session_settings', ['session' => 'Morning', 'starts_at' => '07:15', 'grace_minutes' => 0]);
        $sessions['Morning']['ends_at'] = '11:30';
        $this->put(route('attendance-settings.update'), ['sessions' => $sessions])->assertRedirect();
        $this->assertDatabaseHas('attendance_session_settings', ['session' => 'Morning', 'ends_at' => '11:30']);
        $sessions['Morning']['ends_at'] = '06:00';
        $this->putJson(route('attendance-settings.update'), ['sessions' => $sessions])->assertUnprocessable();
        $sessions['Morning']['starts_at'] = '25:15';
        $this->putJson(route('attendance-settings.update'), ['sessions' => $sessions])->assertUnprocessable();
        foreach (['teacher', 'student', 'parent'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('attendance-settings.edit'))->assertForbidden();
            $this->putJson(route('attendance-settings.update'), ['sessions' => $sessions])->assertForbidden();
        }
    }

    public function test_custom_cutoffs_apply_per_session_and_preserve_first_scan_timing(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 15));
        $teacher = $this->teacher();
        foreach (['Morning' => '07:15', 'Afternoon' => '13:15', 'Evening' => '16:45'] as $session => $start) {
            \App\Models\AttendanceSessionSetting::create(['session' => $session, 'starts_at' => $start, 'grace_minutes' => 0]);
        }
        $service = app(TeacherScanAttendance::class);
        $this->assertSame('present', $service->scan($teacher)['status']);
        $this->travelTo(now()->setTime(13, 16));
        $scan = $service->scan($teacher);
        $this->assertSame('Afternoon', $scan['session']);
        $this->assertSame('late', $scan['status']);
        $this->assertSame(1, $scan['late_minutes']);
        \App\Models\AttendanceSessionSetting::where('session', 'Afternoon')->update(['starts_at' => '13:30']);
        $this->assertSame(1, $service->scan($teacher)['late_minutes']);
        $this->travelTo(now()->setTime(16, 45, 1));
        $this->assertSame('late', $service->scan($teacher)['status']);
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

    public function test_after_hours_scan_returns_a_distinct_session_notice_without_recording_attendance(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(22, 0));
        $teacher = $this->teacher();
        $result = app(TeacherScanAttendance::class)->scan($teacher);
        $this->assertFalse($result['success']);
        $this->assertSame('outside_session', $result['reason']);
        $this->assertDatabaseCount('teacher_session_attendances', 0);
    }

    public function test_session_messages_follow_live_cutoff_and_thank_teachers_on_departure(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 0));
        $teacher = $this->teacher();
        $service = app(TeacherScanAttendance::class);
        foreach ([['Morning', 7, 0, 11, 0], ['Afternoon', 13, 0, 16, 0], ['Evening', 16, 30, 18, 30]] as [$session, $hour, $minute, $endHour, $endMinute]) {
            $this->travelTo(now()->setTime($hour, $minute)->subMinute());
            $arrival = $service->scan($teacher);
            $this->assertSame($session, $arrival['session']);
            $this->assertStringContainsString('Good '.strtolower($session), $arrival['encouragement']);
            $this->travelTo(now()->setTime($hour, $minute)->addMinutes(10));
            $this->assertNotNull($service->scan($teacher)['encouragement']);
            $this->travelTo(now()->addSecond());
            $this->assertSame('You already scanned.', $service->scan($teacher)['encouragement']);
            $this->travelTo(now()->setTime($endHour, $endMinute));
            $departure = $service->checkout($teacher, []);
            $this->assertTrue($departure['success']);
            $this->assertStringContainsString('Thank you', $departure['encouragement']);
            $this->assertStringContainsString(strtolower($session), $departure['encouragement']);
        }
    }

    public function test_late_arrival_has_no_motivation_and_custom_allowance_is_respected(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 15));
        $teacher = $this->teacher();
        \App\Models\AttendanceSessionSetting::create(['session' => 'Morning', 'starts_at' => '07:15', 'grace_minutes' => 0]);
        $service = app(TeacherScanAttendance::class);
        $this->assertNotNull($service->scan($teacher)['encouragement']);
        $this->travelTo(now()->addWeek()->addSecond());
        $scan = $service->scan($teacher);
        $this->assertSame('late', $scan['status']);
        $this->assertNull($scan['encouragement']);
    }

    public function test_verified_scan_updates_directory_and_records_each_session_check_in_once(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(7, 11));
        $teacher = $this->teacher();
        $admin = User::factory()->create(['role' => 'administrator']);
        SchoolLocation::create(['name' => 'School', 'is_active' => true, 'latitude' => 11.6, 'longitude' => 104.94, 'radius_meters' => 100, 'created_by' => $admin->id]);
        $scan = fn () => ['latitude' => 11.6, 'longitude' => 104.94, 'accuracy' => 10, 'sampled_at' => now()->toIso8601String()];
        $originalTime = now()->toIso8601String();
        $this->actingAs($teacher)->postJson(route('school.map.scan'), $scan())->assertOk()->assertJsonPath('status', 'late')->assertJsonPath('session', 'Morning')
            ->assertJsonPath('teacher_name', $teacher->name)->assertJsonPath('scanned_at', $originalTime)
            ->assertJsonPath('late_minutes', 11)->assertJsonPath('location_name', 'School');
        $this->travelTo(now()->setTime(7, 25));
        $this->postJson(route('school.map.scan'), $scan())->assertOk()->assertJsonPath('scanned_at', $originalTime)->assertJsonPath('late_minutes', 11)
            ->assertJsonPath('already_scanned', true)->assertJsonPath('encouragement', 'You already scanned.');
        $this->postJson(route('school.map.scan'), $scan())->assertOk()->assertJsonPath('scanned_at', $originalTime)
            ->assertJsonPath('encouragement', 'You already scanned.');
        $this->assertDatabaseCount('school_location_check_ins', 1);
        $this->travelTo(now()->setTime(13, 5));
        $this->postJson(route('school.map.scan'), $scan())->assertOk()->assertJsonPath('status', 'present')->assertJsonPath('session', 'Afternoon')->assertJsonPath('late_minutes', 0);
        $this->assertDatabaseCount('school_location_check_ins', 2);
        $this->actingAs($admin)->get(route('modules.index', ['module' => 'teachers', 'session' => 'Afternoon']))
            ->assertOk()->assertViewHas('rowAttendance', fn ($records) => $records->get($teacher->id)?->status === 'present');
    }
}
