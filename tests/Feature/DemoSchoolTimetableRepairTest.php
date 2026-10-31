<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, ClassSession, Classroom, SchoolClass, Schedule, Student, Subject, User};
use App\Services\{ClassroomBookingService, DemoSchoolTimetableRepair, LessonManagementService, TeacherManagementService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DemoSchoolTimetableRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_repair_completes_one_shared_week_without_duplicating_teachers_rooms_or_enrollments(): void
    {
        Storage::fake('local');
        $group=ClassGroup::create(['name'=>'Grade 1','grade'=>1,'program'=>'English','status'=>'active']);
        $session=ClassSession::create(['class_group_id'=>$group->id,'session'=>'Morning','starts_at'=>'07:00','ends_at'=>'11:00']);
        $room=Classroom::create(['room_number'=>'E01','name'=>'English 1','capacity'=>40,'status'=>'active']);
        foreach(TeacherManagementService::DAYS as $day) app(ClassroomBookingService::class)->create(['class_group_id'=>$group->id,'class_session_id'=>$session->id,'classroom_id'=>$room->id,'day_of_week'=>$day,'starts_at'=>'07:00','ends_at'=>'11:00']);
        $owner=SchoolClass::create(['name'=>'Grade 1','section'=>'A','program'=>'English','session'=>'Morning','class_group_id'=>$group->id,'weekly_relaxation_minutes'=>180]);
        $alias=SchoolClass::create(['name'=>'Grade 1','section'=>'B','program'=>'English','session'=>'Morning','class_group_id'=>$group->id,'weekly_relaxation_minutes'=>180]);
        foreach([$owner,$alias] as $class) Subject::create(['school_class_id'=>$class->id,'name'=>'English writing','code'=>'WR-'.$class->id,'required_weekly_minutes'=>1020,'lesson_minutes'=>60]);
        $studentUser=User::factory()->create(['role'=>'student']);
        $student=Student::factory()->create(['school_class_id'=>$alias->id,'user_id'=>$studentUser->id]);
        $report=app(DemoSchoolTimetableRepair::class)->apply('2026-2027');
        $this->assertCount(1,$report['new_sample_teachers']);
        $this->assertSame($owner->id,$alias->fresh()->timetableOwnerId());
        $this->assertSame($alias->id,$student->fresh()->school_class_id);
        $this->assertSame(20,Schedule::count());
        $this->assertSame(17,Schedule::where('entry_type','academic')->count());
        $this->assertSame(3,Schedule::where('entry_type','relaxation')->count());
        foreach(Schedule::all() as $lesson) app(LessonManagementService::class)->validate($lesson);
        $teacher=User::findOrFail($report['new_sample_teachers'][0]['id']);
        $this->assertStringStartsWith('Sample ',$teacher->name);
        $this->assertCount(3,app(TeacherManagementService::class)->breaks($teacher));
        $admin=User::factory()->create(['role'=>'administrator']);
        $this->actingAs($admin)->get(route('timetables.index',['class'=>$alias->id]))->assertOk()->assertSee('English writing')->assertDontSee('Incomplete timetable');
        $this->actingAs($studentUser)->get(route('timetables.index',['class'=>$alias->id]))->assertOk()->assertSee($teacher->name)->assertDontSee('Incomplete timetable');
        $this->get(route('classrooms.show',$room))->assertOk()->assertSee('English writing');
        $this->getJson(route('classrooms.lesson-status',['classroom'=>$room,'date'=>'2026-11-02','time'=>'08:00']))->assertOk()->assertSee($teacher->name);
        $this->get(route('classes.timetable',$alias))->assertOk()->assertSee('English writing')->assertSee($teacher->name);
        $this->assertSame(20,\App\Support\SchoolModules::query('schedules',$studentUser)->count());
        \Illuminate\Support\Facades\DB::table('subjects')->whereIn('school_class_id',[$owner->id,$alias->id])->update(['teacher_id'=>null]);
        $this->actingAs($teacher)->get(route('classrooms.show',$room))->assertOk()->assertSee('English writing');
        $this->get(route('classes.timetable',$owner))->assertOk()->assertSee('English writing');
    }

    public function test_shared_timetables_cannot_cross_programs_groups_or_sessions(): void
    {
        $owner=SchoolClass::create(['name'=>'Grade 1','section'=>'A','program'=>'English','session'=>'Morning']);
        $alias=new SchoolClass(['name'=>'Grade 1','section'=>'B','program'=>'Chinese','session'=>'Evening','shared_timetable_class_id'=>$owner->id]);
        $this->expectException(ValidationException::class);
        $alias->save();
    }
}
