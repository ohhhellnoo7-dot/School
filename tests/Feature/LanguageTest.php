<?php

namespace Tests\Feature;

use Tests\TestCase;

class LanguageTest extends TestCase
{
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
