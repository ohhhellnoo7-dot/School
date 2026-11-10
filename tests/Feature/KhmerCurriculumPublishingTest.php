<?php
namespace Tests\Feature;
use App\Models\{ClassGroup,ClassSession,Classroom,ClassroomBooking,CurriculumProfile,SchoolClass,Schedule,User};
use App\Services\{KhmerCurriculumService,KhmerCurriculumTimetablePublisher,RotationTimetableGenerator,TeacherManagementService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class KhmerCurriculumPublishingTest extends TestCase
{
    use RefreshDatabase;
    private function draft(): array
    {
        DB::table('teacher_management_settings')->where('id',1)->update(['khmer_teacher_free_periods'=>0]);
        $profile=CurriculumProfile::create(['program'=>'Khmer','grade'=>7,'stream'=>'','status'=>'provisional']);
        foreach(['Khmer Literature','Mathematics'] as $i=>$name) $profile->requirements()->create(['name'=>$name,'weekly_minutes'=>600,'lesson_minutes'=>120,'position'=>$i]);
        $group=ClassGroup::create(['name'=>'Grade 7','grade'=>7,'section'=>'A','program'=>'Khmer','status'=>'active','capacity'=>40]);
        $session=ClassSession::create(['class_group_id'=>$group->id,'session'=>'Morning','starts_at'=>'07:00','ends_at'=>'11:00']);
        $class=SchoolClass::create(['name'=>'Grade 7','section'=>'A','program'=>'Khmer','session'=>'Morning','class_group_id'=>$group->id]);
        $room=Classroom::create(['room_number'=>'TEST7','name'=>'Room TEST7','building'=>'A','capacity'=>40,'status'=>'active']);
        app(KhmerCurriculumService::class)->configure($class);
        $teachers=[];
        foreach(['Khmer Literature','Mathematics'] as $name) {
            $teacher=User::factory()->create(['role'=>'teacher','program'=>'Khmer','status'=>'active']);
            app(TeacherManagementService::class)->saveProfile($teacher,[$name],600,collect(TeacherManagementService::DAYS)->map(fn($d)=>['weekday'=>$d,'start_time'=>'07:00','end_time'=>'11:00','unavailable'=>false])->all());
            $teachers[$name]=$teacher;
        }
        $rows=[];
        foreach(TeacherManagementService::DAYS as $day) {
            ClassroomBooking::create(['class_group_id'=>$group->id,'class_session_id'=>$session->id,'classroom_id'=>$room->id,'day_of_week'=>$day,'starts_at'=>'07:00','ends_at'=>'11:00']);
            foreach(['Khmer Literature'=>['07:00','09:00'],'Mathematics'=>['09:00','11:00']] as $name=>[$start,$end]) $rows[]=['school_class_id'=>$class->id,'subject_id'=>$class->subjects()->where('name',$name)->sole()->id,'teacher_id'=>$teachers[$name]->id,'classroom_id'=>$room->id,'room'=>'TEST7','day'=>$day,'starts_at'=>$start,'ends_at'=>$end,'entry_type'=>'academic','academic_year'=>'2026-2027'];
        }
        $old=new Schedule($rows[0]); $old->saveQuietly();
        return ['status'=>'FEASIBLE','academic_year'=>'2026-2027','fingerprint'=>app(RotationTimetableGenerator::class)->fingerprint(),'rows'=>$rows];
    }
    public function test_complete_conflict_free_plan_is_published_and_old_lessons_are_retained(): void
    {
        $plan=$this->draft(); $old=Schedule::sole();
        $result=app(KhmerCurriculumTimetablePublisher::class)->publish($plan);
        $this->assertSame(10,$result['lesson_count']); $this->assertSame(0,$result['student_free_periods']);
        $this->assertSame('provisional',$result['curriculum_status']);
        $this->assertSame(10,Schedule::count());
        $this->assertNotNull(Schedule::withoutGlobalScope('active')->findOrFail($old->id)->cancelled_at);
        $this->assertSame(11,Schedule::withoutGlobalScope('active')->count());
    }
    public function test_overlapping_plan_is_rejected_without_replacing_live_lessons(): void
    {
        $plan=$this->draft(); $plan['rows'][1]['starts_at']='07:00'; $plan['rows'][1]['ends_at']='09:00';
        try { app(KhmerCurriculumTimetablePublisher::class)->publish($plan); $this->fail('Expected conflict rejection.'); }
        catch(\RuntimeException $e) { $this->assertStringContainsString('Overlapping',$e->getMessage()); }
        $this->assertSame(1,Schedule::count()); $this->assertSame(1,Schedule::withoutGlobalScope('active')->count());
    }
    public function test_changed_school_data_rejects_a_stale_plan(): void
    {
        $plan=$this->draft(); DB::table('curriculum_profiles')->update(['notes'=>'Changed configuration']);
        $this->expectException(\RuntimeException::class);
        app(KhmerCurriculumTimetablePublisher::class)->publish($plan);
    }
    public function test_database_driven_solver_preview_does_not_change_live_lessons(): void
    {
        if (!is_dir(base_path('.tmp/khmer-solver/ortools'))) $this->markTestSkipped('Install the documented curriculum solver dependency.');
        $this->draft();
        $before=Schedule::orderBy('id')->get()->toJson();
        $plan=app(\App\Services\KhmerCurriculumPlanner::class)->preview('2026-2027');
        $this->assertContains($plan['status'],['FEASIBLE','OPTIMAL']);
        $this->assertCount(10,$plan['rows']);
        $this->assertSame('provisional',$plan['curriculum_status']);
        $this->assertSame($before,Schedule::orderBy('id')->get()->toJson());
    }

    public function test_new_teacher_receives_a_safe_existing_subject_without_moving_lesson_times(): void
    {
        $plan=$this->draft(); app(KhmerCurriculumTimetablePublisher::class)->publish($plan);
        $math=\App\Models\Subject::where('name','Mathematics')->sole(); $donor=User::findOrFail($math->teacher_id);
        $rules=app(TeacherManagementService::class);
        $periods=collect(TeacherManagementService::DAYS)->map(fn($d)=>['weekday'=>$d,'start_time'=>'07:00','end_time'=>'11:00','unavailable'=>false])->all();
        $rules->saveProfile($donor,['Mathematics'],1200,$periods);
        $group=ClassGroup::create(['name'=>'Grade 8','grade'=>8,'section'=>'A','program'=>'Khmer','status'=>'active','capacity'=>40]);
        ClassSession::create(['class_group_id'=>$group->id,'session'=>'Morning','starts_at'=>'07:00','ends_at'=>'11:00']);
        $class=SchoolClass::create(['name'=>'Grade 8','section'=>'A','program'=>'Khmer','session'=>'Morning','class_group_id'=>$group->id]);
        $room=Classroom::create(['room_number'=>'TEST8','name'=>'Room TEST8','building'=>'A','capacity'=>40,'status'=>'active']);
        $other=\App\Models\Subject::create(['name'=>'Mathematics','code'=>'OTHER-MATH','school_class_id'=>$class->id,'teacher_id'=>$donor->id,'required_weekly_minutes'=>120,'lesson_minutes'=>120]);
        (new Schedule(['school_class_id'=>$class->id,'subject_id'=>$other->id,'teacher_id'=>$donor->id,'classroom_id'=>$room->id,'room'=>'TEST8','day'=>'Monday','starts_at'=>'07:00','ends_at'=>'09:00','entry_type'=>'academic','academic_year'=>'2026-2027']))->saveQuietly();
        $unavailable=User::factory()->create(['role'=>'teacher','program'=>'Khmer','status'=>'active']);
        $early=collect(TeacherManagementService::DAYS)->map(fn($d)=>['weekday'=>$d,'start_time'=>'07:00','end_time'=>'09:00','unavailable'=>false])->all();
        $rules->saveProfile($unavailable,['Mathematics'],600,$early);
        $this->assertSame([],app(\App\Services\KhmerTeacherEnrollmentAssignment::class)->assign($unavailable));
        $this->assertSame($donor->id,$math->fresh()->teacher_id);
        $new=User::factory()->create(['role'=>'teacher','program'=>'Khmer','status'=>'active']);
        $rules->saveProfile($new,['Mathematics'],600,$periods);
        $before=Schedule::where('subject_id',$math->id)->get()->map(fn($s)=>$s->only(['id','day','starts_at','ends_at','classroom_id']))->all();
        $assigned=app(\App\Services\KhmerTeacherEnrollmentAssignment::class)->assign($new);
        $this->assertSame([$math->id],$assigned);
        $this->assertSame($new->id,$math->fresh()->teacher_id);
        $this->assertSame(5,Schedule::where('teacher_id',$new->id)->count());
        $this->assertSame(1,Schedule::where('teacher_id',$donor->id)->count());
        $this->assertSame($before,Schedule::where('subject_id',$math->id)->get()->map(fn($s)=>$s->only(['id','day','starts_at','ends_at','classroom_id']))->all());
    }

}
