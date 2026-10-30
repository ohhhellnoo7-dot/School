<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Examination;
use App\Models\Fee;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\SchoolModules;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SchoolTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $role): User
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        return $user;
    }

    private function student(array $attributes = []): Student
    {
        return Student::create(array_merge([
            'student_number' => 'ST-'.fake()->unique()->numerify('#####'), 'name' => 'Test Student',
            'email' => fake()->unique()->safeEmail(), 'gender' => 'female', 'date_of_birth' => '2013-04-12',
            'school_class_id' => SchoolClass::create(['name' => fake()->unique()->word(), 'section' => 'A', 'capacity' => 30])->id,
            'guardian_name' => 'Test Guardian', 'guardian_phone' => '555-0100', 'enrollment_date' => today()->toDateString(), 'status' => 'active',
        ], $attributes));
    }

    public function test_class_program_session_and_weekly_schedule_are_scoped(): void
    {
        $admin = $this->account('administrator');
        $this->actingAs($admin)->post('/school/classes', ['name' => 'English 1', 'section' => 'A', 'program' => 'English', 'session' => 'Evening', 'room' => 'E1', 'capacity' => 30])->assertSessionHasNoErrors();
        $class = SchoolClass::where('name', 'English 1')->firstOrFail();
        $subject = Subject::create(['name' => 'History', 'code' => 'HIS-E1', 'school_class_id' => $class->id]);
        $qualified = $this->account('teacher'); $qualified->program = 'English'; $qualified->save();
        app(\App\Services\TeacherManagementService::class)->saveProfile($qualified, ['History'], null, [['weekday' => 'Monday', 'start_time' => '16:30', 'end_time' => '18:30', 'unavailable' => false]]);
        $subject->update(['teacher_id' => $qualified->id]);
        $lesson = ['subject_id' => $subject->id, 'day' => 'Monday', 'starts_at' => '16:30', 'ends_at' => '17:30', 'room' => 'E1'];
        $this->post('/school/schedules', $lesson)->assertSessionHasNoErrors();
        $this->post('/school/schedules', array_merge($lesson, ['day' => 'Tuesday', 'starts_at' => '08:00', 'ends_at' => '09:00']))->assertSessionHasErrors('starts_at');
        $this->get(route('classes.timetable', $class))->assertOk()->assertSee('History')->assertSee('16:30')->assertSee('Monday')->assertSee('English');
        $this->get('/school/classes/'.$class->id)->assertOk()->assertSee('History');

        $studentAccount = $this->account('student');
        $parent = $this->account('parent');
        $this->student(['school_class_id' => $class->id, 'user_id' => $studentAccount->id, 'parent_id' => $parent->id]);
        $this->get('/students?program=English&session=Evening')->assertOk()->assertViewHas('students', fn ($students) => $students->total() === 1)->assertSee(route('classes.timetable', $class));
        $this->actingAs($studentAccount)->get(route('classes.timetable', $class))->assertOk()->assertSee('History');
        $this->actingAs($parent)->get(route('classes.timetable', $class))->assertOk();
        $other = SchoolClass::create(['name' => 'Private', 'section' => 'B']);
        $this->get(route('classes.timetable', $other))->assertNotFound();
        $this->actingAs($studentAccount)->get(route('classes.timetable', $other))->assertNotFound();
    }

    public function test_guests_are_redirected_and_auth_pages_render(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('A good day to learn.');
        $this->get('/register')->assertNotFound();
    }

    public function test_public_registration_is_disabled_and_provisioned_accounts_can_sign_in(): void
    {
        $this->post('/register', ['name' => 'New Student', 'email' => 'new@example.com', 'password' => 'SecurePass123', 'password_confirmation' => 'SecurePass123', 'role' => 'administrator'])->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->actingAs($this->account('administrator'))->post('/school/users', ['name' => 'New Student', 'email' => 'new@example.com', 'password' => 'SecurePass123', 'role' => 'student'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('student', User::where('email', 'new@example.com')->firstOrFail()->role);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => 'new@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'new@example.com', 'password' => 'SecurePass123'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_demo_dashboards_and_every_authorized_module_render_for_all_roles(): void
    {
        $this->seed(SchoolSeeder::class);
        foreach (['admin', 'teacher', 'student', 'parent'] as $name) {
            $user = User::where('email', $name.'@school.test')->firstOrFail();
            $this->actingAs($user);
            $this->get('/dashboard')->assertOk()->assertSee($name === 'student' ? 'MY SCHOOL DAY' : 'Welcome back');
            $this->get('/students')->assertOk();
            foreach (SchoolModules::all() as $module => $meta) {
                $this->get('/school/'.$module)->assertStatus(SchoolModules::canView($user, $module) ? 200 : 403);
                $this->get('/school/'.$module.'/create')->assertStatus(SchoolModules::canWrite($user, $module) ? 200 : 403);
                if (SchoolModules::canWrite($user, $module)) {
                    $record = SchoolModules::query($module, $user)->first();
                    if ($record) {
                        $this->get('/school/'.$module.'/'.$record->id.'/edit')->assertOk();
                    }
                }
            }
        }
        $this->actingAs(User::where('role', 'administrator')->first())->get('/reports')->assertOk();
        $this->get('/students/create')->assertOk();
        $this->get('/students/'.Student::first()->id.'/edit')->assertOk();
        $this->get('/students/'.Student::first()->id)->assertOk()->assertSee('Examination results');
    }

    public function test_administrator_can_enroll_update_search_and_delete_student_with_photo(): void
    {
        Storage::fake('public');
        $this->actingAs($this->account('administrator'));
        $class = SchoolClass::create(['name' => 'Grade 7', 'section' => 'B']);
        $data = ['student_number' => 'ST-100', 'name' => 'New Student', 'gender' => 'male', 'date_of_birth' => '2012-01-01', 'email' => 'enrolled@example.com', 'school_class_id' => $class->id, 'guardian_name' => 'Guardian', 'guardian_phone' => '12345', 'enrollment_date' => today()->toDateString(), 'status' => 'active'];
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $this->post('/students', $data + ['photo' => UploadedFile::fake()->createWithContent('photo.png', $png)])->assertSessionHasNoErrors()->assertRedirect();
        $student = Student::firstOrFail();
        $path = $student->photo;
        Storage::disk('public')->assertExists($path);
        $this->put('/students/'.$student->id, array_merge($data, ['name' => 'Updated Student', 'photo' => null]))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($path, $student->fresh()->photo);
        $this->get('/students?search=Updated')->assertSee('Updated Student');
        $this->post('/students', $data)->assertSessionHasErrors(['email']);
        $this->delete('/students/'.$student->id)->assertRedirect('/students');
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_student_ids_are_automatic_unique_and_preserved_on_update(): void
    {
        $this->actingAs($this->account('administrator'));
        $class = SchoolClass::create(['name' => 'Grade 7', 'section' => 'B']);
        $data = ['name' => 'Auto Student', 'gender' => 'male', 'date_of_birth' => '2012-01-01', 'email' => 'auto@example.com', 'school_class_id' => $class->id, 'guardian_name' => 'Guardian', 'guardian_phone' => '12345', 'enrollment_date' => today()->toDateString(), 'status' => 'active'];

        $this->get('/students/create')->assertOk()->assertSee('Assigned automatically when enrolled');
        $this->post('/students', $data)->assertSessionHasNoErrors()->assertRedirect();
        $first = Student::where('email', $data['email'])->firstOrFail();
        $this->assertSame('STU001', $first->student_number);

        $this->post('/students', array_merge($data, ['email' => 'second@example.com', 'student_number' => 'CUSTOM-ID']))->assertSessionHasNoErrors();
        $second = Student::where('email', 'second@example.com')->firstOrFail();
        $this->assertSame('STU002', $second->student_number);
        $this->put('/students/'.$second->id, array_merge($data, ['email' => $second->email, 'student_number' => 'CHANGED']))->assertSessionHasNoErrors();
        $this->assertSame('STU002', $second->fresh()->student_number);

        $this->delete('/students/'.$second->id)->assertRedirect();
        $this->post('/students', array_merge($data, ['email' => 'third@example.com']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students', ['email' => 'third@example.com', 'student_number' => 'STU003']);
    }

    public function test_parent_and_student_cannot_access_unrelated_records_or_modify_students(): void
    {
        $parent = $this->account('parent');
        $studentUser = $this->account('student');
        $own = $this->student(['parent_id' => $parent->id, 'user_id' => $studentUser->id, 'name' => 'Linked Child']);
        $other = $this->student(['name' => 'Private Child']);
        foreach ([$parent, $studentUser] as $user) {
            $this->actingAs($user)->get('/students')->assertSee('Linked Child')->assertDontSee('Private Child');
            $this->get('/students/'.$own->id)->assertOk();
            $this->get('/students/'.$other->id)->assertForbidden();
            $this->get('/students/create')->assertForbidden();
            $this->post('/students', [])->assertForbidden();
            $this->put('/students/'.$own->id, [])->assertForbidden();
            $this->delete('/students/'.$own->id)->assertForbidden();
            $this->get('/reports')->assertForbidden();
        }
        $this->actingAs($parent);
        $fee = Fee::create(['student_id' => $other->id, 'description' => 'Private tuition', 'amount' => 100, 'paid' => 0, 'due_date' => today()]);
        $this->get('/school/fees')->assertDontSee('Private tuition');
        $this->put('/school/fees/'.$fee->id, [])->assertNotFound();
    }

    public function test_teacher_can_manage_only_assigned_attendance_and_subject_grades(): void
    {
        $teacher = $this->account('teacher');
        $otherTeacher = $this->account('teacher');
        $own = $this->student();
        $own->schoolClass->update(['teacher_id' => $teacher->id]);
        $other = $this->student();
        $subject = Subject::create(['name' => 'Math', 'code' => 'M1', 'school_class_id' => $own->school_class_id, 'teacher_id' => $teacher->id]);
        $exam = Examination::create(['name' => 'Midterm', 'subject_id' => $subject->id, 'date' => today(), 'total_marks' => 100]);
        $this->actingAs($teacher)->post('/school/attendance', ['student_id' => $own->id, 'date' => today()->toDateString(), 'status' => 'present'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/school/attendance', ['student_id' => $other->id, 'date' => today()->toDateString(), 'status' => 'present'])->assertSessionHasErrors('student_id');
        $this->post('/school/grades', ['student_id' => $own->id, 'examination_id' => $exam->id, 'marks' => 101])->assertSessionHasErrors('marks');
        $this->post('/school/grades', ['student_id' => $own->id, 'examination_id' => $exam->id, 'marks' => 88])->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/school/grades', ['student_id' => $other->id, 'examination_id' => $exam->id, 'marks' => 88])->assertSessionHasErrors('student_id');
        $attendance = Attendance::first();
        $this->actingAs($otherTeacher)->put('/school/attendance/'.$attendance->id, [])->assertNotFound();
        $this->actingAs($teacher)->post('/school/users', [])->assertForbidden();
    }

    public function test_fee_overpayment_and_duplicate_attendance_are_rejected(): void
    {
        $this->actingAs($this->account('administrator'));
        $student = $this->student();
        $this->post('/school/fees', ['student_id' => $student->id, 'description' => 'Tuition', 'amount' => 100, 'paid' => 101, 'due_date' => today()->toDateString()])->assertSessionHasErrors('paid');
        $data = ['student_id' => $student->id, 'date' => today()->toDateString(), 'status' => 'present'];
        $this->post('/school/attendance', $data)->assertSessionHasNoErrors();
        $this->post('/school/attendance', $data)->assertSessionHasErrors('date');
    }

    public function test_user_roles_can_be_assigned_only_by_admin_and_self_lockout_is_blocked(): void
    {
        $admin = $this->account('administrator');
        $this->actingAs($admin);
        $this->post('/school/users', ['name' => 'Parent Account', 'email' => 'parent@example.com', 'password' => 'SecurePass123', 'role' => 'parent'])->assertSessionHasNoErrors();
        $this->assertSame('parent', User::where('email', 'parent@example.com')->first()->role);
        $this->put('/school/users/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'student'])->assertSessionHasErrors('role');
        $this->delete('/school/users/'.$admin->id)->assertSessionHasErrors('record');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'administrator']);
    }

    public function test_administrator_crud_for_all_school_modules(): void
    {
        $this->actingAs($this->account('administrator'));
        $student = $this->student();
        $teacher = $this->account('teacher');
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'TEST-MATH', 'school_class_id' => $student->school_class_id, 'teacher_id' => $teacher->id]);
        app(\App\Services\TeacherManagementService::class)->saveProfile($teacher, ['Mathematics'], null, [['weekday' => 'Monday', 'start_time' => '07:00', 'end_time' => '11:00', 'unavailable' => false]]);
        $exam = Examination::create(['name' => 'Test exam', 'subject_id' => $subject->id, 'date' => today(), 'total_marks' => 100]);
        $cases = [
            'parents' => ['name' => 'New Guardian', 'email' => 'newguardian@example.com', 'password' => 'SecurePass123'],
            'teachers' => ['name' => 'New Teacher', 'email' => 'newteacher@example.com', 'password' => 'SecurePass123'],
            'classes' => ['name' => 'New Class', 'section' => 'B', 'capacity' => 35, 'room' => 'Room 2', 'teacher_id' => $teacher->id],
            'subjects' => ['name' => 'New Subject', 'code' => 'NEW-SUB', 'school_class_id' => $student->school_class_id, 'teacher_id' => null],
            'attendance' => ['student_id' => $student->id, 'date' => today()->toDateString(), 'status' => 'present'],
            'examinations' => ['name' => 'New Exam', 'subject_id' => $subject->id, 'date' => today()->toDateString(), 'total_marks' => 100],
            'grades' => ['student_id' => $student->id, 'examination_id' => $exam->id, 'marks' => 95],
            'fees' => ['student_id' => $student->id, 'description' => 'Term fee', 'amount' => 200, 'paid' => 50, 'paid_at' => today()->toDateString(), 'due_date' => today()->addDays(7)->toDateString()],
            'assignments' => ['subject_id' => $subject->id, 'title' => 'Practice', 'description' => 'Complete the exercises.', 'due_date' => today()->addDays(7)->toDateString()],
            'schedules' => ['subject_id' => $subject->id, 'day' => 'Monday', 'starts_at' => '08:00', 'ends_at' => '09:00', 'room' => 'Room 1'],
            'announcements' => ['title' => 'Welcome', 'body' => 'Welcome to school.', 'audience' => 'all', 'published_at' => today()->toDateString()],
            'users' => ['name' => 'New Parent', 'email' => 'newparent@example.com', 'password' => 'SecurePass123', 'role' => 'parent'],
        ];
        foreach ($cases as $module => $data) {
            $model = SchoolModules::get($module)['model'];
            $this->post('/school/'.$module, $data)->assertSessionHasNoErrors()->assertRedirect('/school/'.$module);
            $record = $model::latest('id')->firstOrFail();
            $this->get('/school/'.$module.'/'.$record->id.'/edit')->assertOk();
            $updated = match ($module) {
                'attendance' => ['status' => 'late'], 'grades' => ['marks' => 90], 'fees' => ['paid' => 100],
                'assignments', 'announcements' => ['title' => 'Updated title'], 'schedules' => ['room' => 'Room 9'],
                default => ['name' => 'Updated name'],
            };
            $this->put('/school/'.$module.'/'.$record->id, array_merge($data, $updated))->assertSessionHasNoErrors()->assertRedirect('/school/'.$module);
            $this->assertDatabaseHas($record->getTable(), ['id' => $record->id] + $updated);
            $this->delete('/school/'.$module.'/'.$record->id)->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseMissing($record->getTable(), ['id' => $record->id]);
        }
    }

    public function test_published_result_constraints_survive_examination_edits(): void
    {
        $this->actingAs($this->account('administrator'));
        $student = $this->student();
        $subject = Subject::create(['name' => 'Math', 'code' => 'MATH-1', 'school_class_id' => $student->school_class_id]);
        $exam = Examination::create(['name' => 'Assessment', 'subject_id' => $subject->id, 'date' => today(), 'total_marks' => 100]);
        Grade::create(['student_id' => $student->id, 'examination_id' => $exam->id, 'marks' => 95]);
        $this->put('/school/examinations/'.$exam->id, ['name' => 'Assessment', 'subject_id' => $subject->id, 'date' => today()->toDateString(), 'total_marks' => 90])->assertSessionHasErrors('total_marks');
    }
}
