<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\TeacherProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherProgramTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_session_and_class_filters_include_class_and_subject_teachers(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        $teachers = User::factory()->count(3)->create(['program' => 'Khmer']);
        foreach ($teachers as $teacher) {
            $teacher->role = 'teacher';
            $teacher->save();
        }
        $morning = \App\Models\SchoolClass::create(['name' => 'Grade 7', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning', 'teacher_id' => $teachers[0]->id]);
        $afternoon = \App\Models\SchoolClass::create(['name' => 'Grade 7', 'section' => 'B', 'program' => 'Khmer', 'session' => 'Afternoon', 'teacher_id' => $teachers[2]->id]);
        \App\Models\Subject::create(['name' => 'Math', 'code' => 'M7A', 'school_class_id' => $morning->id, 'teacher_id' => $teachers[1]->id]);
        $this->actingAs($admin)->get('/school/teachers?program=Khmer&session=Morning&class='.$morning->id)->assertOk()
            ->assertSee('teacher-session')->assertSee('teacher-class')
            ->assertViewHas('records', fn ($records) => $records->total() === 2 && !$records->contains('id', $teachers[2]->id));
        $this->get('/school/teachers?program=Khmer&session=Afternoon&class='.$afternoon->id)->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->id === $teachers[2]->id);
        $this->get('/school/teachers?session=Invalid')->assertSessionHasErrors('session');
        $this->get('/school/teachers?class=999999')->assertSessionHasErrors('class');
    }

    public function test_thirty_teachers_are_divided_equally_and_can_be_filtered_by_program(): void
    {
        $teachers = User::factory()->count(30)->create();
        foreach ($teachers as $teacher) {
            $teacher->role = 'teacher';
            $teacher->save();
        }
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();

        $this->seed(TeacherProgramSeeder::class);
        $snapshot = $teachers->map(fn ($teacher) => $teacher->fresh()->program)->all();
        $this->seed(TeacherProgramSeeder::class);
        $this->assertSame($snapshot, $teachers->map(fn ($teacher) => $teacher->fresh()->program)->all());
        $this->assertNull($admin->fresh()->program);

        foreach (['Khmer', 'English', 'Chinese'] as $program) {
            $this->assertSame(10, User::where('role', 'teacher')->where('program', $program)->count());
            $this->actingAs($admin)->get('/school/teachers?program='.$program)
                ->assertOk()->assertViewHas('records', fn ($records) => $records->total() === 10 && $records->every(fn ($teacher) => $teacher->program === $program));
        }
        $this->get('/school/teachers?program=Invalid')->assertSessionHasErrors('program');
    }

    public function test_distribution_requires_thirty_teachers_before_changing_any_program(): void
    {
        $teacher = User::factory()->create(['program' => 'Chinese']);
        $teacher->role = 'teacher';
        $teacher->save();
        try {
            $this->seed(TeacherProgramSeeder::class);
            $this->fail('Expected an incorrect teacher count to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('exactly 30', $exception->getMessage());
        }
        $this->assertSame('Chinese', $teacher->fresh()->program);
    }
}
