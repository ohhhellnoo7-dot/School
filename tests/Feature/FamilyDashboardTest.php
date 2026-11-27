<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\ClassAttendance;
use App\Models\Examination;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FamilyDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_view_every_day_and_compare_program_attendance_for_a_linked_child(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $english = SchoolClass::create(['name' => 'Grade 8', 'section' => 'A', 'program' => 'English', 'session' => 'Afternoon']);
        $khmer = SchoolClass::create(['name' => 'Grade 8', 'section' => 'B', 'program' => 'Khmer', 'session' => 'Morning']);
        $child = Student::factory()->create(['parent_id' => $teacher->id, 'school_class_id' => $english->id]);
        $child->studyClasses()->attach($khmer);
        Attendance::create(['student_id' => $child->id, 'date' => '2028-02-02', 'status' => 'present']);

        foreach (['2028-02-01' => ['present', 'late'], '2028-02-29' => ['absent', 'excused']] as $date => $statuses) {
            foreach ([$english, $khmer] as $index => $class) {
                ClassAttendance::create(['student_id' => $child->id, 'school_class_id' => $class->id, 'date' => $date, 'status' => $statuses[$index]]);
            }
        }

        foreach (['2028-02' => 29, '2028-03' => 31] as $month => $days) {
            $response = $this->actingAs($teacher)->get(route('students.family.show', [$child, 'tab' => 'attendance', 'month' => $month]))
                ->assertOk()->assertSee(['Attendance report', 'Show data', 'English', 'Khmer', 'Not recorded']);
            $response->assertDontSee('id="family-month-heading-daily"', false);
            foreach ([$english, $khmer] as $index => $class) {
                $label = 'aria-labelledby="family-month-heading-'.$class->id.'"';
                $response->assertSee($label, false);
                $table = str($response->getContent())->after($label)->before('</table>')->toString();
                $this->assertSame($days, substr_count($table, '<time datetime='));
                if ($days === 29) {
                    foreach (['2028-02-01' => ['present', 'late'], '2028-02-02' => ['present', 'present'], '2028-02-29' => ['absent', 'excused']] as $date => $statuses) {
                        $this->assertMatchesRegularExpression('/data-date="'.$date.'"[^>]*>\s*<span[^>]*data-tone="'.$statuses[$index].'"/', $table);
                    }
                }
            }
        }
    }

    public function test_teacher_my_children_shows_tuition_coverage_only_for_linked_children(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $child = Student::factory()->create(['parent_id' => $teacher->id]);
        foreach (['Khmer', 'English', 'Chinese'] as $program) {
            $class = SchoolClass::create(['name' => 'Grade 8', 'section' => $program, 'program' => $program, 'session' => 'Evening']);
            $child->studyClasses()->attach($class);
        }
        $other = Student::factory()->create(['name' => 'Unrelated Tuition Student']);
        foreach ([$child, $other] as $student) {
            $student->fees()->create([
                'description' => 'Monthly tuition', 'amount' => '35.00', 'paid' => '35.00',
                'due_date' => today(), 'paid_at' => today(), 'payment_plan' => 'monthly', 'program' => 'Khmer',
            ]);
        }

        $this->actingAs($teacher)->get(route('students.index', ['my_children' => 1]))
            ->assertOk()->assertSee(['Tuition Payment Coverage', 'Paid period active', 'Payment Timeline', '35.00', $child->name])
            ->assertSee(route('students.family.show', [$child, 'tab' => 'fees']), false)
            ->assertDontSee($other->name)
            ->assertViewHas('paymentCoverage', fn ($coverage) => $coverage->keys()->all() === [$child->id]
                && $coverage[$child->id]->count() === 3 && $coverage[$child->id]['Khmer']['months'] === 1
                && $coverage[$child->id]['English'] === null && $coverage[$child->id]['Chinese'] === null);
        $this->actingAs(User::factory()->create(['role' => 'teacher']))
            ->get(route('students.index', ['my_children' => 1]))->assertOk()->assertDontSee('Tuition Payment Coverage');
    }

    public function test_linked_guardian_can_edit_only_their_childs_profile_fields(): void
    {
        Storage::fake('public');
        $guardian = User::factory()->create(['role' => 'parent']);
        $studentAccount = User::factory()->create(['role' => 'student']);
        $child = Student::factory()->create(['parent_id' => $guardian->id, 'user_id' => $studentAccount->id]);
        $other = Student::factory()->create();
        $edit = route('students.family.edit', $child);
        $update = route('students.family.update', $child);
        $photo = UploadedFile::fake()->createWithContent('child.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));

        $this->actingAs($guardian)->get($edit)->assertOk()->assertSee('Edit child profile');
        $this->patch($update, ['name' => 'Updated Child', 'phone' => '012222222', 'address' => 'Updated address', 'status' => 'inactive', 'parent_id' => null, 'photo' => $photo])
            ->assertRedirect(route('students.family.show', $child));
        $child->refresh();
        $this->assertSame('Updated Child', $child->name);
        $this->assertSame('012222222', $child->phone);
        $this->assertSame('Updated address', $child->address);
        $this->assertSame('active', $child->status);
        $this->assertEquals($guardian->id, $child->parent_id);
        Storage::disk('public')->assertExists($child->photo);
        $this->get(route('students.family.show', $child))->assertOk()->assertSee(route('students.photo', $child));
        $this->get(route('students.family.edit', $other))->assertNotFound();
        $this->patch(route('students.family.update', $other), ['name' => 'Blocked'])->assertNotFound();

        $photoUrl = route('students.photo', $child);
        $this->actingAs($studentAccount)->get(route('dashboard'))->assertOk()
            ->assertSee('class="user-avatar" src="'.$photoUrl.'"', false);
        $profile = $this->get(route('profile.edit'))->assertOk();
        $this->assertMatchesRegularExpression('/id="profile-preview"\s+src="'.preg_quote($photoUrl, '/').'"/', $profile->getContent());
        $this->get($photoUrl)->assertOk();
    }

    public function test_family_dashboard_shows_linked_children_and_their_real_records(): void
    {
        $guardian = User::factory()->create(['role' => 'parent']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $class = SchoolClass::create(['name' => 'Grade 5', 'section' => 'A', 'program' => 'English', 'session' => 'Afternoon', 'teacher_id' => $teacher->id]);
        $otherClass = SchoolClass::create(['name' => 'Grade 7', 'section' => 'B', 'program' => 'Khmer', 'session' => 'Morning']);
        $child = Student::factory()->create(['name' => 'Linked Child', 'parent_id' => $guardian->id, 'school_class_id' => $class->id]);
        $child->studyClasses()->attach($otherClass);
        $sibling = Student::factory()->create(['name' => 'Linked Sibling', 'parent_id' => $guardian->id]);
        $other = Student::factory()->create(['name' => 'Unrelated Child']);
        Attendance::create(['student_id' => $child->id, 'date' => '2026-11-02', 'status' => 'present']);
        Attendance::create(['student_id' => $child->id, 'date' => '2026-11-03', 'status' => 'absent', 'notes' => 'School office notified']);
        Attendance::create(['student_id' => $other->id, 'date' => '2026-11-03', 'status' => 'late', 'notes' => 'Private attendance remark']);
        $subject = Subject::create(['name' => 'Literacy', 'code' => 'FAM-LIT', 'school_class_id' => $class->id, 'teacher_id' => $teacher->id]);
        Schedule::create(['subject_id' => $subject->id, 'day' => 'Monday', 'starts_at' => '13:00', 'ends_at' => '14:00', 'room' => 'Room B']);
        $exam = Examination::create(['name' => 'Reading assessment', 'subject_id' => $subject->id, 'date' => '2026-11-01', 'total_marks' => 100]);
        Grade::create(['student_id' => $child->id, 'examination_id' => $exam->id, 'marks' => 86, 'remarks' => 'Good comprehension']);
        $child->fees()->create(['description' => 'English tuition', 'amount' => '35.00', 'paid' => '10.00', 'currency' => 'USD', 'due_date' => today()]);
        $child->fees()->create(['description' => 'Khmer tuition', 'amount' => '90000.00', 'paid' => '0.00', 'currency' => 'KHR', 'due_date' => today()]);
        Announcement::create(['title' => 'Family meeting', 'body' => 'Family meeting this week.', 'audience' => 'parent', 'published_at' => today(), 'created_by' => $teacher->id]);
        Announcement::create(['title' => 'Staff-only update', 'body' => 'Internal update.', 'audience' => 'teacher', 'published_at' => today(), 'created_by' => $teacher->id]);

        $this->actingAs($guardian)->get(route('students.index'))->assertOk()
            ->assertSee(['family-dashboard-hero.png', 'Linked Child', 'Linked Sibling', 'English · Grade 5 A · Afternoon', 'Khmer · Grade 7 B · Morning', 'Reading assessment', 'Family meeting', 'Learning Snapshot', 'View All'])
            ->assertDontSee(['Unrelated Child', 'Staff-only update', 'Advanced filters']);
        $this->get(route('students.index', ['all_updates' => 1]))->assertOk()->assertSee('Family meeting')->assertDontSee('Staff-only update');
        $this->get(route('students.index', ['search' => 'Unrelated Child']))->assertOk()->assertSee('No linked children match your search');

        $this->get(route('students.family.show', $child))->assertOk()->assertSee(['Switch child', $sibling->name, $teacher->name]);
        $this->get(route('students.family.show', [$child, 'tab' => 'attendance', 'month' => '2026-11']))->assertOk()
            ->assertSee(['50%', 'Attendance Rate', 'Monthly Attendance', 'Attendance Records', 'School office notified'])
            ->assertDontSee('Private attendance remark');
        $this->get(route('modules.index', ['module' => 'attendance', 'child' => $child->id, 'month' => '2026-11']))
            ->assertOk()->assertSee(['Linked Child', 'Switch child', 'School office notified'])->assertDontSee('Private attendance remark');
        $this->get(route('modules.index', ['module' => 'attendance', 'child' => $other->id]))->assertNotFound();
        $this->get(route('students.family.show', [$child, 'tab' => 'timetable', 'day' => 'Monday']))->assertOk()->assertSee(['Literacy', 'Room B', '13:00']);
        $this->get(route('students.family.show', [$child, 'tab' => 'results', 'period' => '2026-2']))->assertOk()->assertSee('Good comprehension');
        $this->get(route('students.family.show', [$child, 'tab' => 'results', 'period' => '2026-1']))->assertOk()->assertDontSee('Good comprehension');
        $this->get(route('students.family.show', [$child, 'tab' => 'fees']))->assertOk()->assertSee(['25.00 USD', '90000.00 KHR']);
        foreach (['overview', 'attendance', 'timetable', 'results', 'fees'] as $tab) {
            $this->get(route('students.family.show', [$other, 'tab' => $tab]))->assertNotFound();
        }
    }
}
