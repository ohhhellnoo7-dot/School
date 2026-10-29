<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnrollmentAccountsTest extends TestCase
{
    use RefreshDatabase;

    private function enrollment(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Student', 'email' => 'student@gmail.com', 'gender' => 'female',
            'date_of_birth' => '2013-01-01', 'school_class_id' => SchoolClass::create(['name' => 'Grade 1', 'section' => 'A'])->id,
            'guardian_name' => 'Parent', 'guardian_phone' => '012345678', 'guardian_email' => 'parent@gmail.com',
            'enrollment_date' => today()->toDateString(), 'status' => 'active', 'create_accounts' => '1',
            'student_password' => '123', 'parent_password' => '456',
        ], $overrides);
    }

    private function administrator(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'administrator'])->save();
        $this->actingAs($admin);

        return $admin;
    }

    public function test_enrollment_creates_both_accounts_displays_credentials_once_and_allows_login(): void
    {
        $this->administrator();
        $this->post('/students', $this->enrollment())->assertSessionHasNoErrors()->assertSessionHas('enrollment_accounts');
        $student = Student::firstOrFail();
        $this->assertSame('student', $student->user->role);
        $this->assertSame('parent', $student->parent->role);
        $this->assertTrue(Hash::check('123', $student->user->password));
        $this->assertTrue(Hash::check('456', $student->parent->password));
        $this->get(route('students.show', $student))->assertOk()->assertSee('Student &amp; parent login details', false)->assertSee('123')->assertSee('456');
        $this->get(route('students.show', $student))->assertDontSee('Enrollment login details');
        $this->post('/logout');
        $this->get(route('login', ['email' => $student->user->email]))->assertOk()->assertSee('value="student@gmail.com"', false);
        foreach ([$student->user, $student->parent] as $account) {
            $this->post('/login', ['email' => $account->email, 'password' => $account->role === 'student' ? '123' : '456'])->assertRedirect('/dashboard');
            $this->get(route('students.show', $student))->assertOk();
            $this->post('/logout');
        }
    }

    public function test_existing_email_accounts_are_linked_without_resetting_passwords(): void
    {
        $this->administrator();
        $studentUser = User::factory()->create(['email' => 'student@gmail.com']);
        $parent = User::factory()->create(['email' => 'parent@gmail.com']);
        $parent->forceFill(['role' => 'parent'])->save();
        $hash = $parent->password;
        $this->post('/students', $this->enrollment())->assertSessionHasNoErrors()
            ->assertSessionHas('enrollment_accounts', fn ($accounts) => count($accounts) === 2 && $accounts[0]['password'] === null && $accounts[1]['password'] === null);
        $this->assertDatabaseHas('students', ['user_id' => $studentUser->id, 'parent_id' => $parent->id]);
        $this->assertSame($hash, $parent->fresh()->password);
    }

    public function test_conflicting_role_rolls_back_new_accounts_and_student(): void
    {
        $this->administrator();
        $this->post('/students', $this->enrollment(['guardian_email' => auth()->user()->email]))->assertSessionHasErrors('guardian_email');
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseMissing('users', ['email' => 'student@gmail.com']);
    }

    public function test_generated_passwords_are_available_on_payment_redirect(): void
    {
        $this->administrator();
        $this->post('/students', $this->enrollment(['student_password' => '', 'parent_password' => '', 'create_test_fee' => 1]))
            ->assertSessionHasNoErrors()->assertRedirect(route('fees.pay', 1))
            ->assertSessionHas('enrollment_accounts', fn ($accounts) => strlen($accounts[0]['password']) === 6 && strlen($accounts[1]['password']) === 6);
        $this->get(route('fees.pay', 1))->assertOk()->assertSee('Enrollment login details');
    }

    public function test_password_validation_does_not_flash_plaintext_and_parent_email_is_required(): void
    {
        $this->administrator();
        $this->from('/students/create')->post('/students', $this->enrollment(['guardian_email' => '', 'student_password' => '12']))
            ->assertSessionHasErrors(['guardian_email', 'student_password'])
            ->assertSessionMissing('_old_input.student_password')->assertSessionMissing('_old_input.parent_password');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_parent_photo_is_saved_to_linked_account_and_can_be_replaced(): void
    {
        Storage::fake('local');
        $this->administrator();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $data = $this->enrollment();
        $this->get('/students/create')->assertOk()->assertSee('name="parent_photo"', false);
        $this->post('/students', $data + ['parent_photo' => UploadedFile::fake()->createWithContent('parent.png', $png)])->assertSessionHasNoErrors();
        $student = Student::firstOrFail();
        $parent = $student->parent;
        $oldPath = $parent->profile_photo_path;
        $this->assertNotNull($oldPath);
        Storage::disk('local')->assertExists($oldPath);
        $this->get(route('users.photo', $parent))->assertOk();
        $this->put(route('students.update', $student), $data + ['parent_id' => $parent->id, 'parent_photo' => UploadedFile::fake()->createWithContent('new.png', $png)])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($oldPath);
        $newPath = $parent->fresh()->profile_photo_path;
        Storage::disk('local')->assertExists($newPath);
        $this->put(route('students.update', $student), $data + ['parent_id' => $parent->id])->assertSessionHasNoErrors();
        $this->assertSame($newPath, $parent->fresh()->profile_photo_path);
        $this->actingAs($parent)->get('/profile')->assertOk()->assertSee(route('profile.photo'));
    }

    public function test_invalid_parent_photo_is_rejected_before_accounts_are_created(): void
    {
        $this->administrator();
        $this->post('/students', $this->enrollment() + ['parent_photo' => UploadedFile::fake()->create('photo.txt', 1, 'text/plain')])
            ->assertSessionHasErrors('parent_photo');
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseMissing('users', ['email' => 'parent@gmail.com']);
    }

    public function test_simple_passwords_are_allowed_for_parent_and_student_modules(): void
    {
        $this->administrator();
        $this->post('/school/parents', ['name' => 'Parent', 'email' => 'parent@gmail.com', 'password' => '123'])->assertSessionHasNoErrors();
        $this->post('/school/users', ['name' => 'Student', 'email' => 'student@gmail.com', 'role' => 'student', 'password' => '123'])->assertSessionHasNoErrors();
        $this->post('/school/users', ['name' => 'Admin', 'email' => 'admin@gmail.com', 'role' => 'administrator', 'password' => '123'])->assertSessionHasErrors('password');
    }

    public function test_enrollment_saves_parent_contact_details_and_automatic_profile_number(): void
    {
        $this->administrator();
        $this->post('/students', $this->enrollment([
            'guardian_phone' => '012345678', 'guardian_gender' => 'female',
            'guardian_address' => 'House 12, Street 5, Phnom Penh, Cambodia',
        ]))->assertSessionHasNoErrors();
        $parent = Student::firstOrFail()->parent;
        $this->assertSame('012345678', $parent->phone);
        $this->assertSame('female', $parent->gender);
        $this->assertSame('House 12, Street 5, Phnom Penh, Cambodia', $parent->address);
        $this->assertSame('PAR'.str_pad((string) $parent->id, 3, '0', STR_PAD_LEFT), $parent->getRawOriginal('profile_number'));
        $this->get('/school/parents')->assertOk()->assertSee('012345678')->assertSee($parent->profile_number)->assertSee('female');
    }

    public function test_existing_linked_parent_keeps_optional_details_when_enrollment_leaves_them_blank(): void
    {
        $this->administrator();
        $parent = User::factory()->create(['email' => 'parent@gmail.com', 'phone' => '099999999', 'gender' => 'male', 'address' => 'Existing home']);
        $parent->forceFill(['role' => 'parent'])->save();
        $password = $parent->password;
        $this->post('/students', $this->enrollment(['parent_id' => $parent->id, 'guardian_gender' => '', 'guardian_address' => '']))->assertSessionHasNoErrors();
        $parent->refresh();
        $this->assertSame('012345678', $parent->phone);
        $this->assertSame('male', $parent->gender);
        $this->assertSame('Existing home', $parent->address);
        $this->assertSame($password, $parent->password);
    }

    public function test_editing_student_updates_linked_parent_details(): void
    {
        $this->administrator();
        $payload = $this->enrollment();
        $this->post('/students', $payload)->assertSessionHasNoErrors();
        $student = Student::firstOrFail();
        $this->put('/students/'.$student->id, array_merge($payload, [
            'parent_id' => $student->parent_id, 'user_id' => $student->user_id,
            'guardian_phone' => '098765432', 'guardian_gender' => 'other', 'guardian_address' => 'Updated home',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('098765432', $student->parent->phone);
        $this->assertSame('other', $student->parent->gender);
        $this->assertSame('Updated home', $student->parent->address);
    }

    public function test_parent_directory_uses_existing_guardian_phone_without_rewriting_old_records(): void
    {
        $this->administrator();
        $parent = User::factory()->create();
        $parent->forceFill(['role' => 'parent', 'phone' => null])->save();
        \Illuminate\Support\Facades\DB::table('users')->where('id', $parent->id)->update(['profile_number' => null]);
        Student::factory()->create(['parent_id' => $parent->id, 'guardian_phone' => '011222333']);
        $this->get('/school/parents')->assertOk()->assertSee('011222333')->assertSee('PAR'.str_pad((string) $parent->id, 3, '0', STR_PAD_LEFT));
        $this->assertNull($parent->fresh()->getRawOriginal('phone'));
        $this->assertNull($parent->fresh()->getRawOriginal('profile_number'));
    }

    public function test_parent_gender_is_validated_before_creating_accounts(): void
    {
        $this->administrator();
        $this->post('/students', $this->enrollment(['guardian_gender' => 'invalid']))->assertSessionHasErrors('guardian_gender');
        $this->assertDatabaseMissing('users', ['email' => 'parent@gmail.com']);
        $this->assertDatabaseCount('students', 0);
    }
}
