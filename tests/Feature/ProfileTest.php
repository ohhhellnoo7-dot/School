<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->put('/profile')->assertRedirect('/login');
        $this->get('/profile/photo')->assertRedirect('/login');
    }

    public function test_user_can_update_own_details_and_replace_photo(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Choose profile photo');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $data = ['name' => 'Updated Name', 'email' => $user->email, 'phone' => '012345678', 'address' => 'New address', 'role' => 'administrator', 'id' => $other->id];
        $this->put('/profile', $data + ['photo' => UploadedFile::fake()->createWithContent('avatar.png', $png)])
            ->assertRedirect('/profile')->assertSessionHasNoErrors();
        $user->refresh();
        $firstPhoto = $user->profile_photo_path;
        Storage::disk('local')->assertExists($firstPhoto);
        $this->assertSame('student', $user->role);
        $this->assertSame('Updated Name', $user->name);
        $this->assertNotSame('Updated Name', $other->fresh()->name);
        $this->get('/profile/photo')->assertOk()->assertHeader('content-type', 'image/png');
        $this->get('/profile')->assertSee(route('profile.photo'), false);
        $this->put('/profile', $data + ['photo' => UploadedFile::fake()->createWithContent('next.png', $png)])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($firstPhoto);
        $this->put('/profile', $data)->assertSessionHasNoErrors();
        Storage::disk('local')->assertExists($user->fresh()->profile_photo_path);
        $this->actingAs($other)->get('/profile/photo')->assertNotFound();
    }

    public function test_account_photos_appear_in_student_directory_and_related_records(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'administrator']);
        $account = User::factory()->create(['profile_photo_path' => 'profile-photos/student.png']);
        Storage::disk('local')->put($account->profile_photo_path, 'photo');
        $student = \App\Models\Student::factory()->create(['user_id' => $account->id]);
        $url = route('users.photo', $account);
        $this->actingAs($admin)->get('/students')->assertOk()->assertSee($url, false);
        $this->get('/students/'.$student->id)->assertOk()->assertSee($url, false);
        $this->get($url)->assertOk();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($account)->get($url)->assertOk();
    }

    public function test_teacher_and_parent_directories_show_profile_photos(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        foreach (['teacher' => 'teachers', 'parent' => 'parents'] as $role => $module) {
            $person = User::factory()->create(['role' => $role, 'profile_photo_path' => 'profile-photos/person.png']);
            $this->actingAs($admin)->get('/school/'.$module)->assertOk()->assertSee(route('users.photo', $person), false);
            $this->get('/school/'.$module.'/'.$person->id)->assertOk()->assertSee(route('users.photo', $person), false);
        }
    }

    public function test_enrollment_photo_is_served_without_public_storage_link_and_is_authorized(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('students/photo.png', 'photo');
        $student = \App\Models\Student::factory()->create(['photo' => 'students/photo.png']);
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->get(route('students.photo', $student))->assertOk();
        $this->get('/students')->assertOk()->assertSee(route('students.photo', $student), false);
        $this->actingAs(User::factory()->create())->get(route('students.photo', $student))->assertForbidden();
    }

    public function test_invalid_upload_and_duplicate_email_are_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user)->put('/profile', [
            'name' => 'Changed', 'email' => $other->email,
            'photo' => UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml'),
        ])->assertSessionHasErrors(['email', 'photo']);
        $this->assertSame($user->name, $user->fresh()->name);
    }
}
