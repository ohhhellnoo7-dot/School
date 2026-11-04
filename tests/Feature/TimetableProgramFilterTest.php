<?php

namespace Tests\Feature;

use App\Models\{Classroom, Program, SchoolClass, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimetableProgramFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_program_appears_in_teacher_menu_and_filters_teachers(): void
    {
        Program::create(['name' => 'Japanese', 'sessions' => ['Evening']]);
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'Japanese']);
        User::factory()->create(['role' => 'teacher', 'program' => 'English']);
        $this->actingAs($admin)->get(route('timetables.index', ['view' => 'teachers', 'program' => 'Japanese']))
            ->assertOk()->assertSee('Japanese')->assertViewHas('selected', $teacher->id)
            ->assertViewHas('teachers', fn ($teachers) => $teachers->modelKeys() === [$teacher->id]);
    }

    public function test_program_filters_cohorts_rooms_and_shared_room_lessons_and_resets_stale_choices(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $rooms = [];
        $classes = [];
        foreach (['Khmer', 'English'] as $index => $program) {
            $classes[$program] = SchoolClass::create(['name' => 'Grade 1', 'section' => $program, 'program' => $program, 'session' => 'Morning']);
            $rooms[$program] = Classroom::create(['name' => $program.' room', 'room_number' => 'FILTER-'.$index, 'capacity' => 30]);
            DB::table('schedules')->insert(['school_class_id' => $classes[$program]->id, 'classroom_id' => $rooms[$program]->id, 'entry_type' => 'relaxation', 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'room' => $rooms[$program]->room_number]);
        }
        DB::table('schedules')->insert(['school_class_id' => $classes['English']->id, 'classroom_id' => $rooms['Khmer']->id, 'entry_type' => 'relaxation', 'day' => 'Tuesday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'room' => $rooms['Khmer']->room_number]);
        $this->actingAs($admin)->get(route('timetables.index', ['program' => 'Khmer', 'class' => $classes['English']->id]))
            ->assertOk()->assertViewHas('studentClasses', fn ($classes) => $classes->count() === 1 && $classes->first()->program === 'Khmer')
            ->assertViewHas('selected', $classes['Khmer']->id);
        $this->get(route('timetables.index', ['view' => 'classrooms', 'program' => 'Khmer', 'classroom' => $rooms['English']->id]))
            ->assertOk()->assertViewHas('rooms', fn ($choices) => $choices->modelKeys() === [$rooms['Khmer']->id])
            ->assertViewHas('selected', $rooms['Khmer']->id)
            ->assertViewHas('lessons', fn ($lessons) => $lessons->count() === 1 && $lessons->first()->school_class_id === $classes['Khmer']->id);
        $this->getJson(route('timetables.data', ['view' => 'classrooms', 'program' => 'Chinese']))
            ->assertOk()->assertJsonMissingPath('error');
        $this->get(route('timetables.index', ['view' => 'classrooms', 'program' => 'Chinese']))
            ->assertOk()->assertSee('No classrooms in this program')->assertViewHas('selected', null)
            ->assertViewHas('lessons', fn ($lessons) => $lessons->isEmpty());
    }
}
