<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SchoolLocation;
use App\Models\TeacherAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolMapTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_requires_login_and_displays_the_requested_location(): void
    {
        $this->get(route('school.map'))->assertRedirect(route('login'));
        $user = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($user)->get(route('school.map'))->assertOk()->assertSee('Where is your campus?');
        $this->get(route('school.map', ['location' => '11.5564,104.9282']))
            ->assertOk()->assertSee('q=11.5564%2C104.9282', false)->assertSee('Get directions');
        $this->get(route('school.map', ['location' => str_repeat('a', 301)]))->assertSessionHasErrors('location');
    }

    public function test_only_admin_can_save_locations_and_saved_locations_are_shared(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $data = ['name' => 'Chroy Changva campus', 'latitude' => 11.6, 'longitude' => 104.94, 'radius_meters' => 100];
        $this->actingAs($teacher)->post(route('school.map.locations.store'), $data)->assertForbidden();
        $this->actingAs($admin)->post(route('school.map.locations.store'), $data)->assertRedirect();
        $this->actingAs($teacher)->get(route('school.map'))->assertOk()->assertViewHas('locations', fn ($locations) => $locations->contains('name', 'Chroy Changva campus'))->assertSee('Start scan location');
        $this->assertDatabaseCount('school_locations', 1);
        $this->actingAs($admin)->post(route('school.map.locations.store'), $data + ['unused' => true])->assertRedirect();
    }

    public function test_legacy_test_locations_cannot_be_selected_or_used_for_attendance(): void
    {
        [$admin, $site, $scan] = $this->siteAndScan();
        $site->update(['name' => 'Administrator current location (test)', 'radius_meters' => 762]);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $this->actingAs($teacher)->get(route('school.map', ['site' => $site->id]))
            ->assertOk()->assertDontSee('Administrator current location (test)')
            ->assertDontSee('Start scan location');
        $this->postJson(route('school.map.scan'), $scan)->assertNotFound();
        $this->actingAs($admin)->postJson(route('school.map.scan'), $scan)->assertNotFound();
        $this->get(route('school.map'))->assertOk()
            ->assertSee('Manage saved locations')
            ->assertDontSee('data-save-current-location', false)
            ->assertDontSee('data-use-my-location', false);
        $this->postJson(route('school.map.locations.store'), [
            'name' => ' Administrator current location (TEST) ',
            'latitude' => 11.6, 'longitude' => 104.94, 'radius_meters' => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('teacher_attendances', 0);
        $this->assertDatabaseCount('school_location_check_ins', 0);
    }

    public function test_admin_can_choose_any_coordinates_and_only_scans_inside_that_zone_succeed(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->post(route('school.map.locations.store'), [
            'name' => 'Chosen attendance area', 'latitude' => 85, 'longitude' => 59, 'radius_meters' => 100,
        ])->assertRedirect();
        $site = SchoolLocation::where('name', 'Chosen attendance area')->firstOrFail();
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $scan = ['location_id' => $site->id, 'latitude' => 11.5878424, 'longitude' => 104.9306214,
            'accuracy' => 10, 'sampled_at' => now()->toIso8601String()];
        $this->actingAs($teacher)->get(route('school.map', ['site' => $site->id, 'location' => '11.5878424,104.9306214']))
            ->assertOk()->assertSee('Start scan location')->assertDontSee('scan-school-location')->assertDontSee('Show selected school');
        $this->postJson(route('school.map.scan'), $scan)->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseCount('teacher_attendances', 0);
        $this->postJson(route('school.map.scan'), array_replace($scan, ['latitude' => 85, 'longitude' => 59]))
            ->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseCount('teacher_attendances', 1);
        $this->assertDatabaseHas('school_location_check_ins', ['school_location_id' => $site->id]);
    }

    public function test_scan_automatically_matches_a_saved_zone_without_a_location_selection(): void
    {
        [$admin, $site, $scan] = $this->siteAndScan();
        $other = SchoolLocation::create(['name' => 'Other campus', 'latitude' => 12.6,
            'longitude' => 104.94, 'radius_meters' => 100, 'created_by' => $admin->id]);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        unset($scan['location_id']);
        $scan['latitude'] = 12.6;
        $this->actingAs($teacher)->postJson(route('school.map.scan'), array_replace($scan, ['latitude' => 13.6]))
            ->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseCount('teacher_attendances', 0);
        $this->postJson(route('school.map.scan'), $scan)->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('location_name', 'Other campus');
        $this->assertDatabaseHas('school_location_check_ins', ['school_location_id' => $other->id]);
        // A supplied old selection cannot change the server's automatic match.
        $this->actingAs($admin)->postJson(route('school.map.scan'), $scan + ['location_id' => $site->id])
            ->assertOk()->assertJsonPath('location_name', 'Other campus');
    }

    public function test_location_can_be_removed_with_json_without_redirecting(): void
    {
        [$admin, $site, $scan] = $this->siteAndScan();
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $this->actingAs($teacher)->deleteJson(route('school.map.locations.destroy', $site))->assertForbidden();
        $this->actingAs($admin)->deleteJson(route('school.map.locations.destroy', $site))
            ->assertOk()->assertJsonPath('success', true)->assertHeaderMissing('Location');
        $this->assertSoftDeleted('school_locations', ['id' => $site->id]);
        $this->actingAs($teacher)->postJson(route('school.map.scan'), $scan)->assertNotFound();
        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    private function siteAndScan(): array
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $site = SchoolLocation::create(['name' => 'School', 'latitude' => 11.6, 'longitude' => 104.94, 'radius_meters' => 100, 'created_by' => $admin->id]);
        return [$admin, $site, ['location_id' => $site->id, 'latitude' => 11.6, 'longitude' => 104.94, 'accuracy' => 10, 'sampled_at' => now()->toIso8601String()]];
    }

    public function test_inside_scan_records_own_attendance_once_and_admin_scan_is_preview_only(): void
    {
        [$admin, $site, $scan] = $this->siteAndScan();
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active', 'program' => 'Khmer']);
        $this->actingAs($admin)->postJson(route('school.map.scan'), $scan)->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseCount('teacher_attendances', 0);
        $this->actingAs($teacher)->postJson(route('school.map.scan'), $scan + ['teacher_id' => $admin->id])->assertOk()->assertJsonPath('success', true);
        $this->postJson(route('school.map.scan'), $scan)->assertOk();
        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teacher->id, 'status' => 'present', 'recorded_by' => $teacher->id]);
        $this->assertDatabaseCount('teacher_attendances', 1);
        $this->assertDatabaseCount('school_location_check_ins', 1);
        TeacherAttendance::query()->update(['status' => 'late']);
        $this->postJson(route('school.map.scan'), $scan)->assertOk();
        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teacher->id, 'status' => 'late']);
    }

    public function test_outside_inaccurate_stale_and_removed_location_scans_cannot_record_attendance(): void
    {
        [$admin, $site, $scan] = $this->siteAndScan();
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $this->actingAs($teacher)->postJson(route('school.map.scan'), array_replace($scan, ['latitude' => 11.7]))->assertUnprocessable()->assertJsonPath('success', false);
        $this->postJson(route('school.map.scan'), array_replace($scan, ['accuracy' => 150]))->assertUnprocessable()->assertJsonPath('success', false);
        $this->postJson(route('school.map.scan'), array_replace($scan, ['latitude' => 11.6008, 'accuracy' => 30]))->assertUnprocessable()->assertJsonPath('success', false);
        $this->postJson(route('school.map.scan'), array_replace($scan, ['sampled_at' => now()->subMinutes(5)->toIso8601String()]))->assertUnprocessable()->assertJsonValidationErrors('sampled_at');
        $this->postJson(route('school.map.scan'), array_replace($scan, ['latitude' => 91]))->assertUnprocessable();
        $this->delete(route('school.map.locations.destroy', $site))->assertForbidden();
        $this->actingAs($admin)->delete(route('school.map.locations.destroy', $site))->assertRedirect();
        $this->actingAs($teacher)->postJson(route('school.map.scan'), $scan)->assertNotFound();
        $this->assertDatabaseCount('teacher_attendances', 0);
        $this->actingAs(User::factory()->create(['role' => 'student']))->postJson(route('school.map.scan'), $scan)->assertForbidden();
    }

    public function test_accuracy_is_checked_against_the_saved_radius_instead_of_a_fixed_limit(): void
    {
        [$admin, $site, $scan] = $this->siteAndScan();
        $site->update(['radius_meters' => 300]);
        $this->actingAs($admin)->postJson(route('school.map.scan'), array_replace($scan, ['accuracy' => 127]))
            ->assertOk()->assertJsonPath('success', true);
        $this->postJson(route('school.map.scan'), array_replace($scan, ['accuracy' => 301]))
            ->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseCount('teacher_attendances', 0);
    }
}
