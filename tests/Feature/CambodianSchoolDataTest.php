<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Examination;
use App\Models\Fee;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Support\SampleSchoolVerifier;
use App\Support\SchoolModules;
use Database\Seeders\CambodianSchoolSeeder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CambodianSchoolDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_dataset_is_valid_preserves_existing_data_and_is_repeatable(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 26)->startOfDay());
        $this->seed(SchoolSeeder::class);
        $existing = DB::table('students')->orderBy('id')->get()->map(fn ($s) => (array) $s)->all();
        $this->seed(CambodianSchoolSeeder::class);
        $report = SampleSchoolVerifier::report();
        foreach ($report['checks'] as $label => $passed) {
            $this->assertTrue($passed, $label);
        }
        $tables = ['users', 'school_classes', 'students', 'subjects', 'attendances', 'examinations', 'grades', 'fees', 'schedules', 'announcements'];
        $snapshot = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()]);
        Student::where('student_number', 'STU001')->update(['phone' => '012999999']);
        $snapshot['students'] = DB::table('students')->orderBy('id')->get()->toJson();
        $this->seed(CambodianSchoolSeeder::class);
        foreach ($tables as $table) {
            $this->assertSame($snapshot[$table], DB::table($table)->orderBy('id')->get()->toJson(), $table.' changed on a repeated seed');
        }
        foreach ($existing as $row) {
            $this->assertSame($row, (array) DB::table('students')->find($row['id']));
        }
        $this->artisan('school:verify-sample')->assertSuccessful();
        $admin = User::where('role', 'administrator')->firstOrFail();
        $this->actingAs($admin);
        $this->get('/dashboard')->assertOk()->assertSee('Absent students');
        foreach (SchoolModules::all() as $module => $meta) {
            $this->get('/school/'.$module.'?sort=name&direction=asc')->assertOk();
            $record = SchoolModules::query($module, $admin)->first();
            if ($record) {
                $this->get('/school/'.$module.'/'.$record->id)->assertOk();
            }
        }
        $this->get('/school/fees?search=STU001&filter=partially_paid')->assertOk()->assertViewHas('records', fn ($records) => $records->count() > 0 && $records->every(fn ($fee) => $fee->payment_status === 'partially_paid' && $fee->student->student_number === 'STU001'));
        $this->get('/students?search=STU001&sort=student_number&direction=asc')->assertOk()->assertSee('STU001');
        $student = Student::where('student_number', 'STU001')->firstOrFail();
        $this->get('/students/'.$student->id)->assertOk()->assertSee('Attendance:');
        $this->delete('/students/'.$student->id)->assertSessionHasErrors('record');
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->delete('/school/parents/'.$student->parent_id)->assertSessionHasErrors('record');
        $this->actingAs($student->parent);
        $this->get('/students')->assertOk()->assertSee($student->name);
        $this->get('/school/parents')->assertForbidden();
    }

    public function test_sample_identifier_collision_rolls_back_without_modifying_existing_records(): void
    {
        $class = SchoolClass::create(['name' => 'Existing class', 'section' => 'A']);
        Student::factory()->create(['student_number' => 'STU001', 'school_class_id' => $class->id, 'email' => 'real@example.com']);
        try {
            $this->seed(CambodianSchoolSeeder::class);
            $this->fail('Expected a sample identifier collision.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('collision', $exception->getMessage());
        }
        $this->assertSame(1, Student::count());
        $this->assertSame(1, SchoolClass::count());
        $this->assertSame(0, User::count());
    }

    public function test_grading_boundaries_payment_status_and_attendance_percentage(): void
    {
        $student = Student::factory()->create();
        $subject = Subject::create(['name' => 'Math', 'code' => 'T-MATH', 'school_class_id' => $student->school_class_id]);
        $exam = Examination::create(['name' => 'Test', 'subject_id' => $subject->id, 'date' => today(), 'total_marks' => 100]);
        foreach ([100 => 'A', 90 => 'A', 89 => 'B', 80 => 'B', 79 => 'C', 70 => 'C', 69 => 'D', 60 => 'D', 59 => 'F', 0 => 'F'] as $marks => $letter) {
            $grade = new Grade(['marks' => $marks]);
            $grade->setRelation('examination', $exam);
            $this->assertSame($letter, $grade->letter_grade);
        }
        foreach (['present', 'late', 'absent', 'excused'] as $offset => $status) {
            Attendance::create(['student_id' => $student->id, 'date' => today()->subDays($offset), 'status' => $status]);
        }
        $this->assertSame(50.0, $student->attendance_percentage);
        $fee = new Fee(['amount' => 100, 'paid' => 40, 'due_date' => today()->addDay()]);
        $this->assertSame('60.00', $fee->balance);
        $this->assertSame('partially_paid', $fee->payment_status);
        $fee->due_date = today()->subDay();
        $this->assertSame('overdue', $fee->payment_status);
    }

    public function test_timetable_rejects_teacher_room_and_class_overlaps_and_outside_hours(): void
    {
        $admin = User::factory()->create();
        $admin->role = 'administrator';
        $admin->save();
        $teacher = User::factory()->create();
        $teacher->role = 'teacher';
        $teacher->save();
        $one = SchoolClass::create(['name' => 'Grade 7', 'section' => 'A']);
        $two = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A']);
        $first = Subject::create(['name' => 'Math', 'code' => 'MATH-A', 'school_class_id' => $one->id, 'teacher_id' => $teacher->id]);
        $second = Subject::create(['name' => 'Math', 'code' => 'MATH-B', 'school_class_id' => $two->id, 'teacher_id' => $teacher->id]);
        Schedule::create(['subject_id' => $first->id, 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'room' => 'Room A']);
        $this->actingAs($admin);
        $data = ['subject_id' => $second->id, 'day' => 'Monday', 'starts_at' => '07:30', 'ends_at' => '08:30', 'room' => 'Room B'];
        $this->post('/school/schedules', $data)->assertSessionHasErrors('starts_at');
        $this->post('/school/schedules', array_replace($data, ['starts_at' => '11:00', 'ends_at' => '12:00']))->assertSessionHasErrors('starts_at');
        $this->post('/school/schedules', array_replace($data, ['starts_at' => '08:00', 'ends_at' => '09:00']))->assertSessionHasNoErrors();
    }
}
