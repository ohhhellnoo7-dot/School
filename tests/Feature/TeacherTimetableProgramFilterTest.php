<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherTimetableProgramFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_program_menu_filters_teachers_and_preserves_the_program_and_academic_year(): void
    {
        $admin = User::factory()->create(['role'=>'administrator']);
        $teachers = collect(['Khmer','English','Chinese'])->mapWithKeys(fn ($program) => [$program=>User::factory()->create(['role'=>'teacher','program'=>$program,'name'=>$program.' Filter Teacher'])]);
        foreach ($teachers as $program=>$teacher) {
            $response = $this->actingAs($admin)->get(route('timetables.index',['view'=>'teachers','program'=>$program,'academic_year'=>'2026-2027']))
                ->assertOk()->assertSee('data-teacher-program-menu',false)->assertSee($teacher->name)
                ->assertSee('name="program" value="'.$program.'"',false)
                ->assertSee(strtoupper($program).' TEACHERS')
                ->assertViewHas('selected',$teacher->id)->assertViewHas('academicYear','2026-2027');
            foreach ($teachers->except($program) as $other) $response->assertDontSee($other->name);
            if ($program === 'Khmer') $response->assertSee('Morning schedule')->assertSee('Afternoon schedule')->assertDontSee('Evening schedule')->assertDontSee('17:30');
            elseif ($program === 'Chinese') $response->assertSee('Evening schedule')->assertDontSee('Morning schedule')->assertDontSee('Afternoon schedule');
            else $response->assertSee('Evening schedule');
            $data = $this->getJson(route('timetables.data',['view'=>'teachers','program'=>$program,'teacher'=>$teacher->id]))->assertOk();
            if ($program === 'Chinese') {
                $this->assertStringContainsString('Evening schedule', $data->json('html'));
                $this->assertStringNotContainsString('Morning schedule', $data->json('html'));
                $this->assertStringNotContainsString('Afternoon schedule', $data->json('html'));
                $this->get(route('timetables.index', ['view'=>'teachers', 'teacher'=>$teacher->id]))
                    ->assertOk()->assertSee('Evening schedule')->assertDontSee('Morning schedule')->assertDontSee('Afternoon schedule');
            }
        }
        $this->get(route('timetables.index',['view'=>'teachers','program'=>'Khmer','teacher'=>$teachers['English']->id]))->assertNotFound();
    }

    public function test_empty_program_does_not_select_another_programs_teacher_and_student_access_is_restricted(): void
    {
        $teacher=User::factory()->create(['role'=>'teacher','program'=>'Khmer']);
        $this->actingAs($teacher)->get(route('timetables.index',['view'=>'teachers','program'=>'Chinese']))->assertOk()
            ->assertSee('No teachers in this program')->assertViewHas('selected',null)->assertViewHas('lessons',fn($lessons)=>$lessons->isEmpty());
        $student=User::factory()->create(['role'=>'student']);
        $this->actingAs($student)->get(route('timetables.index'))->assertOk()->assertDontSee('<div class="dropdown" data-teacher-program-menu>',false);
        $this->get(route('timetables.index',['view'=>'teachers','program'=>'Khmer']))->assertNotFound();
    }

    public function test_session_choices_follow_each_programs_working_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin);
        foreach (['Khmer' => ['Morning', 'Afternoon'], 'English' => ['Morning', 'Afternoon', 'Evening'], 'Chinese' => ['Evening']] as $program => $sessions) {
            User::factory()->create(['role' => 'teacher', 'program' => $program]);
            $this->get(route('timetables.index', ['view' => 'teachers', 'program' => $program]))
                ->assertOk()->assertSee('data-teacher-session-menu', false)
                ->assertViewHas('availableTeacherSessions', $sessions);
            foreach ($sessions as $session) {
                $this->get(route('timetables.index', ['view' => 'teachers', 'program' => $program, 'session' => $session]))
                    ->assertOk()->assertViewHas('teacherSession', $session);
            }
            foreach (array_diff(['Morning', 'Afternoon', 'Evening'], $sessions) as $invalidSession) {
                $this->getJson(route('timetables.data', ['view' => 'teachers', 'program' => $program, 'session' => $invalidSession]))->assertUnprocessable();
            }
        }
    }
}
