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
            $this->assertStringContainsString('Up next', $html);
            $this->assertStringContainsString('class="teaching-period-next"', $html);
            $this->assertStringNotContainsString('class="teaching-period-current"', $html);
            $this->assertStringNotContainsString('Monthly Attendance Trend', $html);
            $this->assertMatchesRegularExpression('~🌅</span>Morning.*08:00–08:50.*☀️</span>Afternoon.*13:00–14:00.*🌙</span>Evening.*17:00–18:00~s', $html);
            foreach (['Students in my classes', 'My classes', 'My subjects', 'View assignments', 'Daily attendance', 'Student attendance report', 'Student attendance updates', 'Attendance history', 'Today’s teaching schedule', 'Period 1', 'A-TEST', 'Building A', 'dashboard-khmer-calendar'] as $label) {
                $this->assertStringContainsString($label, $html);
            }

            foreach (['My teachers', 'Enrollment trend', 'Student enrollment', 'Students by gender', 'Recent registrations', 'Monthly revenue', 'Another teacher’s room'] as $label) {
                $this->assertStringNotContainsString($label, $html);
            }
        }

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-16 08:00'));
        $html = $this->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertStringContainsString('class="teaching-period-current"', $html);
        $this->assertMatchesRegularExpression('~Teaching now.*08:00–08:50.*Room A-TEST~s', $html);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-16 18:00'));
        $html = $this->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertStringContainsString('No more teaching periods today.', $html);
        $this->assertStringNotContainsString('class="teaching-period-current"', $html);
    }

    public function test_teacher_dashboard_replaces_scheduled_periods_with_the_calendar_holiday(): void
    {
        $this->seed(SchoolSeeder::class);
        $teacher = User::where('email', 'teacher@school.test')->firstOrFail();
        Schedule::where('teacher_id', $teacher->id)->update(['day' => 'Tuesday']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-24 09:00'));

        $this->actingAs($teacher)->get('/dashboard')->assertOk()
            ->assertViewHas('todayTeachingPeriods', fn ($periods) => $periods->isEmpty())
            ->assertSee('Water Festival')->assertDontSee('today-teaching-periods-title');
        $html = $this->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertStringContainsString('teaching-holiday-title', $html);
        $this->assertStringContainsString('holidays/chibi/water_festival.jpg', $html);
        $this->assertStringNotContainsString('today-teaching-periods-title', $html);

        foreach (array_unique(config('khmer-holidays.2026')) as $date => $holiday) {
            $this->travelTo(\Illuminate\Support\Carbon::parse('2026-'.$date.' 09:00'));
            $response = $this->get('/dashboard')->assertOk()->assertDontSee('today-teaching-periods-title');
            $artwork = $holiday === 'labour_and_visak' ? ['labour', 'visak'] : [$holiday];
            foreach ($artwork as $image) {
                $response->assertSee('holidays/chibi/'.$image.'.jpg', false);
            }
        }

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-01 09:00'));
        $this->get('/dashboard')->assertOk()->assertSee('today-teaching-periods-title')
            ->assertSee('Period 1')->assertDontSee('teaching-holiday-title');
    }

    public function test_parent_labels_refer_to_their_own_children_on_initial_and_live_dashboard(): void
    {
        $parent = User::factory()->create();
        $parent->forceFill(['role' => 'parent'])->save();
        Student::factory()->create(['name' => 'Rika', 'parent_id' => $parent->id]);
        Student::factory()->create(['name' => 'Private Child']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        \App\Models\Announcement::create(['title' => 'Family meeting', 'body' => 'Meeting for families.', 'audience' => 'parent', 'published_at' => today(), 'created_by' => $teacher->id]);
        \App\Models\Announcement::create(['title' => 'Staff-only update', 'body' => 'Internal update.', 'audience' => 'teacher', 'published_at' => today(), 'created_by' => $teacher->id]);
        $this->actingAs($parent)->get('/dashboard')->assertOk()
            ->assertSee("Rika's teachers")->assertSee("Rika's classes")->assertSee("Rika's subjects")
            ->assertSee(['Open family dashboard', 'Family meeting', 'Family Announcements'])
            ->assertDontSee('Monthly Attendance Trend')
            ->assertDontSee(['Private Child', 'Staff-only update', 'Monthly revenue', 'Recent registrations', 'Students by gender', 'Fees collected'])
            ->assertDontSee('My teachers')->assertDontSee("Private Child's teachers");
        $html = $this->getJson('/dashboard/data')->assertOk()->assertJsonPath('showRevenue', false)
            ->assertJsonPath('charts.revenue', [])->assertJsonPath('charts.gender', [])->assertJsonPath('charts.enrollment', [])->json('html');
        $this->assertStringContainsString(e("Rika's teachers"), $html);
        $this->assertStringContainsString('Family meeting', $html);
        $this->assertStringNotContainsString('Monthly Attendance Trend', $html);
        $this->assertStringNotContainsString('Staff-only update', $html);
        Student::factory()->create(['name' => 'Soth Vichea', 'parent_id' => $parent->id]);
        $this->get('/dashboard')->assertOk()->assertSee("My children's teachers")
            ->assertSee("My children's classes")->assertSee("My children's subjects");
        $otherParent = User::factory()->create();
        $otherParent->forceFill(['role' => 'parent'])->save();
        $this->actingAs($otherParent)->get('/dashboard')->assertOk()
            ->assertSee('No student records are linked')->assertDontSee("Rika's teachers")->assertDontSee('Monthly Attendance Trend');
    }

    public function test_teacher_attendance_messages_refresh_and_roll_over_to_weekly_history(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-22 09:00'));
        $this->seed(SchoolSeeder::class);
        $teacher = User::where('email', 'teacher@school.test')->firstOrFail();
        $class = SchoolClass::scheduledForTeacher($teacher)->firstOrFail();
        $student = Student::factory()->create(['name' => 'Attendance Feed Student', 'school_class_id' => $class->id]);
        $student->studyClasses()->syncWithoutDetaching([$class->id]);
        $otherClass = SchoolClass::create(['name' => 'Grade 12', 'section' => 'Feed scope', 'program' => 'English', 'session' => 'Morning']);
        $other = Student::factory()->create(['name' => 'Outside Teacher Scope', 'school_class_id' => $otherClass->id]);
        $child = Student::factory()->create(['name' => 'Linked Teacher Child', 'school_class_id' => $otherClass->id, 'parent_id' => $teacher->id]);
        foreach (['2026-11-16' => 'present', '2026-11-17' => 'late', '2026-11-18' => 'absent', '2026-11-21' => 'late'] as $date => $status) {
            Attendance::updateOrCreate(['student_id' => $student->id, 'date' => $date], ['status' => $status]);
        }
        Attendance::create(['student_id' => $other->id, 'date' => '2026-11-18', 'status' => 'absent']);
        Attendance::create(['student_id' => $child->id, 'date' => '2026-11-18', 'status' => 'late']);
        \App\Models\ClassAttendance::create(['student_id' => $child->id, 'school_class_id' => $otherClass->id, 'date' => '2026-11-19', 'status' => 'present', 'recorded_by' => $teacher->id]);
        \App\Models\ClassAttendance::create(['student_id' => $student->id, 'school_class_id' => $class->id, 'date' => '2026-11-19', 'status' => 'excused', 'recorded_by' => $teacher->id]);
        $html = $this->actingAs($teacher)->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertStringContainsString(e($student->name.' arrived on time.'), $html);
        $this->assertStringContainsString(e($student->name.' arrived late.'), $html);
        $this->assertStringContainsString(e($student->name.' was marked absent.'), $html);
        $this->assertStringContainsString(e($class->label), $html);
        $this->assertStringContainsString(e($student->name.'’s absence was excused.'), $html);
        $this->assertStringNotContainsString('Sat, Nov 21', $html);
        $this->assertStringNotContainsString('Outside Teacher Scope was marked absent.', $html);
        $this->assertStringNotContainsString('Linked Teacher Child arrived', $html);
        $childHtml = $this->getJson('/dashboard/data?attendance_scope=children')->assertOk()->json('html');
        $this->assertStringContainsString('Linked Teacher Child arrived late.', $childHtml);
        $this->assertStringContainsString('Linked Teacher Child arrived on time.', $childHtml);
        $this->assertStringNotContainsString('Attendance Feed Student arrived', $childHtml);
        $this->assertStringNotContainsString('Outside Teacher Scope was marked absent.', $childHtml);

        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-23 09:00'));
        $html = $this->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertStringNotContainsString(e($student->name.' arrived late.'), $html);
        Attendance::updateOrCreate(['student_id' => $student->id, 'date' => '2026-11-23'], ['status' => 'present']);
        $this->assertStringContainsString(e($student->name.' arrived on time.'), $this->getJson('/dashboard/data')->assertOk()->json('html'));
        $this->get(route('attendance.student-history', ['week' => '2026-11-16']))->assertOk()
            ->assertSee($student->name.' arrived late.')->assertDontSee('Sat, Nov 21')->assertDontSee('Outside Teacher Scope');
        $this->get(route('attendance.student-history', ['week' => '2026-11-16', 'attendance_scope' => 'children']))->assertOk()
            ->assertSee('Linked Teacher Child arrived late.')->assertDontSee('Attendance Feed Student arrived');
    }

    public function test_family_notices_start_three_days_ahead_and_show_one_current_weekday_status(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-19 09:00', 'Asia/Phnom_Penh'));
        $parent = User::factory()->create(['role' => 'parent']);
        $child = Student::factory()->create(['name' => 'Family Notice Child', 'parent_id' => $parent->id]);
        $other = Student::factory()->create(['name' => 'Unrelated Notice Child']);
        $this->actingAs($parent)->get('/dashboard')->assertOk()->assertDontSee('Water Festival');

        $this->travelTo(now()->addDay());
        \App\Models\ClassAttendance::create(['student_id' => $child->id, 'school_class_id' => $child->school_class_id, 'date' => '2026-11-20', 'status' => 'late', 'recorded_by' => User::factory()->create(['role' => 'teacher'])->id]);
        $this->get('/dashboard')->assertOk()->assertSee('Family Notice Child arrived late.');
        $daily = Attendance::create(['student_id' => $child->id, 'date' => '2026-11-20', 'status' => 'present']);
        Attendance::create(['student_id' => $other->id, 'date' => '2026-11-20', 'status' => 'absent']);
        $html = $this->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertSame(1, substr_count($html, 'Water Festival'));
        $this->assertStringContainsString('Mon, Nov 23', $html);
        $this->assertStringContainsString('Wed, Nov 25, 2026', $html);
        $this->assertSame(1, substr_count($html, 'data-family-attendance-notice'));
        $this->assertStringContainsString('Family Notice Child arrived on time.', $html);
        $this->assertStringNotContainsString('Unrelated Notice Child', $html);
        $daily->update(['status' => 'excused']);
        $html = $this->getJson('/dashboard/data')->assertOk()->json('html');
        $this->assertStringContainsString('Family Notice Child’s absence was excused.', $html);
        $this->assertStringNotContainsString('Family Notice Child arrived on time.', $html);

        $this->travelTo(now()->addDay());
        Attendance::create(['student_id' => $child->id, 'date' => '2026-11-21', 'status' => 'absent']);
        $this->get('/dashboard')->assertOk()->assertSee('Water Festival')->assertDontSee('data-family-attendance-notice', false);
        $this->travelTo(now()->addDays(5));
        $this->get('/dashboard')->assertOk()->assertDontSee('Water Festival')->assertDontSee('data-family-attendance-notice', false);
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
