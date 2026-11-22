<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_twenty_four_themes_render_on_login_and_dashboard(): void
    {
        $themes = config('scholar-themes');
        $this->assertCount(24, $themes);
        $this->assertCount(7, array_unique(array_column($themes, 'group')));
        $login = $this->get('/login')->assertOk();
        $dashboard = $this->actingAs(User::factory()->create(['role'=>'administrator']))->get('/dashboard')->assertOk();
        foreach ($themes as $id => $theme) {
            foreach ([$login, $dashboard] as $page) {
                $page->assertSee('data-theme-choice="'.$id.'"', false)->assertSee($theme['name']);
            }
        }
        $login->assertSee('scholar-theme-registry')->assertSee('theme-components.css')->assertSee('christmas.js')->assertSee('christmas.css');
        $dashboard->assertSee('data-theme-search')->assertSee('school-hero')->assertSee('christmas.js')->assertSee('christmas.css');
        foreach ([$login, $dashboard] as $page) {
            $page->assertSee('khmer-apsara.css')->assertSee('khmer-apsara.js')->assertSee('data-apsara-image=', false);
            $page->assertSee('ocean-life.css')->assertSee('ocean-life.js');
            $page->assertSee('ocean-shark.css')->assertSee('ocean-shark.js')->assertDontSee('js/ocean-mermaid.js');
            $page->assertSee('ocean-surface.css')->assertSee('ocean-surface.js');
            $page->assertSee('autumn.css')->assertSee('autumn.js');
            $page->assertSee('windy-breeze.css');
            $page->assertSee('class="windy-breeze-air" aria-hidden="true"', false);
        }
        $this->get(route('employee-salaries.index'))->assertOk()
            ->assertSee('class="windy-breeze-air" aria-hidden="true"', false);
    }

    public function test_new_palettes_have_complete_tokens_and_readable_body_and_button_text(): void
    {
        $newThemes = array_filter(config('scholar-themes'), fn ($theme) => isset($theme['palette']));
        $this->assertCount(16, $newThemes);
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

    public function test_windy_breeze_dashboard_and_attendance_render_for_each_school_role(): void
    {
        foreach (['administrator', 'teacher', 'student'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $dashboard = $this->actingAs($user)->get(route('dashboard'))->assertOk()
                ->assertSee('windy-landscape--sidebar', false)
                ->assertSee('windy-turbine-rotor', false);
            if ($role === 'student') {
                $dashboard->assertSee('Today’s lessons')->assertDontSee('Student attendance summary');
                $this->followingRedirects()->get(route('modules.index', 'attendance'))->assertOk()
                    ->assertSee('My attendance')->assertSee('Attendance Calendar')->assertDontSee(['Daily attendance', 'More filters']);
                continue;
            }
            $dashboard->assertSee('Student attendance summary');
            $this->get(route('modules.index', 'attendance'))->assertOk()
                ->assertSee('student-attendance-hero windy-only', false)
                ->assertSee('Attendance list')->assertSee('More filters')->assertSee('All sessions');
            if ($role === 'teacher') {
                $dashboard->assertDontSee('windy-landscape--hero', false)
                    ->assertSee('teacher-dashboard-hero windy-only', false)
                    ->assertSee('dashboard-heading school-hero windy-original', false)
                    ->assertSee('teacher-greeting-emoji', false);
            } else {
                $dashboard->assertSee('windy-landscape--hero', false);
            }
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
