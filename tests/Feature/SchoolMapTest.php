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

    public function test_only_administrators_can_edit_saved_location_radius(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $location = SchoolLocation::create(['name' => 'Campus', 'latitude' => 11.58739, 'longitude' => 104.93052, 'radius_meters' => 272, 'is_active' => true, 'created_by' => $admin->id]);
        $this->actingAs($admin)->get(route('school.map'))->assertOk()->assertSee('Edit radius');
        $this->patch(route('school.map.locations.radius', $location), ['radius_meters' => 350])
            ->assertRedirect(route('school.map', ['site' => $location->id]));
        $this->assertSame(350, (int) $location->fresh()->radius_meters);
        $this->assertTrue((bool) $location->fresh()->is_active);
        foreach ([19, 5001, 25.5] as $radius) {
            $this->patchJson(route('school.map.locations.radius', $location), ['radius_meters' => $radius])->assertUnprocessable();
        }
        $this->actingAs(User::factory()->create(['role' => 'teacher']))
            ->patchJson(route('school.map.locations.radius', $location), ['radius_meters' => 100])->assertForbidden();
        $this->assertSame(350, (int) $location->fresh()->radius_meters);
    }

    public function test_administrator_can_open_the_shared_scan_popup_from_overview(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('teacher-scan-shortcut', false)->assertSee('data-scan-dialog', false)
            ->assertSee('data-close-scan', false)->assertSee('scan-loading-template', false);
        $this->get(route('school.map'))->assertOk()->assertSee('data-scan-dialog', false);
    }

    public function test_teacher_can_scan_from_overview_with_a_closable_result_dialog(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()
            ->assertSee('data-scan-dialog', false)
            ->assertSee('data-close-scan', false)
            ->assertSee('data-global-scan', false)
            ->assertSee(route('school.map.scan'), false)
            ->assertSee('js/school-map.js', false);
    }

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
            ->assertDontSee('data-scan-location', false);
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

    public function test_switching_battambang_phnom_penh_battambang_does_not_accept_an_old_zone(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $scan = ['latitude' => 11.58739, 'longitude' => 104.93052, 'accuracy' => 10,
            'sampled_at' => now()->toIso8601String()];
        $battambang = ['name' => 'Battambang', 'latitude' => 13.09486, 'longitude' => 103.19811, 'radius_meters' => 100];
        $phnomPenh = ['name' => 'Phnom Penh', 'latitude' => 11.58739, 'longitude' => 104.93052, 'radius_meters' => 100];
        foreach ([$battambang, $phnomPenh, $battambang] as $index => $data) {
            $this->actingAs($admin)->post(route('school.map.locations.store'), $data)->assertRedirect();
            $this->assertSame(1, SchoolLocation::where('is_active', true)->count());
            $response = $this->actingAs($teacher)->postJson(route('school.map.scan'), $scan);
            if ($index === 1) {
                $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('location_name', 'Phnom Penh');
            } else {
                $response->assertUnprocessable()->assertJsonPath('success', false)->assertJsonPath('location_name', 'Battambang');
            }
        }
        $old = SchoolLocation::where('name', 'Phnom Penh')->firstOrFail();
        $this->postJson(route('school.map.scan'), $scan + ['location_id' => $old->id])
            ->assertUnprocessable()->assertJsonPath('location_name', 'Battambang');
        $this->get(route('school.map', ['site' => $old->id, 'location' => '11.58739,104.93052']))
            ->assertOk()->assertViewHas('activeLocation', fn ($location) => $location->name === 'Battambang');
        $this->assertDatabaseCount('teacher_attendances', 1);
        $this->assertDatabaseCount('school_location_check_ins', 1);
    }

    public function test_only_admin_can_activate_history_and_deleting_active_zone_does_not_fall_back(): void
    {
        [$admin, $site, $scan] = $this->siteAndScan();
        $old = SchoolLocation::create(['name' => 'History', 'latitude' => 13.09486,
            'longitude' => 103.19811, 'radius_meters' => 100, 'created_by' => $admin->id]);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $this->actingAs($teacher)->post(route('school.map.locations.activate', $old))->assertForbidden();
        $this->actingAs($admin)->post(route('school.map.locations.activate', $old))->assertRedirect();
        $this->assertFalse($site->fresh()->is_active ?? false);
        $this->assertTrue($old->fresh()->is_active);
        $this->actingAs($teacher)->postJson(route('school.map.scan'), $scan)->assertUnprocessable();
        $this->actingAs($admin)->deleteJson(route('school.map.locations.destroy', $old))->assertOk();
        $this->postJson(route('school.map.scan'), $scan)->assertNotFound();
        $this->assertDatabaseCount('teacher_attendances', 0);
        $site->update(['name' => 'Administrator current location (test)']);
        $this->post(route('school.map.locations.activate', $site))->assertNotFound();
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

    public function test_map_box_sets_the_actual_zone_and_unsaved_coordinates_cannot_show_success(): void
    {
        [$admin, $site, $scan] = $this->siteAndScan();
        $this->actingAs($admin)->postJson(route('school.map.scan'), $scan + [
            'target_location' => '13.0948600,103.1981100', 'expected_location_id' => $site->id,
        ])->assertConflict()->assertJsonPath('success', false);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $this->actingAs($teacher)->post(route('school.map.set-location'), ['location' => '13.09486,103.19811'])->assertForbidden();
        $this->actingAs($admin)->post(route('school.map.set-location'), ['location' => '13.09486,103.19811'])->assertRedirect();
        $active = SchoolLocation::where('is_active', true)->firstOrFail();
        $this->assertSame('13.0948600', $active->latitude);
        $this->assertSame(1, SchoolLocation::where('is_active', true)->count());
        $this->postJson(route('school.map.scan'), $scan + ['expected_location_id' => $site->id])
            ->assertConflict()->assertJsonPath('success', false);
        $this->postJson(route('school.map.scan'), $scan)->assertUnprocessable()->assertJsonPath('success', false);
        $this->post(route('school.map.set-location'), ['location' => '11.6,104.94'])->assertRedirect();
        $this->assertSame($site->id, SchoolLocation::where('is_active', true)->firstOrFail()->id);
        $this->postJson(route('school.map.scan'), $scan)->assertOk()->assertJsonPath('success', true);
        $this->post(route('school.map.set-location'), ['location' => '13.09486,103.19811'])->assertRedirect();
        $this->postJson(route('school.map.scan'), $scan)->assertUnprocessable()->assertJsonPath('success', false);
        foreach (['Phnom Penh', '91,104', '13,181', ','] as $invalid) {
            $this->postJson(route('school.map.set-location'), ['location' => $invalid])->assertUnprocessable();
        }
        $this->assertDatabaseCount('teacher_attendances', 0);
    }

    public function test_teacher_map_is_read_only_and_only_the_scan_action_is_available(): void
    {
        [$admin, $site, $scan] = $this->siteAndScan();
        $other = SchoolLocation::create(['name' => 'Historical area', 'latitude' => 13.09486,
            'longitude' => 103.19811, 'radius_meters' => 100, 'created_by' => $admin->id]);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $this->actingAs($teacher)->get(route('school.map', ['location' => '13.09486,103.19811', 'site' => $other->id]))
            ->assertOk()->assertSee('Start scan location')->assertSee('This location is set by the administrator.')
            ->assertSee('q=11.6000000%2C104.9400000', false)
            ->assertSee('inert aria-label="Read-only school map"', false)
            ->assertDontSee('id="map-location"', false)->assertDontSee('Show on map')
            ->assertDontSee('Set attendance location</button>', false)
            ->assertDontSee('data-location-history', false)->assertDontSee('Get directions')
            ->assertDontSee('Manage saved locations')->assertDontSee('Historical area');
        $this->post(route('school.map.set-location'), ['location' => '13.09486,103.19811'])->assertForbidden();
        $this->post(route('school.map.locations.activate', $other))->assertForbidden();
        $this->delete(route('school.map.locations.destroy', $site))->assertForbidden();
        $this->postJson(route('school.map.scan'), $scan)->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('teacher_attendances', ['teacher_id' => $teacher->id, 'status' => 'present']);
    }

    private function siteAndScan(): array
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $site = SchoolLocation::create(['name' => 'School', 'is_active' => true, 'latitude' => 11.6, 'longitude' => 104.94, 'radius_meters' => 100, 'created_by' => $admin->id]);
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
