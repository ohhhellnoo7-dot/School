<?php

namespace Tests\Feature;

use App\Models\User;
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
}
