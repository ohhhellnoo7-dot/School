<?php
namespace Tests\Feature;

use App\Models\{ClassGroup, ClassSession, Classroom, Schedule, SchoolClass, Student, Subject, User};
use App\Services\{ClassroomBookingService, RoomLessonService, RotationTimetableGenerator, TeacherManagementService, TimetableDiagnostics, TimetableRoomReconciliation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimetableRebuildTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $program = 'Khmer', string $sessionName = 'Morning'): array
    {
        [$from, $to] = $sessionName === 'Evening' ? ['17:30', '18:30'] : ['07:00', '11:00'];
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => $program, 'status' => 'active']);
        $group = ClassGroup::create(['name' => 'Grade 9', 'grade' => 9, 'section' => 'A', 'program' => $program, 'status' => 'active']);
        $session = ClassSession::create(['class_group_id' => $group->id, 'session' => $sessionName, 'starts_at' => $from, 'ends_at' => $to]);
        $class = SchoolClass::create(['class_group_id' => $group->id, 'name' => 'Grade 9', 'section' => 'A', 'program' => $program, 'session' => $sessionName, 'room' => 'LEGACY-9A']);
        $subject = Subject::create(['school_class_id' => $class->id, 'teacher_id' => $teacher->id, 'name' => 'Mathematics with a very long subject name that must wrap without overlapping its teacher classroom or adjacent lessons', 'code' => 'LONG-MATH', 'required_weekly_minutes' => 120, 'lesson_minutes' => 120]);
        $room = Classroom::create(['room_number' => $program === 'Chinese' ? 'C09' : 'E09', 'name' => 'Physical Classroom '.$program.' 9', 'status' => 'active', 'capacity' => 40]);
        $periods = [];
        foreach (TeacherManagementService::DAYS as $day) {
            app(ClassroomBookingService::class)->create(['class_group_id' => $group->id, 'class_session_id' => $session->id, 'classroom_id' => $room->id, 'teacher_id' => null, 'day_of_week' => $day, 'starts_at' => $from, 'ends_at' => $to]);
            $periods[] = ['weekday' => $day, 'start_time' => $from, 'end_time' => $to, 'unavailable' => false];
        }
        app(TeacherManagementService::class)->saveProfile($teacher, [$subject->name], 1200, $periods);
        return compact('admin', 'teacher', 'group', 'session', 'class', 'subject', 'room');
    }

    private function legacy(array $f, string $start = '07:00', string $end = '09:00', ?int $room = null): Schedule
    {
        $id = DB::table('schedules')->insertGetId(['subject_id' => $f['subject']->id, 'school_class_id' => $f['class']->id, 'teacher_id' => $f['teacher']->id, 'entry_type' => 'academic', 'day' => 'Monday', 'starts_at' => $start, 'ends_at' => $end, 'room' => 'EN-9A', 'classroom_id' => $room, 'created_at' => now(), 'updated_at' => now()]);
        return Schedule::with(['subject', 'schoolClass.classGroup.sessions', 'teacher', 'classroom'])->findOrFail($id);
    }

    public function test_reservations_and_room_names_only_propose_an_id_and_never_assign_a_room(): void
    {
        $f = $this->fixture();
        $lesson = $this->legacy($f);
        $room = $f['room']->load('bookings.classSession');
        $this->assertCount(0, app(RoomLessonService::class)->lessons($room, collect([$lesson])));
        $description = app(TimetableRoomReconciliation::class)->describe($lesson);
        $this->assertFalse($description['confirmed']);
        $this->assertSame($room->id, $description['proposed_id']);
        $this->assertStringContainsString('does not establish', $description['reason']);
        $this->actingAs($f['admin'])->get(route('timetables.index', ['view' => 'students', 'class' => $f['class']->id]))->assertOk()->assertSee('Room assignment needs review.')->assertSee('Fix Room')->assertDontSee('Review room link');
        $this->get(route('timetables.index', ['view' => 'classrooms', 'classroom' => $room->id]))->assertOk()->assertDontSee('data-lesson-id="'.$lesson->id.'"', false);
        $this->assertNull($lesson->fresh()->classroom_id);
    }

    public function test_room_corrections_require_confirmation_preserve_history_and_keep_the_same_shared_id(): void
    {
        $f = $this->fixture();
        $lesson = $this->legacy($f);
        $original = $lesson->getAttributes();
        $this->actingAs($f['admin'])->get(route('timetables.rooms.review', ['lesson' => $lesson->id]))->assertOk()->assertSee('EN-9A')->assertSee('E09');
        $this->post(route('timetables.rooms.preview'), ['selected' => [$lesson->id], 'rooms' => [$lesson->id => $f['room']->id]])->assertOk()->assertSee('Room Correction Preview');
        $token = session('room_correction_preview.token');
        $this->assertSame($original, $lesson->fresh()->getAttributes());
        $this->post(route('timetables.rooms.confirm'), ['token' => $token])->assertSessionHasErrors('confirm');
        $this->post(route('timetables.rooms.confirm'), ['token' => $token, 'confirm' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $corrected = $lesson->fresh();
        $this->assertSame($f['room']->id, $corrected->classroom_id);
        $this->assertSame('E09', $corrected->room);
        foreach (['teacher_id', 'subject_id', 'school_class_id', 'day', 'starts_at', 'ends_at'] as $key) $this->assertSame($original[$key], $corrected->getRawOriginal($key));
        $this->assertDatabaseHas('lesson_room_corrections', ['schedule_id' => $lesson->id, 'previous_room' => 'EN-9A', 'previous_classroom_id' => null, 'classroom_id' => $f['room']->id, 'corrected_by' => $f['admin']->id]);
        foreach ([['view' => 'teachers', 'teacher' => $f['teacher']->id], ['view' => 'students', 'class' => $f['class']->id], ['view' => 'classrooms', 'classroom' => $f['room']->id]] as $query) {
            $html = $this->get(route('timetables.index', $query))->assertOk()->assertSee('data-lesson-id="'.$lesson->id.'"', false)->assertSee('Room E09')->assertDontSee('Recorded room:')->getContent();
            $this->assertSame(1, substr_count($html, 'data-lesson-id="'.$lesson->id.'"'));
        }
        $this->assertSame(1, Schedule::count());
        $this->post(route('timetables.rooms.confirm'), ['token' => $token, 'confirm' => 1])->assertSessionHasErrors();
    }

    public function test_room_correction_stale_preview_and_non_admin_access_are_rejected(): void
    {
        $f = $this->fixture(); $lesson = $this->legacy($f);
        $this->actingAs($f['teacher'])->get(route('timetables.rooms.review'))->assertForbidden();
        $this->post(route('timetables.rooms.preview'), ['selected' => [$lesson->id], 'rooms' => [$lesson->id => $f['room']->id]])->assertForbidden();
        $this->actingAs($f['admin'])->post(route('timetables.rooms.preview'), ['selected' => [$lesson->id], 'rooms' => [$lesson->id => $f['room']->id]])->assertOk();
        $token = session('room_correction_preview.token');
        $f['room']->update(['capacity' => 45]);
        $this->post(route('timetables.rooms.confirm'), ['token' => $token, 'confirm' => 1])->assertSessionHasErrors();
        $this->assertNull($lesson->fresh()->classroom_id);
        $this->assertSame(0, DB::table('lesson_room_corrections')->count());
    }

    public function test_overlaps_render_one_conflict_block_instead_of_repeating_or_stacking_cards(): void
    {
        $f = $this->fixture();
        $first = $this->legacy($f, '07:00', '09:00', $f['room']->id);
        $second = $this->legacy($f, '08:00', '10:00', $f['room']->id);
        foreach ([['view' => 'teachers', 'teacher' => $f['teacher']->id], ['view' => 'students', 'class' => $f['class']->id], ['view' => 'classrooms', 'classroom' => $f['room']->id]] as $query) {
            $html = $this->actingAs($f['admin'])->get(route('timetables.index', $query))->assertOk()->assertSee('CONFLICT DETECTED')->assertSee('2 lessons assigned')->assertSee('View Conflict')->assertSee('rowspan="3"', false)->getContent();
            $this->assertSame(1, substr_count($html, 'class="timetable-conflict-card"'));
            $this->assertSame(0, substr_count($html, 'data-lesson-id="'.$first->id.'"'));
        }
        $this->get(route('timetables.conflicts', $first))->assertOk()->assertSee('same teacher cannot teach')->assertSee('same student cohort')->assertSee('same physical classroom')->assertSee('Fix Conflict');
        $this->assertSame(2, Schedule::count());
        $this->post(route('timetables.rooms.preview'), ['selected' => [$first->id, $second->id], 'rooms' => [$first->id => $f['room']->id, $second->id => $f['room']->id]])->assertSessionHasErrors();
    }

    public function test_nonconflicting_two_hour_cards_render_once_and_long_titles_are_preserved(): void
    {
        $f = $this->fixture(); $lesson = $this->legacy($f, '07:00', '09:00', $f['room']->id);
        $html = $this->actingAs($f['admin'])->get(route('timetables.index', ['view' => 'students', 'class' => $f['class']->id]))->assertOk()->assertSee('rowspan="2"', false)->assertSee($f['subject']->name)->assertSee('two-hour-entry')->getContent();
        $this->assertSame(1, substr_count($html, 'data-lesson-id="'.$lesson->id.'"'));
        $this->assertStringNotContainsString('CONFLICT DETECTED', $html);
    }

    public function test_english_and_chinese_generation_use_the_actual_evening_window(): void
    {
        foreach (['English', 'Chinese'] as $program) {
            $f = $this->fixture($program, 'Evening');
            $f['subject']->update(['code' => $program.'-MATH']);
            $plan = app(RotationTimetableGenerator::class)->preview($f['class']);
            foreach ($plan['rows'] as $row) { $this->assertSame('17:30', substr($row['starts_at'], 0, 5)); $this->assertSame('18:30', substr($row['ends_at'], 0, 5)); }
            app(RotationTimetableGenerator::class)->confirm($plan);
            $this->actingAs($f['admin'])->get(route('timetables.index', ['view' => 'students', 'class' => $f['class']->id]))->assertOk()->assertSee('17:30')->assertDontSee('<strong>16:30</strong>', false);
        }
    }

    public function test_out_of_window_legacy_lessons_are_reported_once_and_never_clipped_or_hidden(): void
    {
        $f = $this->fixture('English', 'Evening'); $lesson = $this->legacy($f, '16:30', '18:30');
        $html = $this->actingAs($f['admin'])->get(route('timetables.index', ['view' => 'students', 'class' => $f['class']->id]))->assertOk()->assertSee('Lessons outside the configured grid')->assertSee('16:30')->getContent();
        $this->assertSame(1, substr_count($html, 'data-lesson-id="'.$lesson->id.'"'));
        $this->assertSame('16:30', substr($lesson->fresh()->starts_at, 0, 5));
        $this->get(route('timetables.conflicts', $lesson))->assertOk()->assertSee('outside its configured program/session hours');
    }
}
