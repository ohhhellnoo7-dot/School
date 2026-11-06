<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, ClassSession, Classroom, Schedule, SchoolClass, Subject, User};
use App\Services\{ClassroomBookingService, KhmerClassTimetableReport, RotationTimetableGenerator, TeacherManagementService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KhmerFullStudyTimetableTest extends TestCase
{
    use RefreshDatabase;

    private function cohort(): SchoolClass
    {
        $group = ClassGroup::create(['grade'=>7,'section'=>'A','name'=>'Grade 7','program'=>'Khmer','status'=>'active']);
        $session = ClassSession::create(['class_group_id'=>$group->id,'session'=>'Morning','starts_at'=>'07:00','ends_at'=>'11:00']);
        $class = SchoolClass::create(['class_group_id'=>$group->id,'name'=>'Grade 7','section'=>'A','program'=>'Khmer','session'=>'Morning','weekly_relaxation_minutes'=>180]);
        $room = Classroom::create(['room_number'=>'7A','name'=>'Room 7A','capacity'=>40,'status'=>'active']);
        $rules = app(TeacherManagementService::class);
        foreach ($rules::DAYS as $day) app(ClassroomBookingService::class)->create(['class_group_id'=>$group->id,'class_session_id'=>$session->id,'classroom_id'=>$room->id,'day_of_week'=>$day,'starts_at'=>'07:00','ends_at'=>'11:00']);
        foreach (['Mathematics'=>480,'Khmer Literature'=>480,'History'=>240] as $name=>$minutes) {
            $teacher = User::factory()->create(['role'=>'teacher','program'=>'Khmer','status'=>'active']);
            Subject::create(['school_class_id'=>$class->id,'teacher_id'=>$teacher->id,'name'=>$name,'code'=>'KH-'.$teacher->id,'required_weekly_minutes'=>$minutes,'lesson_minutes'=>$name==='History'?60:120]);
            $rules->saveProfile($teacher, [$name],1200,array_map(fn ($day)=>['weekday'=>$day,'start_time'=>'07:00','end_time'=>'11:00','unavailable'=>false],$rules::DAYS));
            $rules->saveBreaks($teacher,array_map(fn ($day)=>['weekday'=>$day,'start_time'=>$name === 'Khmer Literature' ? '07:00' : '10:00','end_time'=>$name === 'Khmer Literature' ? '08:00' : '11:00'],['Monday','Wednesday','Friday']));
        }
        return $class;
    }

    public function test_complete_draft_has_no_free_periods_and_publishes_shared_lessons_only_after_confirmation(): void
    {
        $class = $this->cohort();
        $generator = app(RotationTimetableGenerator::class);
        $plan = $generator->preview($class);
        $this->assertSame(0,Schedule::count());
        $this->assertSame(1200,collect($plan['rows'])->sum(fn ($r)=>TeacherManagementService::duration($r['starts_at'],$r['ends_at'])));
        $this->assertSame(['academic'],collect($plan['rows'])->pluck('entry_type')->unique()->values()->all());
        $generator->confirm($plan);
        $report = app(KhmerClassTimetableReport::class)->report();
        $cohort = $report['identities'][0]['cohorts'][0];
        $this->assertSame('valid',$cohort['status'],json_encode($cohort['issues']));
        $this->assertSame(12,Schedule::count());
        foreach (User::where('role','teacher')->get() as $teacher) $this->assertCount(3,app(TeacherManagementService::class)->breaks($teacher));
    }

    public function test_curriculum_shortfall_is_rejected_without_filling_or_publishing(): void
    {
        $class = $this->cohort();
        $class->subjects()->where('name','History')->update(['required_weekly_minutes'=>180]);
        try { app(RotationTimetableGenerator::class)->preview($class); $this->fail('Incomplete curriculum accepted'); }
        catch (ValidationException $e) { $this->assertStringContainsString('INCOMPLETE curriculum',$e->getMessage()); }
        $this->assertSame(0,Schedule::count());
    }

    public function test_confirmation_rejects_a_tampered_plan_with_a_missing_day(): void
    {
        $class = $this->cohort();
        $generator = app(RotationTimetableGenerator::class);
        $plan = $generator->preview($class);
        $plan['rows'][0]['day'] = 'Saturday';
        try { $generator->confirm($plan); $this->fail('Incomplete plan published'); }
        catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        $this->assertSame(0,Schedule::count());
    }

    public function test_staffing_capacity_respects_teacher_free_periods(): void
    {
        $this->cohort();
        $teacher = User::where('role', 'teacher')->firstOrFail();
        User::where('role', 'teacher')->where('id', '!=', $teacher->id)->update(['status' => 'inactive']);
        $capacity = app(\App\Services\KhmerTeachingCapacity::class)->report();
        $this->assertEquals(20, $capacity['required_hours']);
        $this->assertEquals(17, $capacity['available_hours']);
        $this->assertEquals(3, $capacity['shortage_hours']);
    }

    public function test_reserved_khmer_room_without_a_lesson_is_incomplete_not_planned_free_time(): void
    {
        $this->cohort();
        $booking = \App\Models\ClassroomBooking::with('classGroup')->firstOrFail();
        $activity = app(\App\Services\RoomLessonService::class)->activity(Classroom::firstOrFail(), collect(), 8 * 60, $booking);
        $this->assertSame('Occupied', $activity['occupancy']);
        $this->assertSame('Incomplete timetable', $activity['activity']);
        $this->assertSame(0, Schedule::count());
    }

    public function test_zero_teacher_free_period_policy_does_not_generate_breaks(): void
    {
        $class = $this->cohort();
        \Illuminate\Support\Facades\DB::table('teacher_management_settings')->where('id', 1)->update(['khmer_teacher_free_periods' => 0]);
        $rules = app(TeacherManagementService::class);
        foreach (User::where('role', 'teacher')->get() as $teacher) {
            $rules->saveBreaks($teacher, [], true);
            $this->assertSame(0, $rules->requiredBreaks($teacher));
            $this->assertSame([], $rules->proposeBreaks($teacher));
            $this->assertFalse($rules->eligibleForBreaks($teacher));
        }
        $generator = app(RotationTimetableGenerator::class);
        $plan = $generator->preview($class);
        $generator->confirm($plan);
        $report = app(KhmerClassTimetableReport::class)->report();
        $this->assertSame('valid', $report['identities'][0]['cohorts'][0]['status']);
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('teacher_break_plans')->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('teacher_breaks')->count());
    }
}
