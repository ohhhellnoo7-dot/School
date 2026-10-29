<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_name_id_and_email_ignoring_case_and_extra_spaces(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $student = Student::factory()->create([
            'name' => 'Den Vichaka', 'student_number' => 'STU-0042', 'email' => 'Den.Vichaka@school.test',
        ]);
        $other = Student::factory()->create(['name' => 'Unrelated Person']);
        foreach (['den vichaka', 'DEN VICH', '  Den   Vichaka  ', 'Vichaka Den', 'stu-0042', 'STU-004', 'DEN.VICHAKA@SCHOOL.TEST', 'vichaka@school', '0'] as $term) {
            $response = $this->actingAs($admin)->get('/students?'.http_build_query(['search' => $term]));
            $response->assertOk()->assertViewHas('students', fn ($results) => $results->contains('id', $student->id));
        }
        $this->get('/students?search=den')->assertViewHas('students', fn ($results) => ! $results->contains('id', $other->id));
        $this->get('/students?search=%25')->assertViewHas('students', fn ($results) => $results->isEmpty());
    }

    public function test_letter_search_matches_name_prefixes_for_every_letter_and_not_email_contents(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        foreach (range('A', 'Z') as $letter) {
            Student::factory()->create(['name' => $letter.' Student', 'email' => strtolower($letter).'@school.test']);
        }
        foreach (range('A', 'Z') as $letter) {
            $this->actingAs($admin)->get('/students?'.http_build_query(['search' => strtolower($letter)]))
                ->assertOk()->assertViewHas('students', fn ($students) => $students->total() === 1 && $students->first()->name === $letter.' Student');
        }
        $rika = Student::factory()->create(['name' => 'Rika', 'email' => 'rika@gmail.com']);
        Student::factory()->create(['name' => 'Adell Tromp', 'email' => 'other.rika@gmail.com']);
        $this->get('/students?search=Ri')->assertViewHas('students', fn ($students) => $students->modelKeys() === [$rika->id]);
    }

    public function test_search_matches_updated_account_details_and_preserves_visibility_and_filters(): void
    {
        $account = User::factory()->create(['name' => 'Updated Learner', 'email' => 'updated@school.test']);
        $student = Student::factory()->create(['user_id' => $account->id, 'status' => 'active']);
        $other = Student::factory()->create(['name' => 'Updated Learner']);
        foreach (['updated learner', 'UPDATED@SCHOOL.TEST'] as $term) {
            $this->actingAs($account)->get('/students?'.http_build_query(['search' => $term]))
                ->assertOk()->assertViewHas('students', fn ($results) => $results->modelKeys() === [$student->id]);
        }
        $this->get('/students?search=updated&status=inactive')
            ->assertOk()->assertViewHas('students', fn ($results) => $results->isEmpty());
    }

    public function test_teacher_and_parent_name_searches_only_match_the_start_of_the_name(): void
    {
        $admin = User::factory()->create(['name' => 'Administrator', 'role' => 'administrator']);
        $this->actingAs($admin);
        foreach (['teachers' => 'teacher', 'parents' => 'parent'] as $module => $role) {
            $john = User::factory()->create(['role' => $role, 'name' => 'John Carter', 'email' => $role.'.john@school.test', 'status' => 'active']);
            User::factory()->create(['role' => $role, 'name' => 'Mary Jane', 'email' => $role.'.mary@school.test', 'status' => 'active']);
            User::factory()->create(['role' => $role, 'name' => 'Alice Brown', 'email' => $role.'.john.alice@school.test', 'address' => 'John Street', 'status' => 'active']);
            foreach (['j', 'J', 'Jo', '  JOHN  ', 'John C'] as $term) {
                $this->get('/school/'.$module.'?'.http_build_query(['search' => $term]))
                    ->assertOk()->assertViewHas('records', fn ($records) => $records->modelKeys() === [$john->id]);
            }
            $this->get('/school/'.$module.'?'.http_build_query(['search' => $role.'.john@school.test']))
                ->assertOk()->assertViewHas('records', fn ($records) => $records->modelKeys() === [$john->id]);
            $this->get('/school/'.$module.'?search=J&filter=inactive')
                ->assertOk()->assertViewHas('records', fn ($records) => $records->isEmpty());
            $this->get('/school/'.$module.'?search=J')->assertSee('js/directory-search.js');
        }
    }

    public function test_user_account_name_search_matches_prefix_without_email_matches(): void
    {
        $admin = User::factory()->create(['role' => 'administrator', 'name' => 'Administrator']);
        $john = User::factory()->create(['name' => 'John Smith']);
        User::factory()->create(['name' => 'Alice Brown', 'email' => 'john@example.test']);
        $this->actingAs($admin)->get('/school/users?search=j')
            ->assertOk()->assertViewHas('records', fn ($records) => $records->modelKeys() === [$john->id]);
    }
}
