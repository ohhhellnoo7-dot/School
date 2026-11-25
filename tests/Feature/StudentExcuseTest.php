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
        $this->get($report('2026-11-26'))->assertForbidden();
        $this->actingAs($admin)->get(route('students.index'))->assertOk()
            ->assertViewHas('attendanceCounts', fn ($counts) => $counts['excused'] === 1)
            ->assertSee('View student excuse requests');
        $this->get($report('2026-11-30'))->assertOk()->assertSee($child->name)->assertSee('Morning')->assertSee('Medical appointment');
        $this->get($report('2026-11-28'))->assertOk()->assertSee('No excuse requests for this date.');
        $this->get($report('2026-12-01'))->assertOk()->assertSee('No excuse requests for this date.');
    }
}
