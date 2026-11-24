<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_khmer_grade_options_include_sections_and_filter_by_the_selected_section(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        foreach (['A', 'B'] as $section) {
            $parent = User::factory()->create();
            $parent->role = 'parent';
            $parent->save();
            $class = \App\Models\SchoolClass::create(['name' => 'Grade 7', 'section' => $section, 'program' => 'Khmer', 'session' => 'Morning']);
            Student::factory()->create(['parent_id' => $parent->id, 'school_class_id' => $class->id]);
        }
        \App\Models\SchoolClass::create(['name' => 'Grade 1', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning']);
        $this->actingAs($admin)->get('/school/parents?program=Khmer&grade=Grade+7%3A%3AB')->assertOk()
            ->assertSee('Grade 7A')->assertSee('Grade 7B')
            ->assertViewHas('parentGrades', fn ($grades) => !$grades->contains(fn ($grade) => $grade->program === 'Khmer' && $grade->name === 'Grade 1'))
            ->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->id === $parent->id);
    }

    public function test_parent_filters_match_program_session_and_grade_on_the_same_child(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        $parents = [];
        foreach ([['Chinese', 'Evening', 'Grade 7'], ['English', 'Morning', 'Grade 7'], ['Khmer', 'Afternoon', 'Grade 8']] as $index => [$program, $session, $grade]) {
            $parent = User::factory()->create();
            $parent->role = 'parent';
            $parent->save();
            $parents[] = $parent;
            $class = \App\Models\SchoolClass::create(['name' => $grade, 'section' => 'A', 'program' => $program, 'session' => $session]);
            Student::factory()->create(['parent_id' => $parent->id, 'school_class_id' => $class->id]);
        }
        // This parent has both programs, but no Chinese child in Grade 8.
        Student::factory()->create(['parent_id' => $parents[0]->id, 'school_class_id' => $class->id]);
        Student::factory()->create(['parent_id' => $parents[0]->id, 'school_class_id' => $class->id]);
        $this->actingAs($admin);
        $this->get('/school/parents')->assertOk()->assertSee('Parents by Program')
            ->assertViewHas('parentSummary', fn ($summary) => $summary['total'] === 3
                && $summary['programs']['Khmer'] === 2
                && $summary['programs']['Chinese'] === 1
                && $summary['programs']['English'] === 1);
        foreach ([['program=Chinese&session=Morning&grade=Grade+7', 0], ['program=English&session=Morning&grade=Grade+7', 1]] as [$query, $index]) {
            $this->get('/school/parents?'.$query)->assertOk()
                ->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->id === $parents[$index]->id);
        }
        $this->get('/school/parents?program=Chinese&grade=Grade+8')->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 0);
        $this->get('/school/parents?session=Afternoon&grade=Grade+8')->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 2);
        $this->get('/school/parents?program=Invalid')->assertSessionHasErrors('program');
    }

    public function test_parent_directory_shows_each_childs_recorded_attendance_for_today_only(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        $parent = User::factory()->create();
        $parent->role = 'parent';
        $parent->save();
        foreach (['present', 'late', 'absent', null] as $index => $status) {
            $child = Student::factory()->create(['parent_id' => $parent->id, 'name' => 'Child '.$index]);
            Attendance::create(['student_id' => $child->id, 'date' => today()->subDay(), 'status' => 'excused']);
            if ($status) {
                Attendance::create(['student_id' => $child->id, 'date' => today(), 'status' => $status]);
            }
        }
        $this->actingAs($admin)->get('/school/parents')->assertOk()
            ->assertSeeTextInOrder(["Today's attendance", 'Children'])
            ->assertSeeTextInOrder(['Child 0', 'Present', 'Child 1', 'Late', 'Child 2', 'Absent', 'Child 3', 'Not recorded'])
            ->assertDontSee('Excused')
            ->assertViewHas('records', fn ($records) => $records->first()->children->every(fn ($child) =>
                $child->relationLoaded('attendances') && $child->attendances->every(fn ($attendance) => $attendance->date->isToday())));

        $yesterday = today()->subDay();
        $this->get('/school/parents?date='.$yesterday->toDateString())->assertOk()
            ->assertSee($yesterday->format('M d, Y'))
            ->assertSee('Excused')
            ->assertDontSee("Today's attendance")
            ->assertViewHas('records', fn ($records) => $records->first()->children->every(fn ($child) =>
                $child->attendances->count() === 1 && $child->attendances->first()->date->isSameDay($yesterday)));

        $this->get('/school/parents?date=2026-02-30')->assertSessionHasErrors('date');
    }
}
