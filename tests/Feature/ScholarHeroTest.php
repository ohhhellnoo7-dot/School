<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ScholarHeroTest extends TestCase
{
    public function test_shared_hero_preserves_content_and_actions_for_every_registered_theme(): void
    {
        foreach (config('scholar-themes') as $id => $theme) {
            $html = Blade::render('<x-scholar-hero class="subject-hero" aria-labelledby="title"><h1 id="title">Subjects</h1><a href="/subjects/create">Add subject</a></x-scholar-hero>');
            $this->assertStringContainsString('scholar-hero', $html, $id);
            $this->assertStringContainsString('subject-hero', $html, $id);
            $this->assertStringContainsString('aria-labelledby="title"', $html, $id);
            $this->assertStringContainsString('href="/subjects/create"', $html, $id);
            $this->assertStringContainsString('</section>', $html, $id);
        }
    }

    public function test_all_existing_banner_templates_use_the_shared_component(): void
    {
        foreach (['teachers/index', 'students/index', 'modules/index', 'class-groups/index', 'classrooms/show', 'reports', 'finance/_nav', 'timetables/index', 'partials/dashboard-content', 'partials/student-home'] as $view) {
            $source = file_get_contents(resource_path('views/school/'.$view.'.blade.php'));
            $this->assertStringContainsString('<x-scholar-hero', $source, $view);
            $this->assertSame(substr_count($source, '<x-scholar-hero'), substr_count($source, '</x-scholar-hero>'), $view);
        }
    }
}
