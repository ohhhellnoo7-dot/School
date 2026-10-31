<?php

namespace Tests\Feature;

use App\Models\{Classroom, SchoolClass, Subject, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnglishTeacherSessionFilterTest extends TestCase
{
    use RefreshDatabase;

    private function lesson(User $teacher, string $session, string $year = '2026-2027', bool $cancelled = false): void
    {
        $class = SchoolClass::firstOrCreate(['name' => 'Grade 5', 'section' => 'A', 'program' => 'English', 'session' => $session]);
        $subject = Subject::firstOrCreate(['name' => 'English Grammar', 'code' => 'FILTER-'.$class->id, 'school_class_id' => $class->id]);
        $room = Classroom::firstOrCreate(['name' => 'English room '.$class->id, 'room_number' => 'E'.$class->id, 'capacity' => 40, 'status' => 'active']);
        [$from, $to] = ['Morning' => ['07:00', '08:00'], 'Afternoon' => ['15:00', '16:00'], 'Evening' => ['17:30', '18:30']][$session];
        DB::table('schedules')->insert(['school_class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'classroom_id' => $room->id, 'entry_type' => 'academic', 'day' => 'Monday', 'starts_at' => $from, 'ends_at' => $to, 'room' => $room->room_number, 'academic_year' => $year, 'cancelled_at' => $cancelled ? now() : null]);
    }

    public function test_session_menu_filters_teacher_options_grid_and_live_refresh_and_preserves_year(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teachers = [];
        foreach (['Morning', 'Afternoon', 'Evening'] as $session) {
            $teachers[$session] = User::factory()->create(['role' => 'teacher', 'program' => 'English', 'name' => $session.' Session Teacher']);
            $this->lesson($teachers[$session], $session);
        }
        $old = User::factory()->create(['role' => 'teacher', 'program' => 'English', 'name' => 'Previous Year Teacher']);
        $this->lesson($old, 'Evening', '2025-2026');
        $cancelled = User::factory()->create(['role' => 'teacher', 'program' => 'English', 'name' => 'Cancelled Teacher']);
        $this->lesson($cancelled, 'Evening', '2026-2027', true);
        $this->actingAs($admin);
        foreach ($teachers as $session => $teacher) {
            // Switching session while another teacher is selected chooses an eligible teacher.
            $params = ['view' => 'teachers', 'program' => 'English', 'session' => $session, 'teacher' => $teachers['Afternoon']->id, 'academic_year' => '2026-2027'];
            $response = $this->get(route('timetables.index', $params))->assertOk()
                ->assertSee('data-teacher-session-menu', false)->assertSee('Session: '.$session)
                ->assertSee('name="session" value="'.$session.'"', false)
                ->assertSee($teacher->name)->assertViewHas('selected', $teacher->id)
                ->assertViewHas('academicYear', '2026-2027')->assertViewHas('teacherSession', $session)
                ->assertSee($session.' schedule')->assertDontSee($old->name)->assertDontSee($cancelled->name);
            foreach ($teachers as $otherSession => $other) if ($otherSession !== $session) {
                $response->assertDontSee($other->name)->assertDontSee($otherSession.' schedule');
            }
            $html = $this->getJson(route('timetables.data', $params))->assertOk()->json('html');
            $this->assertStringContainsString($session.' schedule', $html);
            foreach (array_diff(['Morning', 'Afternoon', 'Evening'], [$session]) as $otherSession) $this->assertStringNotContainsString($otherSession.' schedule', $html);
        }
        $this->get(route('timetables.index', ['view' => 'teachers', 'program' => 'English']))->assertOk()->assertViewHas('teachers', fn ($rows) => $rows->count() === 5);
    }

    public function test_empty_session_has_no_teacher_or_lessons_and_rejects_invalid_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'English']);
        $this->lesson($teacher, 'Morning');
        $this->actingAs($admin)->get(route('timetables.index', ['view' => 'teachers', 'program' => 'English', 'session' => 'Evening', 'teacher' => $teacher->id]))
            ->assertOk()->assertSee('No teachers in this session')->assertViewHas('selected', null)
            ->assertViewHas('lessons', fn ($rows) => $rows->isEmpty());
        $this->getJson(route('timetables.data', ['view' => 'teachers', 'program' => 'English', 'session' => 'Invalid']))->assertUnprocessable();
    }
}
