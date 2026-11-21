<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendancePeopleTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_attendance_uses_the_teachers_scheduled_classes(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer']);
        $other = User::factory()->create(['role' => 'teacher', 'program' => 'Khmer']);
        $ownClass = SchoolClass::create(['name' => 'Own class', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Afternoon', 'teacher_id' => $other->id]);
        $otherClass = SchoolClass::create(['name' => 'Other class', 'section' => 'A', 'program' => 'Khmer', 'teacher_id' => $teacher->id]);
        $subject = \App\Models\Subject::create(['name' => 'Mathematics', 'code' => 'MATH-ATT', 'school_class_id' => $ownClass->id, 'teacher_id' => $teacher->id]);
        \App\Models\Schedule::create([
            'subject_id' => $subject->id, 'day' => 'Monday', 'starts_at' => '13:00', 'ends_at' => '14:00', 'room' => 'A',
            'academic_year' => \App\Support\AcademicYear::current(),
        ]);
        \App\Models\Subject::create(['name' => 'English', 'code' => 'ENG-ATT', 'school_class_id' => $otherClass->id, 'teacher_id' => $teacher->id]);
        $ownStudent = Student::factory()->create(['name' => 'Own Class Student', 'school_class_id' => $ownClass->id]);
        $otherStudent = Student::factory()->create(['name' => 'Other Class Student', 'school_class_id' => $otherClass->id]);
        foreach ([$ownStudent, $otherStudent] as $student) {
            $record = Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'present']);
        }

        $this->actingAs($teacher)->get(route('modules.index', 'attendance'))->assertOk()
            ->assertSee($ownStudent->name)->assertDontSee($otherStudent->name)
            ->assertSee('Own class A')->assertSee('Afternoon')->assertDontSee('Other class');
        $this->get(route('modules.index', ['module' => 'attendance', 'class' => $ownClass->id, 'session' => 'Afternoon']))
            ->assertOk()->assertSee($ownStudent->name)->assertSee('data-session="Afternoon"', false);
        $this->get(route('modules.index', ['module' => 'attendance', 'class' => $ownClass->id, 'session' => 'Morning']))
            ->assertOk()->assertDontSee($ownStudent->name)->assertDontSee('data-session="Afternoon"', false);
        $this->get(route('modules.show', ['attendance', $record->id]))->assertNotFound();
        $this->get(route('modules.create', 'attendance'))->assertOk()
            ->assertSee($ownStudent->name)->assertDontSee($otherStudent->name);
        $this->post(route('modules.store', 'attendance'), ['student_id' => $otherStudent->id, 'date' => today()->toDateString(), 'status' => 'present'])
            ->assertSessionHasErrors('student_id');
        $this->get(route('attendance.people', ['kind' => 'students', 'status' => 'present']))->assertOk()
            ->assertSee($ownStudent->name)->assertDontSee($otherStudent->name);
    }

    public function test_live_name_search_matches_only_the_start_for_students_and_teachers(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        foreach (['Sophia Chen', 'Skye Haley', 'William Scott', 'Alexander Stanton'] as $name) {
            $student = Student::factory()->create(['name' => $name]);
            Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'present']);
            $teacher = User::factory()->create(['name' => $name, 'role' => 'teacher']);
            TeacherAttendance::create(['teacher_id' => $teacher->id, 'date' => today(), 'status' => 'present', 'recorded_by' => $admin->id]);
        }
        foreach (['students', 'teachers'] as $kind) {
            $this->actingAs($admin)->get(route('attendance.people', ['kind' => $kind, 'status' => 'present', 'search' => 's', 'name_part' => 'last']))
                ->assertOk()->assertSee('William Scott')->assertSee('Alexander Stanton')->assertDontSee('Sophia Chen')->assertDontSee('Skye Haley')
                ->assertViewHas('records', fn ($records) => $records->total() === 2);
            $this->actingAs($admin)->get(route('attendance.people', ['kind' => $kind, 'status' => 'present', 'search' => 's']))
                ->assertOk()->assertSee('Sophia Chen')->assertSee('Skye Haley')->assertDontSee('William Scott')->assertDontSee('Alexander Stanton')
                ->assertViewHas('records', fn ($records) => $records->total() === 2);
            $this->get(route('attendance.people', ['kind' => $kind, 'status' => 'present', 'search' => 'So']))
                ->assertOk()->assertSee('Sophia Chen')->assertDontSee('Skye Haley')
                ->assertViewHas('records', fn ($records) => $records->total() === 1);
        }
    }

    public function test_student_program_name_and_first_letter_filters_work_together(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $khmer = SchoolClass::create(['name' => 'Khmer class', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning']);
        $english = SchoolClass::create(['name' => 'English class', 'section' => 'A', 'program' => 'English', 'session' => 'Morning']);
        foreach ([['Alice Green', $khmer], ['Adam Brown', $khmer], ['Bella Green', $khmer], ['Amy Green', $english]] as [$name, $class]) {
            $student = Student::factory()->create(['name' => $name, 'school_class_id' => $class->id]);
            $student->studyClasses()->syncWithoutDetaching([$class->id]);
            Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'present']);
        }
        $base = ['kind' => 'students', 'status' => 'present', 'session' => 'Morning'];
        $this->actingAs($admin)->get(route('attendance.people', $base + ['program' => 'Khmer', 'letter' => 'A', 'search' => 'ali']))
            ->assertOk()->assertSee('Alice Green')->assertDontSee('Adam Brown')->assertDontSee('Bella Green')->assertDontSee('Amy Green')
            ->assertViewHas('records', fn ($records) => $records->total() === 1);
        $this->get(route('attendance.people', $base + ['letter' => 'A']))->assertOk()
            ->assertSee('Alice Green')->assertSee('Adam Brown')->assertSee('Amy Green')->assertDontSee('Bella Green');
        $this->get(route('attendance.people', $base + ['letter' => 'Z']))->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 0);
        $this->getJson(route('attendance.people', $base + ['letter' => 'AB']))->assertUnprocessable();
    }

    public function test_status_lists_respect_date_session_and_account_access(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $parent = User::factory()->create(['role' => 'parent']);
        $teacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $otherTeacher = User::factory()->create(['role' => 'teacher', 'status' => 'active']);
        $morning = SchoolClass::create(['name' => 'Morning class', 'section' => 'A', 'session' => 'Morning', 'teacher_id' => $teacher->id]);
        $evening = SchoolClass::create(['name' => 'Evening class', 'section' => 'A', 'session' => 'Evening', 'teacher_id' => $otherTeacher->id]);
        $child = Student::factory()->create(['name' => 'My absent child', 'parent_id' => $parent->id, 'school_class_id' => $morning->id]);
        $private = Student::factory()->create(['name' => 'Private absent student', 'school_class_id' => $evening->id]);
        foreach ([$child, $private] as $student) {
            Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'absent']);
        }
        foreach ([$teacher, $otherTeacher] as $person) {
            TeacherAttendance::create(['teacher_id' => $person->id, 'date' => today(), 'status' => 'absent', 'recorded_by' => $admin->id]);
        }
        $url = route('attendance.people', ['kind' => 'students', 'status' => 'absent', 'session' => 'Morning']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('My absent child')->assertDontSee('Private absent student');
        foreach (['present', 'late', 'excused'] as $status) {
            $this->get(route('attendance.people', ['kind' => 'students', 'status' => $status]))->assertOk()->assertDontSee('My absent child');
        }
        $this->get($url.'&date='.today()->subDay()->toDateString())->assertOk()->assertDontSee('My absent child');
        $this->actingAs($parent)->get(route('attendance.people', ['kind' => 'students', 'status' => 'absent']))->assertOk()->assertSee('My absent child')->assertDontSee('Private absent student');
        $teachersUrl = route('attendance.people', ['kind' => 'teachers', 'status' => 'absent']);
        $this->get($teachersUrl)->assertForbidden();
        $this->actingAs($teacher)->get($teachersUrl)->assertOk()->assertSee($teacher->name)->assertDontSee($otherTeacher->name);
        $this->actingAs($admin)->get($teachersUrl.'?session=Morning')->assertOk()->assertSee($teacher->name)->assertDontSee($otherTeacher->name);
        $this->get($teachersUrl.'?search='.urlencode($teacher->name))->assertOk()
            ->assertSee($teacher->name)->assertDontSee($otherTeacher->name)
            ->assertSee('data-attendance-search', false)->assertSee('Filter attendance status')
            ->assertSee('Excused')->assertSee('js/attendance-people.js', false);
        $this->get($teachersUrl.'?search=NoMatchingTeacherName')->assertOk()
            ->assertViewHas('records', fn ($records) => $records->total() === 0);
        $this->get('/dashboard')->assertOk()->assertSee('hover-menus-toggle')->assertSee('View absent teachers');
    }
}
