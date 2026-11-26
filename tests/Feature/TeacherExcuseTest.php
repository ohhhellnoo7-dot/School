<?php

namespace Tests\Feature;

use App\Models\TeacherAttendance;
use App\Models\TeacherExcuse;
use App\Models\User;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\SchoolLocation;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeacherExcuseTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_excuse_workflow_updates_the_notification_and_attendance_report(): void
    {
        $this->travelTo(Carbon::parse('2026-11-26 10:00'));
        $teacher = User::factory()->create(['role' => 'teacher']);
        $otherTeacher = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'administrator']);
        $class = SchoolClass::create(['name' => 'Grade 5', 'section' => 'A', 'session' => 'Morning', 'teacher_id' => $teacher->id]);
        $subject = Subject::create(['name' => 'English', 'code' => 'ENG-EXCUSE', 'school_class_id' => $class->id, 'teacher_id' => $teacher->id]);
        Schedule::create(['subject_id' => $subject->id, 'day' => 'Thursday', 'starts_at' => '09:00', 'ends_at' => '11:00', 'room' => 'A']);
        SchoolLocation::create(['name' => 'School', 'is_active' => true, 'latitude' => 11.6, 'longitude' => 104.94, 'radius_meters' => 100, 'created_by' => $admin->id]);
        $payload = ['starts_on' => '2026-11-26', 'days' => 5, 'reason' => 'Family appointment'];

        $this->actingAs($teacher)->get(route('dashboard'))->assertOk()->assertSee('Ask permission (excuse)');
        $this->post(route('teacher-excuses.store'), $payload)->assertRedirect(route('dashboard'));
        $this->post(route('teacher-excuses.store'), $payload)->assertSessionHasErrorsIn('teacherExcuse', ['starts_on']);
        $excuse = TeacherExcuse::sole();
        $this->assertDatabaseCount('teacher_attendances', 0);
        $this->get(route('teacher-excuses.index'))->assertOk()->assertSee('Family appointment')->assertSee('Pending');
        $this->patch(route('teacher-excuses.review', $excuse), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($otherTeacher)->get(route('teacher-excuses.index'))->assertOk()->assertDontSee('Family appointment');

        $this->actingAs($admin)->getJson(route('teacher-excuses.count'))->assertExactJson(['count' => 1]);
        $this->get(route('modules.index', 'teachers'))->assertOk()->assertSee('Review excuse requests');
        $this->get(route('teacher-excuses.index'))->assertOk()->assertSee('Family appointment')->assertSee('Approve')->assertSee('Reject');
        $daily = TeacherAttendance::where('teacher_id', $teacher->id)->whereDate('date', today())->first()
            ?? new TeacherAttendance(['teacher_id' => $teacher->id, 'date' => today()]);
        $daily->fill(['status' => 'present', 'recorded_by' => $admin->id])->save();
        $sessionCount = \App\Models\TeacherSessionAttendance::count();
        $this->patch(route('teacher-excuses.review', $excuse), ['status' => 'approved'])->assertRedirect(route('teacher-excuses.index'));
        $this->patchJson(route('teacher-excuses.review', $excuse), ['status' => 'rejected'])->assertUnprocessable();
        $this->getJson(route('teacher-excuses.count'))->assertExactJson(['count' => 0]);
        $this->post(route('dashboard.teacher-attendance.bulk', 'all-present'))->assertRedirect();
        $this->get(route('modules.index', 'teachers'))->assertOk()
            ->assertViewHas('attendance', fn ($attendance) => $attendance['counts']->get('excused') === 1)
            ->assertSee('Family appointment');
        $this->get(route('teacher-attendance.monthly', ['date' => '2026-11-30', 'status' => 'excused']))
            ->assertOk()->assertSee('Family appointment');
        $this->get(route('teacher-attendance.monthly', ['date' => '2026-11-26', 'session' => 'Morning', 'status' => 'excused']))
            ->assertOk()->assertSee('Family appointment');
        $this->post(route('dashboard.teacher-attendance.bulk', 'scan-in'), ['session' => 'Morning'])->assertRedirect();
        app(\App\Services\TeacherScanAttendance::class)->refresh($teacher, '2026-11-26');
        $this->assertDatabaseCount('teacher_session_attendances', $sessionCount);
        foreach (['2026-11-26', '2026-11-30'] as $date) {
            $this->get(route('attendance.people', ['kind' => 'teachers', 'status' => 'excused', 'date' => $date]))
                ->assertOk()->assertSee($teacher->name)->assertSee('Family appointment')->assertSee('Report date')
                ->assertSee('name="date" value="'.$date.'" form="attendance-people-filters"', false);
        }
        $this->assertSame(5, TeacherAttendance::where('teacher_id', $teacher->id)->where('status', 'excused')->count());
        $this->actingAs($teacher)->get(route('teacher-excuses.index'))->assertOk()->assertSee('Approved');
        $this->get(route('dashboard'))->assertOk()->assertSee('Excused today')->assertSee('Family appointment')->assertDontSee('Teaching now');
        $this->getJson(route('teachers.schedule.status', $teacher))->assertOk()->assertJsonPath('status', 'Excused');
        $this->postJson(route('school.map.scan'), [
            'latitude' => 11.6, 'longitude' => 104.94, 'accuracy' => 10, 'sampled_at' => now()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonPath('status', 'excused');
        $this->assertDatabaseCount('teacher_session_attendances', $sessionCount);
        $this->assertFalse(\App\Models\TeacherSessionAttendance::whereNotNull('scanned_at')->exists());
    }

    public function test_rejection_does_not_record_attendance(): void
    {
        $this->travelTo(Carbon::parse('2026-11-26 10:00'));
        $teacher = User::factory()->create(['role' => 'teacher']);
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($teacher)->post(route('teacher-excuses.store'), [
            'starts_on' => '2026-11-26', 'days' => 1, 'reason' => 'Personal matter',
        ])->assertRedirect();
        $this->actingAs($admin)->patch(route('teacher-excuses.review', TeacherExcuse::sole()), ['status' => 'rejected'])
            ->assertRedirect(route('teacher-excuses.index'));
        $this->getJson(route('teacher-excuses.count'))->assertExactJson(['count' => 0]);
        $this->assertDatabaseCount('teacher_attendances', 0);
        $this->actingAs($teacher)->get(route('teacher-excuses.index'))->assertOk()->assertSee('Rejected');
    }
}
