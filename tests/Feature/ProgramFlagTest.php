<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ProgramFlagTest extends TestCase
{
    public function test_known_programs_and_future_french_program_render_local_flags(): void
    {
        foreach (['Khmer'=>'km', 'English'=>'en', 'Chinese'=>'zh', 'Japan'=>'jp', 'Japanese'=>'jp', 'French'=>'fr', ' french '=>'fr'] as $program => $flag) {
            $html = Blade::render('<x-program-flag :program="$program" />', compact('program'));
            $this->assertStringContainsString('images/flags/'.$flag.'.svg', $html);
            $this->assertFileExists(public_path('images/flags/'.$flag.'.svg'));
        }
    }

    public function test_unknown_programs_have_a_fallback_without_a_broken_image(): void
    {
        $html = Blade::render('<x-program-flag program="New program" />');
        $this->assertStringContainsString('bi-globe2', $html);
        $this->assertStringNotContainsString('<img', $html);
    }
}
