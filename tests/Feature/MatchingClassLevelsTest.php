<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, Classroom, Program, SchoolClass, Subject, TeacherAttendance, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MatchingClassLevelsTest extends TestCase
{
    use RefreshDatabase;

    public static function programs(): array
    {
        return [
            'Khmer stays same grade' => ['Khmer', 3, [3], false],
            'English level 3' => ['English', 3, [2, 3, 4], false],
            'English level 4' => ['English', 4, [3, 4, 5], false],
            'Chinese' => ['Chinese', 3, [2, 3, 4], false],
            'Japan' => ['Japan', 4, [3, 4, 5], false],
            'new program default' => ['Korean', 3, [2, 3, 4], false],
            'first level boundary' => ['English', 1, [1, 2], false],
            'legacy Level names' => ['English', 3, [2, 3, 4], true],
        ];
    }

    #[DataProvider('programs')]
    public function test_matching_and_selection_follow_the_program_level_rule(string $program, int $level, array $expected, bool $legacy): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin);
        Program::firstOrCreate(['name' => $program], ['sessions' => ['Evening']]);
        Program::firstOrCreate(['name' => 'Other program'], ['sessions' => ['Evening']]);
        $session = $program === 'Khmer' ? 'Afternoon' : 'Evening';
        $make = function (int $classLevel, string $section, string $classProgram, string $subjectName = 'Reading') use ($session, $legacy) {
            $teacher = User::factory()->create(['role' => 'teacher', 'program' => $classProgram, 'status' => 'active']);
            $room = Classroom::create(['name' => $classProgram.$classLevel.$section, 'room_number' => $classProgram.$classLevel.$section, 'capacity' => 40, 'status' => 'active']);
            $group = $legacy ? null : ClassGroup::create(['name' => 'Level '.$classLevel, 'grade' => $classLevel, 'program' => $classProgram, 'section' => $section, 'capacity' => 40, 'status' => 'active']);
            $class = SchoolClass::create(['name' => 'Level '.$classLevel, 'section' => $section, 'class_group_id' => $group?->id, 'program' => $classProgram, 'session' => $session]);
            $subject = Subject::create(['name' => $subjectName, 'code' => $room->room_number, 'school_class_id' => $class->id]);
            $schedule = DB::table('schedules')->insertGetId(['school_class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
                'classroom_id' => $room->id, 'room' => $room->room_number, 'day' => 'Monday', 'starts_at' => '16:30', 'ends_at' => '17:00', 'entry_type' => 'academic', 'academic_year' => '2026-2027']);
            return compact('teacher', 'room', 'schedule');
        };
        $source = $make($level, 'SOURCE', $program);
        TeacherAttendance::create(['teacher_id' => $source['teacher']->id, 'date' => '2026-11-09', 'status' => 'absent', 'recorded_by' => $admin->id]);
        $options = [];
        foreach (range(1, 6) as $candidateLevel) $options[$candidateLevel] = $make($candidateLevel, 'A', $program);
        $make($level, 'OTHER', 'Other program');
        $make($level, 'WRONG-SUBJECT', $program, 'Writing');
        $response = $this->get(route('classrooms.matching-class', ['classroom' => $source['room'], 'date' => '2026-11-09', 'time' => '16:40']))->assertOk();
        $this->assertEqualsCanonicalizing(array_map(fn ($number) => $options[$number]['room']->id, $expected), $response->viewData('matches')->pluck('classroom.id')->all());
        if ($program !== 'Khmer' && $level > 1) $response->assertSee('1 level lower')->assertSee('1 level higher');
        if ($program === 'Khmer') $response->assertSee('Khmer matches must have the same grade')->assertDontSee('1 level lower');
        $choice = $options[$program === 'Khmer' ? $level : $level + 1];
        $this->post(route('classrooms.study-with', $source['room']), ['date' => '2026-11-09', 'time' => '16:40', 'source_schedule_id' => $source['schedule'],
            'target_schedule_id' => $choice['schedule'], 'target_classroom_id' => $choice['room']->id])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('temporary_class_studies', ['source_schedule_id' => $source['schedule'], 'target_schedule_id' => $choice['schedule']]);
    }
}
