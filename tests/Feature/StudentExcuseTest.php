<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\ClassAttendanceReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentExcuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_students_and_guardians_without_linked_children_cannot_request_excuses(): void
    {
        $studentUser = User::factory()->create(['role' => 'student']);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $payload = [
            'child_id' => $student->id, 'session' => 'Morning',
            'starts_on' => today()->toDateString(), 'days' => 1, 'reason' => 'Appointment',
        ];

        $this->actingAs($studentUser)->post(route('students.excuses.store'), $payload)->assertForbidden();
        foreach (User::GUARDIAN_ROLES as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('students.index', ['my_children' => 1]))
                ->assertOk()->assertDontSee('data-open-excuse', false);
            $this->post(route('students.excuses.store'), $payload)->assertForbidden();
        }
        $this->assertDatabaseCount('student_excuses', 0);
    }

    public function test_guardian_submission_marks_the_selected_session_and_appears_in_a_date_filtered_report(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-26 10:00'));
        $guardian = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'administrator']);
        $morning = SchoolClass::create(['name' => 'Grade 5', 'section' => 'A', 'program' => 'English', 'session' => 'Morning']);
        $afternoon = SchoolClass::create(['name' => 'Grade 5', 'section' => 'A', 'program' => 'English', 'session' => 'Afternoon']);
        $child = Student::factory()->create(['parent_id' => $guardian->id, 'school_class_id' => $morning->id]);
        $child->studyClasses()->attach($afternoon);
        $sibling = Student::factory()->create(['parent_id' => $guardian->id]);
        $other = Student::factory()->create(['school_class_id' => $morning->id]);
        Attendance::create(['student_id' => $child->id, 'date' => '2026-11-26', 'status' => 'absent']);

        $payload = ['child_id' => $child->id, 'session' => 'Morning', 'starts_on' => '2026-11-26', 'days' => 5, 'reason' => 'Medical appointment'];
        $this->actingAs($guardian)->get(route('students.index', ['my_children' => 1, 'search' => $child->name]))
            ->assertOk()->assertSee('Ask for excuse')->assertSee('data-excuse-child="'.$sibling->id.'"', false);
        $this->post(route('students.excuses.store'), ['child_id' => $other->id] + $payload)->assertNotFound();
        $this->post(route('students.excuses.store'), ['session' => 'Evening'] + $payload)->assertSessionHasErrorsIn('excuse', 'session');
        $this->post(route('students.excuses.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('students.excuses.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('student_excuses', 1);
        $this->assertDatabaseCount('attendances', 3);
        $this->assertDatabaseCount('class_attendances', 3);
        $marks = app(ClassAttendanceReportService::class)->forMonth(
            SchoolClass::whereKey([$morning->id, $afternoon->id])->get(),
            Student::whereKey($child->id)->with('studyClasses')->get(),
            CarbonImmutable::parse('2026-11-01'),
        );
        $this->assertSame('excused', $marks->get($morning->id.':'.$child->id.':2026-11-26')?->status);
        $this->assertNull($marks->get($afternoon->id.':'.$child->id.':2026-11-26'));

        $report = fn ($date) => route('students.excuses.index', ['date' => $date]);
        $count = route('students.excuses.count');
        $this->getJson($count)->assertForbidden();
        $this->get($report('2026-11-26'))->assertForbidden();
        $this->actingAs($admin)->get(route('students.index'))->assertOk()
            ->assertViewHas('attendanceCounts', fn ($counts) => $counts['excused'] === 1)
            ->assertSee('View requests');
        $this->getJson($count)->assertOk()->assertExactJson(['count' => 1]);
        $this->actingAs($guardian)->post(route('students.excuses.store'), [
            'starts_on' => '2026-12-02', 'days' => 1,
        ] + $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->getJson($count)->assertOk()->assertExactJson(['count' => 2]);
        $this->get($report('2026-11-30'))->assertOk()->assertSee($child->name)->assertSee('Morning')->assertSee('Medical appointment');
        $this->get($report('2026-11-28'))->assertOk()->assertSee('No excuse requests for this date.');
        $this->get($report('2026-12-01'))->assertOk()->assertSee('No excuse requests for this date.');
    }
}
