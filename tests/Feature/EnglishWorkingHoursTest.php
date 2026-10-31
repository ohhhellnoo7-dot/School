<?php

namespace Tests\Feature;

use App\Models\{SchoolClass, Subject, User};
use App\Services\{EnglishProgramSetup, TeacherManagementService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnglishWorkingHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_time_covers_eight_hours_each_weekday_and_excludes_lunch(): void
    {
        $periods = EnglishProgramSetup::workingPeriods();
        $this->assertCount(10, $periods);
        $this->assertSame(2400, collect($periods)->sum(fn ($p) => TeacherManagementService::duration($p['start_time'], $p['end_time'])));
        $this->assertFalse(collect($periods)->contains(fn ($p) => $p['start_time'] < '13:00' && $p['end_time'] > '11:00'));
    }

    public function test_part_time_supports_morning_afternoon_and_evening(): void
    {
        foreach (['07:00', '15:00', '17:30'] as $start) {
            $periods = EnglishProgramSetup::workingPeriods(true, $start);
            $this->assertCount(5, $periods);
            $this->assertSame(600, collect($periods)->sum(fn ($p) => TeacherManagementService::duration($p['start_time'], $p['end_time'])));
        }
    }

    public function test_part_time_cannot_cross_lunch(): void
    {
        $this->expectException(ValidationException::class);
        EnglishProgramSetup::workingPeriods(true, '10:00');
    }

    public function test_session_rule_allows_a_new_afternoon_class_but_rejects_morning_class_or_room_changes(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'English']);
        $morning = SchoolClass::create(['name' => 'Grade 5', 'section' => 'A', 'program' => 'English', 'session' => 'Morning']);
        $otherMorning = SchoolClass::create(['name' => 'Grade 6', 'section' => 'A', 'program' => 'English', 'session' => 'Morning']);
        $afternoon = SchoolClass::create(['name' => 'Grade 6', 'section' => 'A', 'program' => 'English', 'session' => 'Afternoon']);
        $subject = Subject::create(['name' => 'English Grammar', 'code' => 'SESSION-TEST', 'school_class_id' => $morning->id]);
        $existing = new \App\Models\Schedule(['subject_id' => $subject->id, 'school_class_id' => $morning->id, 'teacher_id' => $teacher->id, 'entry_type' => 'academic', 'room' => 'E01', 'day' => 'Monday', 'starts_at' => '07:00', 'ends_at' => '08:00', 'academic_year' => '2026-2027']);
        $existing->saveQuietly();
        $rules = app(TeacherManagementService::class);
        $next = new \App\Models\Schedule(['room' => 'E01', 'day' => 'Monday', 'academic_year' => '2026-2027']);
        $rules->validateEnglishSession($teacher, $next, $morning);
        $rules->validateEnglishSession($teacher, new \App\Models\Schedule(['room' => 'E02', 'day' => 'Monday', 'academic_year' => '2026-2027']), $afternoon);
        $rules->validateEnglishSession($teacher, new \App\Models\Schedule(['room' => 'E02', 'day' => 'Tuesday', 'academic_year' => '2026-2027']), $otherMorning);
        $rules->validateEnglishSession($teacher, new \App\Models\Schedule(['room' => 'E02', 'day' => 'Monday', 'academic_year' => '2027-2028']), $otherMorning);
        $rules->validateEnglishSession($teacher, $existing, $morning);
        foreach ([[$otherMorning, 'E01'], [$morning, 'E02']] as [$class, $room]) {
            try {
                $rules->validateEnglishSession($teacher, new \App\Models\Schedule(['room' => $room, 'day' => 'Monday', 'academic_year' => '2026-2027']), $class);
                $this->fail('A teacher must stay in one class and room throughout a session.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('only one class in one classroom per session', $exception->getMessage());
            }
        }
    }

    public function test_teacher_form_saves_full_time_and_part_time_limits(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $teacher = User::factory()->create(['role' => 'teacher', 'program' => 'English']);
        $class = SchoolClass::create(['name' => 'Grade 1', 'section' => 'A', 'program' => 'English', 'session' => 'Morning']);
        Subject::create(['school_class_id' => $class->id, 'name' => 'English Grammar', 'code' => 'ENG-TEST']);
        foreach (['EnglishFullTime' => 2400, 'EnglishPartTime' => 600] as $arrangement => $minutes) {
            $this->actingAs($admin)->put(route('teacher-management.update', $teacher), ['name' => $teacher->name, 'email' => $teacher->email, 'gender' => 'other', 'program' => 'English', 'subject1' => 'English Grammar', 'working_sessions' => $arrangement, 'part_time_start' => '15:00', 'status' => 'active'])
                ->assertSessionHasNoErrors()->assertRedirect();
            $rules = app(TeacherManagementService::class);
            $this->assertSame($minutes, $rules->limit($teacher));
            $this->assertTrue($rules->onDuty($teacher, 'Monday', '15:00', '17:00'));
            $this->assertSame($arrangement === 'EnglishFullTime', $rules->onDuty($teacher, 'Monday', '07:00', '08:00'));
        }
    }

    public function test_rebuild_covers_all_grades_and_sessions_within_each_teachers_working_hours(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        User::factory()->count(10)->create(['role' => 'teacher', 'program' => 'English', 'status' => 'active']);
        foreach (range(1, 12) as $grade) {
            $group = \App\Models\ClassGroup::create(['name' => 'Grade '.$grade, 'grade' => $grade, 'program' => 'English', 'status' => 'active']);
            $room = \App\Models\Classroom::create(['room_number' => 'E'.$grade, 'name' => 'English '.$grade, 'capacity' => 40, 'status' => 'active']);
            foreach (['Morning' => ['07:00', '11:00'], 'Afternoon' => ['13:00', '17:00'], 'Evening' => ['17:30', '18:30']] as $session => [$from, $to]) {
                $classSession = \App\Models\ClassSession::create(['class_group_id' => $group->id, 'session' => $session, 'starts_at' => $from, 'ends_at' => $to]);
                SchoolClass::create(['name' => 'Grade '.$grade, 'section' => 'A', 'program' => 'English', 'session' => $session, 'class_group_id' => $group->id]);
                foreach (TeacherManagementService::DAYS as $day) \App\Models\ClassroomBooking::create(['class_group_id' => $group->id, 'class_session_id' => $classSession->id, 'classroom_id' => $room->id, 'day_of_week' => $day, 'starts_at' => $from, 'ends_at' => $to, 'starts_minute' => TeacherManagementService::minute($from), 'ends_minute' => TeacherManagementService::minute($to)]);
            }
        }
        $report = app(EnglishProgramSetup::class)->apply('2026-2027');
        $this->assertSame(600, $report['lessons']);
        $this->assertSame(600, \App\Models\Schedule::where('entry_type', 'academic')->count());
        $this->assertSame(0, \App\Models\Schedule::where('entry_type', 'relaxation')->count());
        $this->assertCount(14, $report['added_teachers']);
        $this->assertSame(24, User::where('role', 'teacher')->count());
        $rules = app(TeacherManagementService::class);
        foreach (User::where('role', 'teacher')->get() as $teacher) {
            $partTime = str_starts_with($teacher->profile_number ?? '', 'ENG-PT-');
            $this->assertFalse($teacher->requiresTeacherConfirmation());
            $this->assertNotNull($teacher->employee_confirmed_at);
            $this->assertCount(0, $rules->breaks($teacher));
            $this->assertFalse($rules->eligibleForBreaks($teacher));
            foreach (TeacherManagementService::DAYS as $day) {
                $this->assertSame($partTime ? 120 : 480, $rules->periods($teacher)->where('weekday', $day)->sum(fn ($p) => TeacherManagementService::duration($p->start_time, $p->end_time)));
            }
            $this->assertSame($partTime ? 600 : 2400, $rules->lessons($teacher)->sum(fn ($l) => TeacherManagementService::duration($l->starts_at, $l->ends_at)));
            foreach ($rules->lessons($teacher)->load('schoolClass')->groupBy(fn ($l) => $l->day.'|'.$l->schoolClass->session) as $sessionLessons) {
                $this->assertCount(1, $sessionLessons->pluck('school_class_id')->unique());
                $this->assertCount(1, $sessionLessons->pluck('classroom_id')->unique());
            }
            if (! $partTime) {
                $monday = $rules->lessons($teacher)->load('schoolClass')->where('day', 'Monday');
                $morning = $monday->first(fn ($l) => $l->schoolClass->session === 'Morning');
                $afternoon = $monday->first(fn ($l) => $l->schoolClass->session === 'Afternoon');
                $this->assertNotSame($morning->schoolClass->class_group_id, $afternoon->schoolClass->class_group_id);
                $this->assertNotSame($morning->classroom_id, $afternoon->classroom_id);
            }
        }
        foreach (SchoolClass::all() as $class) {
            $this->assertCount(6, $class->subjects);
            $this->assertSame($class->session === 'Evening' ? 10 : 20, $class->schedules()->count());
            foreach ($class->subjects as $subject) $this->assertSame((int) $subject->required_weekly_minutes, $subject->schedules()->get()->sum(fn ($l) => TeacherManagementService::duration($l->starts_at, $l->ends_at)));
        }
        $admin = User::factory()->create(['role' => 'administrator']);
        $this->actingAs($admin)->get(route('timetables.index', ['view' => 'teachers', 'program' => 'English', 'teacher' => User::where('role', 'teacher')->orderBy('id')->firstOrFail()->id]))->assertOk()->assertSee('Administrator verified')->assertSee('Full-time: 8 hours per day')->assertDontSee('No lesson scheduled');
        foreach (User::where('role', 'teacher')->where('program', 'English')->get() as $teacher) {
            $partTime = str_starts_with($teacher->profile_number ?? '', 'ENG-PT-');
            $this->get(route('timetables.index', ['view' => 'teachers', 'program' => 'English', 'teacher' => $teacher->id]))
                ->assertOk()->assertDontSee('No lesson scheduled')
                ->assertViewHas('periods', fn ($periods) => count($periods) === ($partTime ? 2 : 8));
            $data = $this->getJson(route('timetables.data', ['view' => 'teachers', 'program' => 'English', 'teacher' => $teacher->id]))->assertOk();
            $this->assertStringNotContainsString('No lesson scheduled', $data->json('html'));
        }
        // A genuinely missing lesson during working hours must still be shown as a gap.
        $lesson = \App\Models\Schedule::where('starts_at', '07:00')->firstOrFail();
        \App\Models\Schedule::whereKey($lesson->id)->update(['cancelled_at' => now()]);
        $this->get(route('timetables.index', ['view' => 'teachers', 'program' => 'English', 'teacher' => $lesson->teacher_id]))
            ->assertOk()->assertSee('No lesson scheduled')->assertViewHas('periods', fn ($periods) => count($periods) === 8);
    }
}
