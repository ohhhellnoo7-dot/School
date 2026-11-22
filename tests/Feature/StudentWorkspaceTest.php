<?php

namespace Tests\Feature;

use App\Models\{Announcement, Assignment, Attendance, ClassAttendance, Examination, Fee, Grade, Schedule, SchoolClass, Student, Subject, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_announcement_archive_and_search_only_show_published_student_notices(): void
    {
        $account = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        foreach (range(0, 5) as $daysAgo) {
            Announcement::create([
                'created_by' => $teacher->id, 'title' => 'Sports day '.$daysAgo, 'body' => 'Registration is open.',
                'audience' => 'all', 'published_at' => today()->subDays($daysAgo),
            ]);
        }
        foreach ([
            ['Older notice', 'student', today()->subDays(10)],
            ['Staff notice', 'teacher', today()],
            ['Future notice', 'student', today()->addDay()],
        ] as [$title, $audience, $date]) {
            Announcement::create([
                'created_by' => $teacher->id, 'title' => $title, 'body' => 'Library opening hours.',
                'audience' => $audience, 'published_at' => $date,
            ]);
        }

        $links = ['section' => 'announcements'];
        $this->actingAs($account)->get(route('dashboard', $links))->assertOk()
            ->assertSee(['Latest Announcements', 'View All', 'Search announcements', 'Sports day 0'])
            ->assertDontSee(['Older notice', 'Staff notice', 'Future notice']);
        $this->get(route('dashboard', $links + ['announcement_view' => 'all']))->assertOk()
            ->assertSee(['All Announcements', 'Older notice'])->assertDontSee(['Staff notice', 'Future notice']);
        $this->get(route('dashboard', $links + ['search' => 'Library opening']))->assertOk()
            ->assertSee(['Search Results', 'Older notice'])->assertDontSee(['Sports day 0', 'Staff notice', 'Future notice']);
    }

    public function test_student_invoice_opens_as_a_private_html_page(): void
    {
        $account = User::factory()->create(['role' => 'student', 'profile_photo_path' => 'profiles/student.png']);
        $student = Student::factory()->create(['user_id' => $account->id]);
        $peer = Student::factory()->create();
        $attributes = ['description' => 'November tuition', 'amount' => 35, 'paid' => 35, 'currency' => 'USD', 'due_date' => '2026-11-10'];
        $fee = Fee::create($attributes + ['student_id' => $student->id, 'payment_plan' => 'monthly', 'paid_at' => '2026-11-07']);
        $privateFee = Fee::create($attributes + ['student_id' => $peer->id]);

        $this->actingAs($account)->get(route('dashboard', ['section' => 'fees']))
            ->assertOk()->assertSee(route('finance.invoice', $fee), false)
            ->assertSee(['My Fees', 'Total Billed', 'Payment History', 'Transactions']);
        $this->get(route('finance.invoice', $fee))->assertOk()
            ->assertHeaderMissing('Content-Disposition')
            ->assertSee(['Invoice Details', 'November tuition', '07 December 2026', 'Payment Summary', '35.00 USD', '0.00 USD'])
            ->assertSee('class="si-icon si-avatar"', false)
            ->assertDontSee('Print / Save as PDF');
        $this->get(route('dashboard', ['section' => 'fees', 'fees_tab' => 'payments']))->assertOk()
            ->assertSee(['November tuition', 'Net Paid', '35.00']);
        Fee::create(array_replace($attributes, [
            'student_id' => $student->id, 'description' => 'Pending tuition', 'paid' => 0,
            'checkout_submitted_at' => '2026-11-10 12:00:00', 'checkout_method' => 'qr',
        ]));
        $this->get(route('dashboard', ['section' => 'fees', 'fees_tab' => 'transactions']))->assertOk()
            ->assertSee(['Pending tuition', 'View payment'])->assertDontSee('November tuition');
        $this->get(route('finance.invoice', $privateFee))->assertNotFound();
    }

    public function test_attendance_dashboard_uses_the_selected_month_and_personal_daily_records(): void
    {
        $account = User::factory()->create(['role' => 'student']);
        $student = Student::factory()->create(['user_id' => $account->id]);
        $peer = Student::factory()->create();
        foreach (['2026-10-30' => 'absent', '2026-11-10' => 'present', '2026-11-11' => 'late'] as $date => $status) {
            Attendance::create(['student_id' => $student->id, 'date' => $date, 'status' => $status]);
        }
        Attendance::create(['student_id' => $peer->id, 'date' => '2026-11-12', 'status' => 'absent']);

        $this->actingAs($account)->get(route('dashboard', ['section' => 'attendance', 'month' => '2026-11', 'trend_days' => 16]))
            ->assertOk()->assertSee(['November 2026', 'Attendance Summary', 'Attendance Trend', 'Last 16 school days', 'Nov 10 100%, Nov 11 50%'])
            ->assertSee('datetime="2026-11-10" data-status="present"', false)
            ->assertSee('datetime="2026-11-11" data-status="late"', false)
            ->assertSee('datetime="2026-11-12" data-status=""', false)
            ->assertDontSee('Daily attendance')
            ->assertDontSee('datetime="2026-10-30"', false);
    }

    public function test_khmer_class_cards_show_only_active_curriculum_subjects_without_duplicates(): void
    {
        $this->seed(\Database\Seeders\KhmerCurriculumSeeder::class);
        $account = User::factory()->create(['role' => 'student']);
        $class = SchoolClass::create(['name' => 'Grade 10', 'section' => 'A', 'program' => 'Khmer', 'session' => 'Morning']);
        Student::factory()->create(['user_id' => $account->id, 'school_class_id' => $class->id]);
        foreach ([
            ['Mathematics', 'MATH', null],
            ['Mathematics', 'DUPLICATE-MATH', null],
            ['Science', 'EXTRA-SCIENCE', null],
            ['Biology', 'BIO', 60],
            ['Physics', 'INACTIVE-PHYSICS', 0],
        ] as [$name, $code, $minutes]) {
            $class->subjects()->create(['name' => $name, 'code' => $code, 'required_weekly_minutes' => $minutes]);
        }

        $this->actingAs($account)->get(route('dashboard', ['section' => 'classes']))->assertOk()
            ->assertSee(['2 Subjects', 'Mathematics', 'Biology'])
            ->assertDontSee(['DUPLICATE-MATH', 'EXTRA-SCIENCE', 'INACTIVE-PHYSICS']);
    }

    public function test_student_workspace_only_displays_personal_records_and_enrolled_coursework(): void
    {
        $account = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $ownClass = SchoolClass::create(['name' => 'My cohort', 'section' => 'A', 'program' => 'English', 'session' => 'Morning']);
        $otherClass = SchoolClass::create(['name' => 'Private cohort', 'section' => 'B', 'program' => 'Khmer', 'session' => 'Afternoon']);
        $student = Student::factory()->create(['user_id' => $account->id, 'school_class_id' => $ownClass->id]);
        $peer = Student::factory()->create(['name' => 'Private peer', 'school_class_id' => $ownClass->id]);

        foreach ([$ownClass, $otherClass] as $class) {
            $label = $class->name;
            $subject = Subject::create(['school_class_id' => $class->id, 'name' => $label.' lesson', 'code' => 'SUB-'.$class->id]);
            Assignment::create(['subject_id' => $subject->id, 'title' => $label.' assignment', 'description' => 'Read the chapter.', 'due_date' => today()]);
            Schedule::create(['subject_id' => $subject->id, 'day' => 'Monday', 'starts_at' => '08:00', 'ends_at' => '09:00', 'room' => '101']);
        }

        $exam = Examination::create(['subject_id' => $ownClass->subjects()->first()->id, 'name' => 'Shared exam', 'date' => today(), 'total_marks' => 100]);
        Grade::create(['student_id' => $student->id, 'examination_id' => $exam->id, 'marks' => 87, 'remarks' => 'My result feedback']);
        Grade::create(['student_id' => $peer->id, 'examination_id' => $exam->id, 'marks' => 93, 'remarks' => 'Private peer feedback']);
        Attendance::create(['student_id' => $student->id, 'date' => today(), 'status' => 'present']);
        Attendance::create(['student_id' => $peer->id, 'date' => today(), 'status' => 'excused']);
        ClassAttendance::create(['school_class_id' => $ownClass->id, 'student_id' => $student->id, 'date' => today(), 'status' => 'late', 'recorded_by' => $teacher->id]);
        ClassAttendance::create(['school_class_id' => $ownClass->id, 'student_id' => $peer->id, 'date' => today(), 'status' => 'excused', 'recorded_by' => $teacher->id]);
        Fee::create(['student_id' => $student->id, 'description' => 'My tuition', 'amount' => 30, 'paid' => 0, 'due_date' => today()]);
        Fee::create(['student_id' => $peer->id, 'description' => 'Private peer tuition', 'amount' => 40, 'paid' => 0, 'due_date' => today()]);
        Announcement::create(['created_by' => $teacher->id, 'title' => 'Student update', 'body' => 'Welcome.', 'audience' => 'student', 'published_at' => today()]);
        Announcement::create(['created_by' => $teacher->id, 'title' => 'Private staff update', 'body' => 'Staff meeting.', 'audience' => 'teacher', 'published_at' => today()]);

        $this->actingAs($account)->get(route('dashboard'))->assertOk()
            ->assertSee(['My cohort', 'My cohort assignment', 'Student update', '100%', 'Quick actions', 'Search assignments'])
            ->assertSee(['Cambodian calendar', 'momentkh-3.0.3.js', 'khmer-calendar.js'])
            ->assertDontSee(['Private cohort', 'Private staff update', 'Search students', 'Classroom availability']);
        foreach ([
            'classes' => ['My cohort lesson', 'Private cohort lesson'],
            'timetable' => ['My cohort lesson', 'Private cohort lesson'],
            'coursework' => ['My result feedback', 'Private peer feedback'],
            'attendance' => ['Present', 'Excused'],
            'fees' => ['My tuition', 'Private peer tuition'],
            'announcements' => ['Student update', 'Private staff update'],
            'record' => [$student->student_number, $peer->name],
        ] as $section => [$visible, $private]) {
            $this->get(route('dashboard', ['section' => $section, 'day' => 'Monday']))->assertOk()
                ->assertSee($visible)->assertDontSee($private);
        }
        $this->get(route('dashboard', ['section' => 'attendance']))->assertSee('Late');
        $this->get(route('dashboard', ['section' => 'classes', 'class_session' => 'Morning']))->assertOk()
            ->assertSee(['Enrolled Classes', '1 Subject', 'My cohort lesson'])->assertDontSee('Private cohort');
        $this->get(route('dashboard', ['section' => 'classes', 'class_session' => 'Afternoon']))->assertOk()
            ->assertSee('You have no enrolled classes in this session.')->assertDontSee('Private cohort');
        $this->get(route('dashboard', ['section' => 'coursework', 'search' => 'Private cohort']))->assertOk()
            ->assertSee('No assignments to show.')->assertDontSee('Private cohort assignment');
        $this->get(route('dashboard', ['section' => 'coursework', 'search' => 'Shared exam']))->assertOk()
            ->assertSee(['Upcoming Exams', 'Shared exam', 'My result feedback', '87%'])
            ->assertDontSee(['Private peer feedback', 'My cohort assignment']);
        $this->get(route('dashboard', ['section' => 'coursework', 'assignment_filter' => 'overdue']))->assertOk()
            ->assertSee('No assignments to show.')->assertDontSee('My cohort assignment');
        $this->get(route('dashboard', ['section' => 'record']))->assertOk()
            ->assertSee(['My Student Record', '100%', '87%', '30.00', 'Study Progress', 'Print Record', 'My cohort assignment', 'Latest Activity', 'View All'])
            ->assertDontSee(['93%', 'Private cohort assignment', '40.00']);
        $this->get(route('dashboard', ['section' => 'record', 'record_activity' => 'all']))->assertOk()
            ->assertSee(['All Activity', 'Show Latest', 'My cohort assignment'])
            ->assertDontSee('Private cohort assignment');

        $this->get(route('students.show', $student))->assertRedirect(route('dashboard', ['section' => 'record']));
        $this->get(route('students.show', $peer))->assertForbidden();
        foreach (['teacher-attendance.index', 'classrooms.index', 'school.map', 'reports'] as $route) {
            $this->get(route($route))->assertForbidden();
        }

        foreach (['Friday' => 'My Friday lesson', 'Saturday' => 'My Saturday lesson'] as $day => $name) {
            $subject = $ownClass->subjects()->create(['name' => $name, 'code' => $day]);
            Schedule::create(['subject_id' => $subject->id, 'day' => $day, 'starts_at' => '08:00', 'ends_at' => '09:00', 'room' => '101']);
        }
        $this->get(route('dashboard', ['section' => 'timetable', 'date' => '2026-11-23']))
            ->assertOk()->assertSee(['Monday, Nov 23, 2026', 'View Weekly Timetable', 'My cohort lesson'])
            ->assertSee('view=weekly')->assertDontSee('My Friday lesson');
        $this->get(route('dashboard', ['section' => 'timetable', 'date' => '2026-11-23', 'view' => 'weekly']))
            ->assertOk()->assertSeeInOrder(['Monday', 'My cohort lesson', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'My Friday lesson'])
            ->assertSee(['View Daily Timetable', 'No lessons scheduled for this day.'])
            ->assertDontSee(['My Saturday lesson', 'Private cohort lesson']);
    }
}
