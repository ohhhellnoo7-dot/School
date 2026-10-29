<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_directory_reports_assignments_and_filters_by_class_and_teacher(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'name' => 'Mira Educator']);
        $class = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning']);
        $other = SchoolClass::create(['name' => 'Grade 8', 'section' => 'B', 'program' => 'Khmer', 'session' => 'Morning']);
        $assigned = Subject::create(['name' => 'Mathematics', 'code' => 'M7', 'school_class_id' => $class->id, 'teacher_id' => $teacher->id]);
        Subject::create(['name' => 'Literature', 'code' => 'L8', 'school_class_id' => $other->id]);

        $this->actingAs($admin)->get('/school/subjects')
            ->assertOk()->assertSee('subject-hero')->assertSee('Add subject')
            ->assertViewHas('subjectSummary', ['total' => 2, 'assigned' => 1, 'unassigned' => 1, 'classes' => 2]);
        $this->get('/school/subjects?class='.$class->id.'&teacher='.$teacher->id)
            ->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->id === $assigned->id);
        $this->get('/school/subjects?class='.$other->id.'&teacher='.$teacher->id)
            ->assertOk()->assertViewHas('records', fn ($records) => $records->isEmpty());
        $this->get('/school/subjects?search=Mira')->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->id === $assigned->id);
        $this->get('/school/subjects?class=999999')->assertSessionHasErrors('class');
    }
}
