<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Fee;
use App\Models\Schedule;
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

    public function test_monthly_attendance_reports_real_totals_gaps_and_matching_enrollments(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-18 09:00'));
        $admin = User::factory()->create(['role' => 'administrator']);
        $parent = User::factory()->create(['role' => 'parent']);
        $morning = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning']);
        $afternoon = SchoolClass::create(['name' => 'Grade 8', 'section' => 'B', 'program' => 'English', 'session' => 'Afternoon']);
        foreach (['present', 'late', 'absent', 'excused'] as $status) {
            $student = Student::factory()->create(['school_class_id' => $morning->id, 'parent_id' => $status === 'late' ? $parent->id : null]);
            Attendance::create(['student_id' => $student->id, 'date' => '2026-11-16', 'status' => $status]);
            if ($status !== 'excused') {
                Attendance::create(['student_id' => $student->id, 'date' => '2026-11-20', 'status' => $status]);
            }
            if ($status === 'absent') {
                Attendance::create(['student_id' => $student->id, 'date' => '2026-11-18', 'status' => 'absent']);
                Attendance::create(['student_id' => $student->id, 'date' => '2026-11-09', 'status' => 'present']);
            }
        }
        $different = Student::factory()->create(['school_class_id' => $afternoon->id]);
        Attendance::create(['student_id' => $different->id, 'date' => '2026-11-16', 'status' => 'present']);
        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertSee(['Monthly Attendance Trend', 'November 2026', 'No attendance recorded', '60%'])
            ->assertSee(['Average Attendance', 'Highest Day', 'Lowest Day', 'Days in Month', '53.3%', '30 days'])
            ->assertSee(['Monthly attendance breakdown', '7 records', '42.9%'])
            ->assertSee(['data-attendance-mode="combined"', 'data-attendance-mode="area"', 'data-attendance-mode="column"'], false)
            ->assertSeeInOrder(['2026-11-01', '2026-11-16', '2026-11-30']);
        $this->getJson('/dashboard/data')->assertOk()
            ->assertJsonCount(30, 'charts.monthlyAttendance')
            ->assertJsonPath('charts.monthlyAttendance.0.date', '2026-11-01')
            ->assertJsonPath('charts.monthlyAttendance.8.rate', 100)
            ->assertJsonPath('charts.monthlyAttendance.15.rate', 60)
            ->assertJsonPath('charts.monthlyAttendance.15.counts', ['present' => 2, 'late' => 1, 'absent' => 1, 'excused' => 1])
            ->assertJsonPath('charts.monthlyAttendance.16.rate', null)
            ->assertJsonPath('charts.monthlyAttendance.17.rate', 0)
            ->assertJsonPath('charts.monthlyAttendance.17.recorded', 1)
            ->assertJsonPath('charts.monthlyAttendance.19.rate', null)
            ->assertJsonPath('charts.monthlyAttendance.29.date', '2026-11-30');
        $this->getJson('/dashboard/data?date=2026-11-18&weekly_program=Khmer&weekly_grade=7&weekly_class='.$morning->id.'&weekly_session=Morning')
            ->assertOk()->assertJsonPath('charts.monthlyAttendance.15.rate', 50)
            ->assertJsonPath('charts.monthlyAttendance.15.attended', 2)->assertJsonPath('summary.attendanceCount', 1);
        $this->getJson('/dashboard/data?weekly_program=Khmer&weekly_session=Afternoon')
            ->assertOk()->assertJsonPath('charts.monthlyAttendance.15.rate', null);
        Attendance::create(['student_id' => $different->id, 'date' => '2026-11-18', 'status' => 'present']);
        $this->getJson('/dashboard/data')->assertOk()
            ->assertJsonPath('charts.monthlyAttendance.17.rate', 50)
            ->assertJsonPath('charts.monthlyAttendance.17.counts', ['present' => 1, 'late' => 0, 'absent' => 1, 'excused' => 0]);
        $this->actingAs($parent)->getJson('/dashboard/data')->assertOk()
            ->assertJsonPath('charts.monthlyAttendance.15.rate', 100)->assertJsonPath('charts.monthlyAttendance.15.recorded', 1);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-01 09:00'));
        $this->getJson('/dashboard/data')->assertOk()
            ->assertJsonCount(31, 'charts.monthlyAttendance')
            ->assertJsonPath('charts.monthlyAttendance.0.date', '2026-12-01')
            ->assertJsonPath('charts.monthlyAttendance.30.date', '2026-12-31');
    }

    public function test_teacher_dashboard_focuses_on_teaching_and_attendance_on_initial_load_and_refresh(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-16 09:00'));
        $this->seed(SchoolSeeder::class);
        $teacher = User::where('email', 'teacher@school.test')->firstOrFail();
        $classroom = Classroom::create(['room_number' => 'A-TEST', 'name' => 'Test classroom', 'building' => 'A']);
        Schedule::where('teacher_id', $teacher->id)->where('starts_at', 'like', '08:00%')->update(['classroom_id' => $classroom->id]);
        Schedule::where('teacher_id', $teacher->id)->where('starts_at', 'like', '09:00%')->update(['starts_at' => '13:00', 'ends_at' => '14:00']);
        Schedule::where('teacher_id', $teacher->id)->where('starts_at', 'like', '10:00%')->update(['starts_at' => '17:00', 'ends_at' => '18:00']);
        $otherTeacher = User::where('email', 'james@school.test')->firstOrFail();
        Schedule::where('teacher_id', $otherTeacher->id)->update(['room' => 'Another teacher’s room']);
        $this->actingAs($teacher)->get('/dashboard')->assertOk()->assertSee('My teaching schedule');

        $pages = [
            $this->get('/dashboard')->assertOk()->getContent(),
            $this->getJson('/dashboard/data')->assertOk()->json('html'),
        ];

        foreach ($pages as $html) {
            $this->assertMatchesRegularExpression('~🌅</span>Morning.*08:00–08:50.*☀️</span>Afternoon.*13:00–14:00.*🌙</span>Evening.*17:00–18:00~s', $html);
            foreach (['Students in my classes', 'My classes', 'My subjects', 'View assignments', 'Daily attendance', 'Student attendance report', 'School announcements', 'Today’s teaching schedule', 'Period 1', 'A-TEST', 'Building A', 'dashboard-khmer-calendar'] as $label) {
                $this->assertStringContainsString($label, $html);
            }

            foreach (['My teachers', 'Enrollment trend', 'Student enrollment', 'Students by gender', 'Recent registrations', 'Monthly revenue', 'Another teacher’s room'] as $label) {
                $this->assertStringNotContainsString($label, $html);
            }
        }
    }

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

    public function test_dashboard_includes_new_program_teachers_in_initial_and_refreshed_breakdowns(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'administrator'])->save();
        $this->seed(\Database\Seeders\JapanTeacherSeeder::class);

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertViewHas('programBreakdowns', fn ($counts) => $counts['person-workspace']['Japan'] === 12);
        $html = $this->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertStringContainsString('breakdown-program-japan', $html);
        $this->assertStringContainsString('<dd>12</dd>', $html);
    }

    public function test_teacher_breakdown_includes_program_labels_without_a_program_record(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        User::factory()->count(2)->create(['role' => 'teacher', 'program' => 'Japanese']);
        User::factory()->create(['role' => 'teacher', 'program' => null]);
        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertViewHas('programBreakdowns', fn ($counts) => $counts['person-workspace']['Japanese'] === 2
                && $counts['person-workspace']['Unassigned'] === 1);
        $html = $this->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertStringContainsString('Japanese', $html);
        $this->assertStringContainsString('Unassigned', $html);
    }

    public function test_dashboard_counts_a_grade_and_section_once_across_programs_and_sessions(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'administrator'])->save();
        foreach ([
            ['English', 'Morning'],
            ['English', 'Afternoon'],
            ['Chinese', 'Evening'],
            ['Khmer', 'Morning'],
        ] as [$program, $session]) {
            SchoolClass::create([
                'name' => 'Grade 12', 'section' => 'A',
                'program' => $program, 'session' => $session,
            ]);
        }
        SchoolClass::create(['name' => 'Grade 12', 'section' => 'B', 'program' => 'English', 'session' => 'Morning']);

        $this->actingAs($admin)->getJson('/dashboard/data')->assertOk()
            ->assertJsonPath('summary.classCount', 2);
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
        $this->get('/dashboard')->assertOk()->assertSee('dashboard-refresh')->assertDontSee('School-wide totals');
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
        // Financial history now restricts deleting a student who has fees.
        $temporary = Student::factory()->create();
        $temporary->delete();
        $this->getJson('/dashboard/data')->assertJsonPath('summary.studentCount', $before + 1);
        $this->assertDatabaseHas('fees', ['id' => $fee->id, 'student_id' => $student->id]);
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
