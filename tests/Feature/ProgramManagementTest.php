<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, Program, User};
use App\Support\{ProgramSessions, SchoolModules};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_an_unused_program_including_a_default_program(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        Program::create(['name' => 'Japanese', 'sessions' => ['Morning']]);
        $this->get(route('classrooms.index'))->assertOk()->assertSee('Delete Japanese program');
        foreach (['Japanese', 'Chinese'] as $name) {
            $this->delete(route('programs.destroy', ['program' => $name]))
                ->assertRedirect(route('classrooms.index'))->assertSessionHasNoErrors();
            $this->assertDatabaseMissing('programs', ['name' => $name]);
            $this->assertNotContains($name, Program::names());
        }
    }

    public function test_program_in_use_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        \App\Models\SchoolClass::create(['name' => 'Grade 7', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning']);
        $this->delete(route('programs.destroy', ['program' => 'Khmer']))->assertSessionHasErrors('program');
        $this->assertDatabaseHas('programs', ['name' => 'Khmer']);
    }

    public function test_student_cannot_delete_programs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'student']));
        $this->delete(route('programs.destroy', ['program' => 'Chinese']))->assertForbidden();
        $this->assertDatabaseHas('programs', ['name' => 'Chinese']);
    }

    public function test_admin_creates_program_and_can_select_it_for_classes_and_classroom_lessons(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->get(route('programs.create'))->assertOk()->assertSee('Create new program');
        $this->post(route('programs.store'), ['name' => 'Japanese', 'sessions' => ['Afternoon', 'Evening']])
            ->assertSessionHasNoErrors()->assertRedirect(route('classrooms.index', ['program' => 'Japanese']));
        $this->assertSame(['Afternoon', 'Evening'], ProgramSessions::for('Japanese'));
        $this->assertContains('Japanese', SchoolModules::get('classes')['fields']['program']);
        $this->get(route('classrooms.index', ['program' => 'Japanese']))->assertOk()->assertSee('data-program="Japanese"', false);
        $this->get(route('classrooms.create'))->assertOk()->assertSee('Japanese');
        $this->post(route('class-groups.store'), ['name' => 'Grade 1', 'grade' => 1, 'program' => 'Japanese', 'capacity' => 30, 'status' => 'active'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $group = ClassGroup::where('program', 'Japanese')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Afternoon', 'Evening'], $group->sessions()->pluck('session')->all());
        $this->get(route('class-groups.index', ['program' => 'Japanese']))->assertOk()->assertSee('Japanese');
    }

    public function test_program_validation_requires_unique_name_and_valid_sessions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->post(route('programs.store'), ['name' => 'khmer', 'sessions' => ['Morning']])->assertSessionHasErrors('name');
        $this->post(route('programs.store'), ['name' => 'Japanese', 'sessions' => []])->assertSessionHasErrors('sessions');
        $this->post(route('programs.store'), ['name' => 'Japanese', 'sessions' => ['Night']])->assertSessionHasErrors('sessions.0');
        $this->assertSame(3, Program::count());
    }

    public function test_students_cannot_create_programs_and_do_not_see_create_action(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'student']));
        $this->get(route('programs.create'))->assertForbidden();
        $this->post(route('programs.store'), ['name' => 'Japanese', 'sessions' => ['Morning']])->assertForbidden();
        $this->get(route('classrooms.index'))->assertOk()->assertSee('data-program="Khmer"', false)->assertDontSee(route('programs.create'));
    }
}
