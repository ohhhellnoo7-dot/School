<?php

namespace Tests\Feature;

use Tests\TestCase;

class LanguageTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    public function test_teacher_schedule_and_live_status_use_selected_language(): void
    {
        $teacher = \App\Models\User::factory()->create(['program' => 'Khmer']);
        $teacher->forceFill(['role' => 'teacher'])->save();
        foreach (['km', 'zh'] as $locale) {
            $response = $this->actingAs($teacher)->withSession(['locale' => $locale])
                ->get(route('teachers.schedule', $teacher))->assertOk();
            foreach (['Teacher schedule', 'School Timetables', 'Main menu', 'Subject', 'Reset', 'Designated Free Periods:', 'Print Schedule', 'No lesson'] as $key) {
                $translated = __($key, [], $locale);
                $this->assertNotSame($key, $translated);
                $response->assertSee($translated);
            }
            $this->getJson(route('teachers.schedule.status', $teacher))->assertOk()
                ->assertJsonPath('current', __('No lesson', [], $locale))
                ->assertJsonPath('next', __('No lesson', [], $locale));
        }
    }

    public function test_selected_language_persists_and_renders(): void
    {
        foreach (['km', 'zh', 'en'] as $locale) {
            $this->from('/login')->post('/language', ['locale' => $locale])
                ->assertRedirect('/login')->assertSessionHas('locale', $locale);
            $this->get('/login')->assertOk()
                ->assertSee('lang="'.$locale.'"', false)
                ->assertSee(__('Sign in', [], $locale));
        }
    }

    public function test_invalid_language_does_not_replace_preference(): void
    {
        $this->withSession(['locale' => 'km'])->post('/language', ['locale' => 'invalid'])
            ->assertSessionHasErrors('locale')->assertSessionHas('locale', 'km');
    }
}
