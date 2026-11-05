<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, SchoolClass, Student, User};
use App\Services\StudentStudySessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StudentStudySessionsTest extends TestCase
{
    use RefreshDatabase;

    private function schoolClass(string $program, string $session, int $grade = 7): SchoolClass
    {
        $group = ClassGroup::create(['name' => $program.' '.$grade, 'program' => $program, 'grade' => $grade, 'section' => $program === 'Khmer' ? 'A' : null, 'status' => 'active', 'capacity' => 30]);
        return SchoolClass::create(['name' => $group->name, 'program' => $program, 'session' => $session, 'class_group_id' => $group->id, 'section' => $group->section ?? '']);
    }

    public function test_student_can_register_three_programs_with_one_profile_and_view_all_timetables(): void
    {
        $khmer = $this->schoolClass('Khmer', 'Morning');
        $english = $this->schoolClass('English', 'Afternoon');
        $chinese = $this->schoolClass('Chinese', 'Evening');
        $account = User::factory()->create(['role' => 'student']);
        $student = Student::factory()->create(['user_id' => $account->id, 'school_class_id' => $khmer->id, 'status' => 'active']);
        $count = Student::count();
        $this->actingAs($account);
        foreach ([$english, $chinese] as $class) {
            $this->post(route('students.study-sessions.store', $student), ['additional_class_id' => $class->id])->assertSessionHasNoErrors()->assertRedirect(route('students.show', $student));
        }
        $this->assertSame($count, Student::count());
        $this->assertEquals($khmer->id, $student->fresh()->school_class_id);
        $this->assertCount(3, $student->studyClasses);
        foreach ([$khmer, $english, $chinese] as $class) {
            $this->assertSame(1, $class->students()->count());
            $this->assertSame(1, $class->classGroup->students()->count());
            $this->get(route('classes.timetable', $class))->assertOk();
        }
        $this->get(route('students.show', $student))->assertOk()->assertSee('Study sessions')->assertSee('Chinese');
        $this->get(route('students.index', ['program' => 'English', 'session' => 'Afternoon']))->assertOk()->assertSee($student->name);
        $this->get(route('timetables.index'))->assertOk();
    }

    public function test_duplicate_or_overlapping_session_rolls_back_existing_enrollments(): void
    {
        $khmer = $this->schoolClass('Khmer', 'Morning');
        $english = $this->schoolClass('English', 'Morning');
        $student = Student::factory()->create(['school_class_id' => $khmer->id]);
        foreach ([[$khmer->id], [$english->id]] as $ids) {
            try {
                app(StudentStudySessionService::class)->sync($student, $ids);
                $this->fail('Expected invalid session rejection.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('additional_class_ids', $error->errors());
            }
            $this->assertSame([$khmer->id], $student->studyClasses()->pluck('school_classes.id')->all());
        }
    }

    public function test_other_students_and_parents_cannot_register_another_student(): void
    {
        $primary = $this->schoolClass('Khmer', 'Morning');
        $extra = $this->schoolClass('English', 'Afternoon');
        $student = Student::factory()->create(['school_class_id' => $primary->id, 'status' => 'active']);
        foreach (['student', 'parent', 'teacher'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->post(route('students.study-sessions.store', $student), ['additional_class_id' => $extra->id])->assertForbidden();
        }
        $this->assertSame(1, $student->studyClasses()->count());
    }

    public function test_full_class_is_rejected_and_teacher_can_see_additional_enrollment(): void
    {
        $primary = $this->schoolClass('Khmer', 'Morning');
        $extra = $this->schoolClass('English', 'Afternoon');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $extra->update(['teacher_id' => $teacher->id]);
        $extra->classGroup->update(['capacity' => 1]);
        $student = Student::factory()->create(['school_class_id' => $primary->id, 'status' => 'active']);
        $other = Student::factory()->create(['school_class_id' => $extra->id, 'status' => 'active']);
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->post(route('students.study-sessions.store', $student), ['additional_class_id' => $extra->id])->assertSessionHasErrors('additional_class_ids');
        $other->update(['status' => 'inactive']);
        app(StudentStudySessionService::class)->sync($student, [$extra->id]);
        $this->assertTrue(Student::visibleTo($teacher)->whereKey($student->id)->exists());
    }

    public function test_admin_form_saves_extra_sessions_and_invalid_changes_roll_back_profile_edits(): void
    {
        $primary = $this->schoolClass('Khmer', 'Morning');
        $extra = $this->schoolClass('English', 'Afternoon');
        $student = Student::factory()->create(['school_class_id' => $primary->id, 'status' => 'active']);
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->get(route('students.edit', $student))->assertOk()->assertSee('Add study session');
        $data = $student->only(['name', 'gender', 'email', 'phone', 'address', 'school_class_id', 'guardian_name', 'guardian_phone', 'status']);
        $data += ['date_of_birth' => $student->date_of_birth->toDateString(), 'enrollment_date' => $student->enrollment_date->toDateString(), 'study_sessions_submitted' => 1, 'additional_class_ids' => [$extra->id]];
        $this->put(route('students.update', $student), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $student->studyClasses()->count());
        $originalName = $student->name;
        $data['name'] = 'Should not save';
        $data['additional_class_ids'] = [$primary->id];
        $this->put(route('students.update', $student), $data)->assertSessionHasErrors('additional_class_ids');
        $this->assertSame($originalName, $student->fresh()->name);
        $this->assertSame(2, $student->studyClasses()->count());
    }

    public function test_room_roster_includes_additional_enrollments_and_hides_other_families(): void
    {
        $primary = $this->schoolClass('Khmer', 'Morning');
        $extra = $this->schoolClass('English', 'Afternoon');
        $room = \App\Models\Classroom::create(['name' => 'E07', 'room_number' => 'E07', 'capacity' => 30, 'building' => 'B', 'status' => 'active']);
        $session = \App\Models\ClassSession::create(['class_group_id' => $extra->class_group_id, 'session' => 'Afternoon', 'starts_at' => '13:00', 'ends_at' => '17:00']);
        \App\Models\ClassroomBooking::create(['class_group_id' => $extra->class_group_id, 'class_session_id' => $session->id, 'classroom_id' => $room->id, 'day_of_week' => 'Monday', 'starts_at' => '13:00', 'ends_at' => '17:00']);
        $account = User::factory()->create(['role' => 'student']);
        $parent = User::factory()->create(['role' => 'parent', 'name' => 'Roster Guardian']);
        $student = Student::factory()->create(['name' => 'Roster Student', 'user_id' => $account->id, 'parent_id' => $parent->id, 'school_class_id' => $primary->id, 'status' => 'active']);
        app(StudentStudySessionService::class)->sync($student, [$extra->id]);
        $other = Student::factory()->create(['name' => 'Other Family Child', 'guardian_name' => 'Private Guardian Name', 'school_class_id' => $extra->id, 'status' => 'active']);
        $outside = Student::factory()->create(['name' => 'Outside This Room', 'school_class_id' => $primary->id, 'status' => 'active']);
        $url = route('classrooms.show', ['classroom' => $room, 'date' => '2026-11-02', 'time' => '13:30']);
        $this->actingAs(User::factory()->create(['role' => 'administrator']));
        $this->get($url)->assertOk()->assertSee('Roster Student')->assertSee($student->student_number)->assertSee('Roster Guardian')->assertSee('Other Family Child')->assertDontSee('Outside This Room');
        foreach ([$account, $parent] as $viewer) {
            $this->actingAs($viewer)->get($url)->assertOk()->assertSee('Roster Student')->assertSee('Roster Guardian')->assertDontSee('Other Family Child')->assertDontSee('Private Guardian Name')->assertDontSee('Outside This Room');
        }
    }
}
