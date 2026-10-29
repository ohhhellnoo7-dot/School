<?php

namespace Tests\Feature;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ProgramClassSeeder;
use Database\Seeders\TeacherProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramClassTest extends TestCase
{
    use RefreshDatabase;

    public function test_chinese_students_move_to_matching_evening_classes_and_filters_force_evening(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        $morning = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A', 'program' => 'Chinese', 'session' => 'Morning']);
        $afternoon = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A', 'program' => 'Chinese', 'session' => 'Afternoon']);
        $evening = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A', 'program' => 'Chinese', 'session' => 'Evening']);
        $missing = SchoolClass::create(['name' => 'Grade 9', 'section' => 'B', 'program' => 'Chinese', 'session' => 'Morning']);
        $khmer = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning']);
        $students = collect([$morning, $afternoon, $evening, $missing, $khmer])
            ->map(fn ($class) => Student::factory()->create(['school_class_id' => $class->id]));

        $migration = require database_path('migrations/2026_10_27_000005_move_chinese_students_to_evening.php');
        $migration->up();
        $migration->up();

        foreach ($students->take(3) as $student) {
            $this->assertSame($evening->id, $student->fresh()->school_class_id);
        }
        $this->assertSame('Evening', $students[3]->fresh()->schoolClass->session);
        $this->assertSame('Grade 9', $students[3]->fresh()->schoolClass->name);
        $this->assertSame('B', $students[3]->fresh()->schoolClass->section);
        $this->assertSame($khmer->id, $students[4]->fresh()->school_class_id);

        foreach (['', '&session=Morning', '&session=Afternoon'] as $query) {
            $this->actingAs($admin)->get('/students?program=Chinese&class='.$morning->id.$query)
                ->assertOk()->assertViewHas('students', fn ($records) => $records->total() === 4
                    && $records->every(fn ($student) => $student->schoolClass->session === 'Evening'));
        }
        $this->actingAs($admin)->get('/students/create')->assertOk()
            ->assertViewHas('classes', fn ($classes) => $classes->where('program', 'Chinese')->every(fn ($class) => $class->session === 'Evening'));
    }

    public function test_student_filters_clear_a_class_from_another_program_or_session(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        $khmer = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning']);
        $chinese = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A', 'program' => 'Chinese', 'session' => 'Morning']);
        $evening = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Evening']);
        $student = Student::factory()->create(['school_class_id' => $khmer->id]);
        $this->actingAs($admin);
        foreach ([$chinese, $evening, $khmer] as $selected) {
            $this->get('/students?program=Khmer&session=Morning&class='.$selected->id)
                ->assertOk()
                ->assertViewHas('students', fn ($students) => $students->total() === 1 && $students->first()->id === $student->id)
                ->assertSee('data-program="Khmer" data-session="Morning"', false)
                ->assertSee('js/student-filters.js');
        }
    }

    public function test_khmer_classes_never_offer_or_return_the_evening_session(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'administrator'])->save();
        $morning = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning']);
        SchoolClass::create(['name' => 'Grade 8', 'section' => 'B', 'program' => 'Khmer', 'session' => 'Evening']);

        $response = $this->actingAs($admin)->get('/school/classes?filter=Khmer&session=Evening');

        $response->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 1 && $records->first()->is($morning))
            ->assertSee('js/class-filters.js');
        $this->assertNull(request('session'));

        $this->get('/school/classes/create')
            ->assertOk()
            ->assertSee('js/class-form-sessions.js');
    }

    public function test_invalid_chinese_and_khmer_session_classes_are_removed_when_empty(): void
    {
        $chineseMorning = SchoolClass::create(['name' => 'Grade 12', 'section' => 'A', 'program' => 'Chinese', 'session' => 'Morning']);
        $chineseAfternoon = SchoolClass::create(['name' => 'Grade 12', 'section' => 'A', 'program' => 'Chinese', 'session' => 'Afternoon']);
        $chineseEvening = SchoolClass::create(['name' => 'Grade 12', 'section' => 'A', 'program' => 'Chinese', 'session' => 'Evening']);
        $khmerEvening = SchoolClass::create(['name' => 'Grade 12', 'section' => 'B', 'program' => 'Khmer', 'session' => 'Evening']);
        $khmerMorning = SchoolClass::create(['name' => 'Grade 12', 'section' => 'B', 'program' => 'Khmer', 'session' => 'Morning']);

        $migration = require database_path('migrations/2026_10_29_000002_remove_invalid_program_session_classes.php');
        $migration->up();

        foreach ([$chineseMorning, $chineseAfternoon, $khmerEvening] as $removed) {
            $this->assertDatabaseMissing('school_classes', ['id' => $removed->id]);
        }
        foreach ([$chineseEvening, $khmerMorning] as $preserved) {
            $this->assertDatabaseHas('school_classes', ['id' => $preserved->id]);
        }
    }

    public function test_program_filters_show_classes_and_language_programs_cover_all_grades_and_sessions(): void
    {
        foreach (User::factory()->count(30)->create() as $teacher) {
            $teacher->role = 'teacher';
            $teacher->save();
        }
        $this->seed(TeacherProgramSeeder::class);
        $legacy = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A']);
        $student = Student::factory()->create(['school_class_id' => $legacy->id]);
        $this->seed(ProgramClassSeeder::class);
        $snapshot = SchoolClass::orderBy('id')->get()->toJson();
        $this->seed(ProgramClassSeeder::class);
        $this->assertSame($snapshot, SchoolClass::orderBy('id')->get()->toJson());
        $this->assertSame('Khmer', $legacy->fresh()->program);
        $this->assertSame($legacy->id, $student->fresh()->school_class_id);

        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        $this->actingAs($admin);
        foreach (['Khmer' => 1, 'English' => 72, 'Chinese' => 24] as $program => $count) {
            $this->get('/school/classes?filter='.$program)->assertOk()
                ->assertViewHas('records', fn ($records) => $records->total() === $count && $records->every(fn ($class) => $class->program === $program));
            $classes = SchoolClass::with('teacher')->where('program', $program)->get();
            $this->assertTrue($classes->every(fn ($class) => $class->teacher->program === $program));
            if ($program !== 'Khmer') {
                foreach (range(1, 12) as $grade) {
                    $sessions = $program === 'Chinese' ? ['Evening'] : array_keys(SchoolClass::SESSION_HOURS);
                    foreach ($sessions as $session) {
                        $this->assertSame(2, $classes->where('name', 'Grade '.$grade)->where('session', $session)->count());
                    }
                }
            }
        }

        $data = ['name' => 'Grade 7', 'section' => 'A', 'program' => 'English', 'session' => 'Morning', 'capacity' => 40, 'room' => 'EN-7C'];
        $this->post('/school/classes', $data)->assertSessionHasErrors('section');
        $this->post('/school/classes', array_replace($data, ['section' => 'C']))->assertSessionHasNoErrors();
        $this->post('/school/classes', array_replace($data, ['section' => 'C', 'program' => 'Chinese']))->assertSessionHasErrors('session');
        $this->post('/school/classes', array_replace($data, ['section' => 'C', 'program' => 'Chinese', 'session' => 'Evening']))->assertSessionHasNoErrors();
        $this->post('/school/classes', array_replace($data, ['section' => 'C', 'session' => 'Evening']))->assertSessionHasNoErrors();
    }
}
