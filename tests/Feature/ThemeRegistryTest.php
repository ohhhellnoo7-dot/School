<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_twenty_themes_render_on_login_and_dashboard(): void
    {
        $themes = config('scholar-themes');
        $this->assertCount(20, $themes);
        $this->assertCount(5, array_unique(array_column($themes, 'group')));
        $login = $this->get('/login')->assertOk();
        $dashboard = $this->actingAs(User::factory()->create(['role'=>'administrator']))->get('/dashboard')->assertOk();
        foreach ($themes as $id => $theme) {
            foreach ([$login, $dashboard] as $page) {
                $page->assertSee('data-theme-choice="'.$id.'"', false)->assertSee($theme['name']);
            }
        }
        $login->assertSee('scholar-theme-registry')->assertSee('theme-components.css');
        $dashboard->assertSee('data-theme-search')->assertSee('school-hero');
    }

    public function test_new_palettes_have_complete_tokens_and_readable_body_and_button_text(): void
    {
        $newThemes = array_filter(config('scholar-themes'), fn ($theme) => isset($theme['palette']));
        $this->assertCount(12, $newThemes);
        foreach ($newThemes as $id => $theme) {
            $this->assertCount(8, $theme['palette']);
            foreach ($theme['palette'] as $color) $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/i', $color);
            [$page, , , , $surface, $text, $muted, $action] = $theme['palette'];
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($text, $surface), $id.' body contrast');
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($muted, $surface), $id.' muted contrast');
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($theme['dark'] ? $page : '#FFFFFF', $action), $id.' button contrast');
            $html = view('school.partials.theme-tokens')->render();
            $this->assertStringContainsString(':root[data-theme="'.$id.'"]', $html);
            $this->assertStringContainsString('--auth-shade:', $html);
        }
    }

    private function contrast(string $a, string $b): float
    {
        $luminance = function ($hex) {
            $rgb = array_map(function ($component) {
                $s = hexdec($component) / 255;
                return $s <= .04045 ? $s / 12.92 : (($s + .055) / 1.055) ** 2.4;
            }, str_split(substr($hex, 1), 2));
            return $rgb[0] * .2126 + $rgb[1] * .7152 + $rgb[2] * .0722;
        };
        $x = $luminance($a);
        $y = $luminance($b);
        return (max($x, $y) + .05) / (min($x, $y) + .05);
    }
}
