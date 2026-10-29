<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Fee;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\SchoolModules;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAjaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_labels_refer_to_their_own_children_on_initial_and_live_dashboard(): void
    {
        $parent = User::factory()->create();
        $parent->forceFill(['role' => 'parent'])->save();
        Student::factory()->create(['name' => 'Rika', 'parent_id' => $parent->id]);
        Student::factory()->create(['name' => 'Private Child']);
        $this->actingAs($parent)->get('/dashboard')->assertOk()
            ->assertSee("Rika's teachers")->assertSee("Rika's classes")->assertSee("Rika's subjects")
            ->assertDontSee('My teachers')->assertDontSee("Private Child's teachers");
        $html = $this->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertStringContainsString(e("Rika's teachers"), $html);
        Student::factory()->create(['name' => 'Soth Vichea', 'parent_id' => $parent->id]);
        $this->get('/dashboard')->assertOk()->assertSee("My children's teachers")
            ->assertSee("My children's classes")->assertSee("My children's subjects");
        $otherParent = User::factory()->create();
        $otherParent->forceFill(['role' => 'parent'])->save();
        $this->actingAs($otherParent)->get('/dashboard')->assertOk()
            ->assertSee("My children's teachers")->assertDontSee("Rika's teachers");
    }

    public function test_daily_report_uses_selected_date_for_totals_and_chart(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        $date = today()->subDay()->toDateString();
        foreach (['present', 'absent', 'late', 'excused'] as $status) {
            $student = Student::factory()->create();
            Attendance::create(['student_id' => $student->id, 'date' => $date, 'status' => $status]);
            Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'present']);
        }
        $this->actingAs($admin)->get('/dashboard?date='.$date)->assertOk()
            ->assertSee('value="'.$date.'"', false)->assertSee('Daily attendance report')
            ->assertDontSee('Mark all present today');
        $this->getJson('/dashboard/data?date='.$date)->assertOk()
            ->assertJsonPath('summary.presentCount', 1)
            ->assertJsonPath('summary.absentCount', 1)
            ->assertJsonPath('summary.lateCount', 1)
            ->assertJsonPath('summary.attendanceCount', 4)
            ->assertJsonPath('charts.attendance', [1, 1, 1, 1]);
        $this->getJson('/dashboard/data')->assertOk()->assertJsonPath('summary.presentCount', 4);
        $empty = $this->getJson('/dashboard/data?date='.today()->subDays(2)->toDateString())->assertOk()
            ->assertJsonPath('summary.attendanceCount', 0);
        $this->assertStringContainsString('No attendance recorded for this date.', $empty->json('html'));
        $this->getJson('/dashboard/data?date=invalid')->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_admin_program_breakdowns_count_students_teachers_and_unique_classes_and_subjects(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        foreach (['Khmer', 'English', 'Chinese'] as $index => $program) {
            $teacher = User::factory()->create(['program' => $program]);
            $teacher->role = 'teacher';
            $teacher->save();
            foreach (['Morning', 'Afternoon'] as $session) {
                $class = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A', 'program' => $program, 'session' => $session]);
                Student::factory()->count($index + 1)->create(['school_class_id' => $class->id]);
                Subject::create(['name' => 'Reading', 'code' => $program.$session, 'school_class_id' => $class->id, 'teacher_id' => $teacher->id]);
            }
        }
        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertViewHas('programBreakdowns', fn ($counts) => $counts['people'] === ['Khmer' => 2, 'English' => 4, 'Chinese' => 6]
                && $counts['person-workspace'] === ['Khmer' => 1, 'English' => 1, 'Chinese' => 1]
                && $counts['building'] === ['Khmer' => 1, 'English' => 1, 'Chinese' => 1]
                && $counts['book'] === ['Khmer' => 1, 'English' => 1, 'Chinese' => 1])
            ->assertSee('Total students by program')->assertSee('Total teachers by program')
            ->assertSee('Total classes by program')->assertSee('Total subjects by program');
        $html = $this->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertStringContainsString('breakdown-people', $html);
        $this->actingAs($teacher)->get('/dashboard')->assertOk()
            ->assertViewHas('programBreakdowns', [])->assertDontSee('breakdown-people');
    }

    public function test_student_home_prioritizes_scoped_upcoming_schoolwork(): void
    {
        $this->seed(SchoolSeeder::class);
        $student = User::where('email', 'student@school.test')->firstOrFail();
        $subject = SchoolModules::subjects($student)->firstOrFail();
        $otherSubject = Subject::where('school_class_id', '!=', $subject->school_class_id)->firstOrFail();
        foreach ([[$subject->id, 'My upcoming task', today()], [$subject->id, 'My old task', today()->subDay()], [$otherSubject->id, 'Another class private task', today()]] as [$subjectId, $title, $due]) {
            Assignment::create(['subject_id' => $subjectId, 'title' => $title, 'description' => 'Read the chapter.', 'due_date' => $due]);
        }
        $this->actingAs($student)->get('/dashboard')->assertOk()
            ->assertSee('My timetable')->assertSee('My upcoming task')->assertSee('Due today')
            ->assertDontSee('My old task')->assertDontSee('Another class private task')
            ->assertDontSee('Recent registrations')->assertDontSee('id="enrollmentChart"', false);
        $html = $this->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertStringContainsString('My upcoming task', $html);
        $this->assertStringNotContainsString('Another class private task', $html);
    }

    public function test_ajax_totals_and_charts_follow_current_database_changes(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 26)->startOfDay());
        $this->seed(SchoolSeeder::class);
        $this->actingAs(User::where('role', 'administrator')->firstOrFail());
        $this->get('/dashboard')->assertOk()->assertSee('dashboard-refresh')->assertSee('School-wide totals');
        $this->getJson('/dashboard/data')->assertOk()
            ->assertJsonPath('summary.studentCount', Student::count())
            ->assertJsonPath('summary.teacherCount', User::where('role', 'teacher')->count())
            ->assertJsonPath('summary.classCount', SchoolClass::select('name', 'section')->distinct()->get()->count())
            ->assertJsonPath('summary.subjectCount', Subject::distinct()->count('name'));
        $before = Student::count();
        $student = Student::factory()->create(['gender' => 'female', 'enrollment_date' => today()]);
        Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'absent']);
        $fee = Fee::create(['student_id' => $student->id, 'description' => 'AJAX test', 'amount' => 100, 'paid' => 25, 'due_date' => today()->addWeek(), 'paid_at' => today()]);
        $response = $this->getJson('/dashboard/data')->assertOk()
            ->assertJsonPath('summary.studentCount', $before + 1)
            ->assertJsonPath('summary.absentCount', Attendance::whereDate('date', today())->where('status', 'absent')->count())
            ->assertJsonPath('charts.gender.0', Student::where('gender', 'female')->count());
        $this->assertSame((float) Fee::sum('paid'), (float) $response->json('summary.collected'));
        $this->assertStringContainsString($student->name, $response->json('html'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $fee->update(['paid' => 100]);
        $response = $this->getJson('/dashboard/data')->assertOk();
        $this->assertSame((float) Fee::sum('paid'), (float) $response->json('summary.collected'));
        $this->assertSame((float) Fee::sum('amount') - (float) Fee::sum('paid'), (float) $response->json('summary.pending'));
        $student->delete();
        $this->getJson('/dashboard/data')->assertJsonPath('summary.studentCount', $before);
    }

    public function test_ajax_is_authenticated_scoped_and_explains_unlinked_accounts(): void
    {
        $this->getJson('/dashboard/data')->assertUnauthorized();
        $this->seed(SchoolSeeder::class);
        $parent = User::where('email', 'parent@school.test')->firstOrFail();
        $this->actingAs($parent)->getJson('/dashboard/data')->assertOk()->assertJsonPath('summary.studentCount', 1);
        $student = User::where('email', 'student@school.test')->firstOrFail();
        $this->actingAs($student)->getJson('/dashboard/data?role=administrator')->assertOk()
            ->assertJsonPath('summary.studentCount', 1)->assertJsonPath('summary.collected', null)
            ->assertJsonPath('summary.pending', null)->assertJsonPath('charts.revenue', []);
        $unlinked = User::factory()->create();
        $response = $this->actingAs($unlinked)->getJson('/dashboard/data')->assertOk()->assertJsonPath('summary.studentCount', 0);
        $this->assertStringContainsString('No student records are linked', $response->json('html'));
        $this->assertStringNotContainsString('Emma Parker', $response->json('html'));
        $child = Student::where('email', 'student@school.test')->firstOrFail();
        $child->update(['user_id' => $unlinked->id]);
        $response = $this->getJson('/dashboard/data')->assertOk()->assertJsonPath('summary.studentCount', 1);
        $this->assertStringNotContainsString('No student records are linked', $response->json('html'));
    }
}
