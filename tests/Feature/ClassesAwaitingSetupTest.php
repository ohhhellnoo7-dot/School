<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, ClassSession, Classroom, SchoolClass, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassesAwaitingSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_unassigned_classes_appear_in_setup_filter_without_physical_rooms(): void
    {
        $group = $this->pendingClass();
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $html = $this->getJson(route('classrooms.data', ['program' => 'Khmer', 'grade' => 12]))->assertOk()->json('html');
        $this->assertStringContainsString('Classes awaiting setup', $html);
        $this->assertStringContainsString('data-awaiting-class="'.$group->id.'"', $html);
        $this->assertStringContainsString('Not assigned', $html);
        $this->assertStringContainsString('Not created yet', $html);
        $this->assertStringContainsString(route('class-groups.show', $group), $html);
        $filtered = $this->getJson(route('classrooms.data', ['program' => 'Khmer', 'grade' => 11]))->assertOk()->json('html');
        $this->assertStringNotContainsString('data-awaiting-class="'.$group->id.'"', $filtered);
    }

    public function test_unassigned_classes_are_not_exposed_to_teachers(): void
    {
        $group = $this->pendingClass();
        $this->actingAs(User::factory()->create(['role' => 'teacher', 'program' => 'Khmer']));
        $html = $this->getJson(route('classrooms.data'))->assertOk()->json('html');
        $this->assertStringNotContainsString('data-awaiting-class="'.$group->id.'"', $html);
        $this->assertStringNotContainsString('data-lesson-filter="awaiting"', $html);
    }

    public function test_classes_with_rooms_assigned_directly_to_lessons_are_not_awaiting_setup(): void
    {
        $group = ClassGroup::create(['name' => 'C01', 'grade' => 1, 'program' => 'Japan', 'capacity' => 30, 'status' => 'active']);
        ClassSession::create(['class_group_id' => $group->id, 'session' => 'Evening', 'starts_at' => '17:30', 'ends_at' => '18:30']);
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'Japan']);
        $class = SchoolClass::create(['name' => 'C01', 'section' => '', 'program' => 'Japan', 'session' => 'Evening', 'class_group_id' => $group->id, 'teacher_id' => $teacher->id]);
        $room = Classroom::create(['name' => 'Japan room', 'room_number' => 'J01', 'building' => 'A', 'capacity' => 30, 'status' => 'active']);
        $lessonId = DB::table('schedules')->insertGetId(['school_class_id' => $class->id, 'teacher_id' => $teacher->id, 'classroom_id' => $room->id, 'room' => $room->room_number, 'day' => 'Monday', 'starts_at' => '17:30', 'ends_at' => '18:30', 'academic_year' => '2026-2027', 'entry_type' => 'academic']);
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $url = route('classrooms.data', ['date' => '2026-11-16', 'time' => '17:30']);
        $html = $this->getJson($url)->assertOk()->json('html');
        $this->assertStringNotContainsString('data-awaiting-class="'.$group->id.'"', $html);
        DB::table('schedules')->where('id', $lessonId)->update(['cancelled_at' => now()]);
        $html = $this->getJson($url)->assertOk()->json('html');
        $this->assertStringContainsString('data-awaiting-class="'.$group->id.'"', $html);
    }

    private function pendingClass(): ClassGroup
    {
        $group = ClassGroup::create(['name' => '12F', 'grade' => 12, 'section' => 'F', 'program' => 'Khmer', 'stream' => 'science', 'capacity' => 30, 'status' => 'active']);
        ClassSession::create(['class_group_id' => $group->id, 'session' => 'Morning', 'starts_at' => '07:00', 'ends_at' => '11:00']);
        return $group;
    }
}
