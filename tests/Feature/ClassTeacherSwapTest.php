<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, ClassSession, Classroom, ClassroomBooking, Schedule, SchoolClass, Subject, User};
use App\Services\{ClassroomBookingService, TeacherManagementService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassTeacherSwapTest extends TestCase
{
    use RefreshDatabase;

    private function cohort(int $grade): array
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'Chinese', 'status' => 'active']);
        $group = ClassGroup::create(['name' => 'C0'.$grade, 'program' => 'Chinese', 'grade' => $grade, 'teacher_id' => $teacher->id, 'capacity' => 30, 'status' => 'active']);
        $session = ClassSession::create(['class_group_id' => $group->id, 'session' => 'Evening', 'starts_at' => '17:30', 'ends_at' => '19:30']);
        $class = SchoolClass::create(['class_group_id' => $group->id, 'name' => $group->name, 'program' => 'Chinese', 'section' => '', 'session' => 'Evening', 'teacher_id' => $teacher->id]);
        $subject = Subject::create(['name' => 'Writing', 'code' => 'W'.$grade, 'school_class_id' => $class->id, 'teacher_id' => $teacher->id]);
        app(TeacherManagementService::class)->saveProfile($teacher, ['Writing'], 1200, [
            ['weekday' => 'Monday', 'start_time' => '17:30', 'end_time' => '19:30', 'unavailable' => false],
        ]);
        $room = Classroom::create(['name' => $group->name, 'room_number' => $group->name, 'building' => 'A', 'capacity' => 30, 'status' => 'active']);
        $lesson = Schedule::withoutEvents(fn () => Schedule::create(['school_class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
            'classroom_id' => $room->id, 'room' => $room->room_number, 'day' => 'Monday', 'starts_at' => '17:30', 'ends_at' => '18:30', 'academic_year' => '2026-2027', 'entry_type' => 'academic']));
        $booking = app(ClassroomBookingService::class)->create(['class_group_id' => $group->id, 'class_session_id' => $session->id, 'classroom_id' => $room->id,
            'teacher_id' => $teacher->id, 'day_of_week' => 'Monday', 'starts_at' => '17:30', 'ends_at' => '18:30']);
        return compact('teacher', 'group', 'class', 'subject', 'room', 'lesson', 'booking');
    }

    private function requestSwap(array $a, array $b)
    {
        return $this->post(route('class-groups.swap-teacher', $a['group']), [
            'swap_class_group_id' => $b['group']->id, 'source_teacher_id' => $a['teacher']->id, 'target_teacher_id' => $b['teacher']->id,
        ]);
    }

    public function test_admin_can_swap_both_teachers_and_all_their_assignments(): void
    {
        $a = $this->cohort(3);
        $b = $this->cohort(6);
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->get(route('class-groups.edit', $a['group']))->assertOk()->assertSee('Swap class teachers')->assertSee('Room C06');
        $this->requestSwap($a, $b)->assertSessionHasNoErrors()->assertRedirect(route('class-groups.edit', $a['group']));
        foreach ([[$a, $b], [$b, $a]] as [$from, $to]) {
            foreach (['group', 'class', 'subject', 'lesson', 'booking'] as $key) {
                $this->assertEquals($to['teacher']->id, $from[$key]->fresh()->teacher_id, $key);
            }
            $this->assertEquals($from['room']->id, $from['lesson']->fresh()->classroom_id);
        }
    }

    public function test_unavailable_teacher_rolls_back_both_sides(): void
    {
        $a = $this->cohort(3);
        $b = $this->cohort(6);
        // Teacher B can no longer cover the incoming Monday lesson.
        \Illuminate\Support\Facades\DB::table('teacher_working_periods')->where('teacher_id', $b['teacher']->id)->update(['unavailable' => true]);
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->requestSwap($a, $b)->assertSessionHasErrors('swap_class_group_id');
        foreach ([$a, $b] as $cohort) {
            foreach (['group', 'class', 'subject', 'lesson', 'booking'] as $key) {
                $this->assertEquals($cohort['teacher']->id, $cohort[$key]->fresh()->teacher_id);
            }
        }
    }

    public function test_stale_assignments_and_non_admin_requests_are_rejected(): void
    {
        $a = $this->cohort(3);
        $b = $this->cohort(6);
        $this->actingAs($a['teacher']);
        $this->requestSwap($a, $b)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $a['group']->update(['teacher_id' => null]);
        $this->requestSwap($a, $b)->assertSessionHasErrors('swap_class_group_id');
        $this->assertEquals($b['teacher']->id, $b['group']->fresh()->teacher_id);
    }

    public function test_external_lesson_conflict_cancels_the_swap(): void
    {
        $a = $this->cohort(3);
        $b = $this->cohort(6);
        $other = $this->cohort(9);
        Schedule::whereKey($a['lesson']->id)->update(['starts_at' => '18:30', 'ends_at' => '19:30']);
        $a['booking']->update(['starts_at' => '18:30', 'ends_at' => '19:30']);
        Schedule::whereKey($other['lesson']->id)->update(['teacher_id' => $b['teacher']->id, 'starts_at' => '18:30', 'ends_at' => '19:30']);
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->requestSwap($a, $b)->assertSessionHasErrors('swap_class_group_id');
        foreach ([$a, $b] as $cohort) {
            $this->assertEquals($cohort['teacher']->id, $cohort['group']->fresh()->teacher_id);
            $this->assertEquals($cohort['teacher']->id, $cohort['lesson']->fresh()->teacher_id);
        }
    }

    public function test_changing_a_japanese_class_teacher_updates_lessons_and_reservations(): void
    {
        $a = $this->cohort(3);
        $a['teacher']->update(['program' => 'Japan']);
        $a['class']->update(['program' => 'Japan']);
        $a['group']->update(['program' => 'Japan']);
        $replacement = User::factory()->create(['role' => 'teacher', 'program' => 'Japan', 'status' => 'active']);
        app(TeacherManagementService::class)->saveProfile($replacement, ['Writing'], 1200, [
            ['weekday' => 'Monday', 'start_time' => '17:30', 'end_time' => '19:30', 'unavailable' => false],
        ]);
        app(\App\Services\JapanClassTeacherService::class)->assign($a['group'], $replacement->id);
        foreach (['group', 'class', 'subject', 'lesson', 'booking'] as $key) {
            $this->assertEquals($replacement->id, $a[$key]->fresh()->teacher_id);
        }
        $other = ClassGroup::create(['name' => 'Other', 'program' => 'Japan', 'grade' => 8, 'teacher_id' => $a['teacher']->id, 'status' => 'active']);
        try {
            app(\App\Services\JapanClassTeacherService::class)->assign($a['group'], $a['teacher']->id);
            $this->fail('Expected busy teacher rejection.');
        } catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertArrayHasKey('teacher_id', $error->errors());
        }
        $this->assertEquals($replacement->id, $a['lesson']->fresh()->teacher_id);
        $this->assertEquals($replacement->id, $a['group']->fresh()->teacher_id);
    }

    private function khmerCohort(int $grade): array
    {
        $cohort = $this->cohort($grade);
        $cohort['booking']->delete();
        $cohort['teacher']->update(['program' => 'Khmer']);
        $cohort['group']->update(['program' => 'Khmer', 'section' => 'A']);
        $cohort['class']->update(['program' => 'Khmer', 'session' => 'Morning', 'section' => 'A']);
        ClassSession::where('class_group_id', $cohort['group']->id)->update(['session' => 'Morning', 'starts_at' => '07:00', 'ends_at' => '11:00']);
        Schedule::whereKey($cohort['lesson']->id)->update(['starts_at' => '07:00', 'ends_at' => '08:00']);
        \Illuminate\Support\Facades\DB::table('teacher_working_periods')->where('teacher_id', $cohort['teacher']->id)->update(['start_time' => '07:00', 'end_time' => '11:00']);
        return $cohort;
    }

    private function requestSubjectSwap(array $a, array $b)
    {
        return $this->post(route('class-groups.swap-subject-teacher', $a['group']), [
            'source_subject_id' => $a['subject']->id, 'target_subject_id' => $b['subject']->id,
            'source_teacher_id' => $a['teacher']->id, 'target_teacher_id' => $b['teacher']->id,
        ]);
    }

    public function test_khmer_subject_swap_moves_only_the_subject_and_its_lessons(): void
    {
        $a = $this->khmerCohort(7);
        $b = $this->khmerCohort(8);
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->get(route('class-groups.edit', $a['group']))->assertOk()->assertSee('Swap subject teachers');
        $this->requestSubjectSwap($a, $b)->assertSessionHasNoErrors();
        foreach ([[$a, $b], [$b, $a]] as [$from, $to]) {
            $this->assertEquals($to['teacher']->id, $from['subject']->fresh()->teacher_id);
            $this->assertEquals($to['teacher']->id, $from['lesson']->fresh()->teacher_id);
            $this->assertEquals($from['teacher']->id, $from['group']->fresh()->teacher_id);
            $this->assertEquals($from['room']->id, $from['lesson']->fresh()->classroom_id);
        }
    }

    public function test_khmer_subject_swap_rejects_different_times_and_rolls_back_unavailable_teachers(): void
    {
        $a = $this->khmerCohort(7);
        $b = $this->khmerCohort(8);
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        Schedule::whereKey($b['lesson']->id)->update(['starts_at' => '08:00', 'ends_at' => '09:00']);
        $this->requestSubjectSwap($a, $b)->assertSessionHasErrors('subject_swap');
        Schedule::whereKey($b['lesson']->id)->update(['starts_at' => '07:00', 'ends_at' => '08:00']);
        \Illuminate\Support\Facades\DB::table('teacher_working_periods')->where('teacher_id', $b['teacher']->id)->update(['unavailable' => true]);
        $this->requestSubjectSwap($a, $b)->assertSessionHasErrors('subject_swap');
        foreach ([$a, $b] as $cohort) {
            $this->assertEquals($cohort['teacher']->id, $cohort['subject']->fresh()->teacher_id);
            $this->assertEquals($cohort['teacher']->id, $cohort['lesson']->fresh()->teacher_id);
        }
        $this->actingAs($a['teacher']);
        $this->requestSubjectSwap($a, $b)->assertForbidden();
    }
}

