<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeacherManagementService;
use Database\Seeders\JapanTeacherSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JapanTeacherSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_twelve_teachers_with_five_qualifications_without_duplicates(): void
    {
        $this->seed(JapanTeacherSeeder::class);
        $this->seed(JapanTeacherSeeder::class);
        $teachers = User::where('role', 'teacher')->where('program', 'Japan')->get();
        $this->assertCount(12, $teachers);
        foreach ($teachers as $teacher) {
            $this->assertSame(JapanTeacherSeeder::SUBJECTS, app(TeacherManagementService::class)->qualifications($teacher)->all());
            $this->assertTrue($teacher->requiresTeacherConfirmation());
        }
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->get('/school/teachers?program=Japan')->assertOk()->assertSee('Japan Teacher 01');
    }
}
