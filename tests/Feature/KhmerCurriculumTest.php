<?php
namespace Tests\Feature;

use App\Models\{ClassGroup, CurriculumProfile, SchoolClass, Subject, User};
use App\Services\KhmerCurriculumService;
use Database\Seeders\KhmerCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KhmerCurriculumTest extends TestCase
{
    use RefreshDatabase;
    public function test_profiles_have_the_requested_counts_and_twenty_hour_allocations(): void
    {
        $this->seed(KhmerCurriculumSeeder::class);
        foreach ([[7,'',10],[8,'',11],[9,'',10],[10,'',12],[11,'science',8],[11,'social_science',8],[12,'science',7],[12,'social_science',7]] as [$grade,$stream,$count]) {
            $profile=CurriculumProfile::where('grade',$grade)->where('stream',$stream)->sole();
            $this->assertCount($count,$profile->requirements);
            $this->assertSame(1200,$profile->requirements->sum('weekly_minutes'));
            $this->assertSame('provisional',$profile->status);
            foreach ($profile->requirements as $r) $this->assertSame(in_array($r->name,['Khmer Literature','Mathematics'],true)?120:60,$r->lesson_minutes);
        }
    }
    private function cohort(int $grade=7,string $stream=''): SchoolClass
    {
        $group=ClassGroup::create(['name'=>'Grade '.$grade,'grade'=>$grade,'section'=>'A','program'=>'Khmer','capacity'=>40,'status'=>'active','stream'=>$stream?:null]);
        return SchoolClass::create(['name'=>'Grade '.$grade,'section'=>'A','program'=>'Khmer','session'=>'Morning','class_group_id'=>$group->id]);
    }
    public function test_sync_reuses_historical_subject_ids_and_creates_only_missing_requirements(): void
    {
        $this->seed(KhmerCurriculumSeeder::class);
        $class=$this->cohort();
        $old=Subject::create(['name'=>'Khmer','code'=>'LEGACY','school_class_id'=>$class->id,'lesson_minutes'=>120]);
        $legacy=Subject::create(['name'=>'Art','code'=>'ART','school_class_id'=>$class->id,'lesson_minutes'=>60]);
        $service=app(KhmerCurriculumService::class);
        $service->syncAll(); $service->syncAll();
        $this->assertSame('Khmer Literature',$old->fresh()->name);
        $this->assertNotNull($old->fresh()->curriculum_requirement_id);
        $this->assertSame(10,$class->subjects()->whereNotNull('curriculum_requirement_id')->count());
        $this->assertSame(0,$legacy->fresh()->required_weekly_minutes);
        $this->assertDatabaseCount('subjects',11);
        $this->assertContains('Civics: requires 1 hours; 0 active qualified teachers offer at most 0 hours before competing subject demands.',$service->readiness()['blockers']);
    }
    public function test_admin_can_edit_hours_and_invalid_or_unauthorized_updates_do_not_mutate_data(): void
    {
        $this->seed(KhmerCurriculumSeeder::class);
        $this->cohort(); app(KhmerCurriculumService::class)->syncAll();
        $admin=User::factory()->create(['role'=>'administrator']); $this->actingAs($admin);
        $profile=CurriculumProfile::where('grade',7)->sole();
        $rows=$profile->requirements->map(fn ($r)=>['name'=>$r->name,'weekly_hours'=>$r->weekly_minutes/60])->all();
        $bad=$rows; $bad[0]['weekly_hours']=3;
        $this->put(route('khmer-curriculum.update',$profile),['subjects'=>$bad])->assertSessionHasErrors('subjects');
        $rows[2]['weekly_hours']=2; $rows[3]['weekly_hours']=2;
        $this->put(route('khmer-curriculum.update',$profile),['subjects'=>$rows])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('curriculum_requirements',['curriculum_profile_id'=>$profile->id,'name'=>'English','weekly_minutes'=>120]);
        $this->get(route('khmer-curriculum.index'))->assertOk()->assertSee('Provisional Scholar configuration')->assertSee('10 subjects');
        $this->actingAs(User::factory()->create(['role'=>'student']))->put(route('khmer-curriculum.update',$profile),['subjects'=>$rows])->assertForbidden();
    }
    public function test_existing_senior_class_can_change_stream_without_creating_another_identity(): void
    {
        $this->seed(KhmerCurriculumSeeder::class);
        $class=$this->cohort(11,'social_science'); $group=$class->classGroup;
        $this->actingAs(User::factory()->create(['role'=>'administrator']));
        $this->put(route('class-groups.update',$group),['name'=>$group->name,'grade'=>11,'section'=>'A','program'=>'Khmer','stream'=>'science','capacity'=>40,'status'=>'active'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('science',$group->fresh()->stream);
        $this->assertSame(1,ClassGroup::where('grade',11)->count());
        $this->assertSame(8,$class->subjects()->whereNotNull('curriculum_requirement_id')->count());
        $this->assertNotNull($class->subjects()->where('name','Physics')->first());
        $this->assertNull($class->subjects()->where('name','Civics')->whereNotNull('curriculum_requirement_id')->first());
    }
    public function test_generation_is_blocked_when_staffing_cannot_cover_the_curriculum(): void
    {
        $this->seed(KhmerCurriculumSeeder::class); $this->cohort(); app(KhmerCurriculumService::class)->syncAll();
        $this->actingAs(User::factory()->create(['role'=>'administrator']));
        $this->post(route('khmer-curriculum.generate'),['academic_year'=>'2026-2027'])->assertSessionHasErrors('generation');
        $this->assertDatabaseCount('schedules',0);
    }
    public function test_teacher_enrollment_automatically_assigns_qualified_curriculum_subjects(): void
    {
        $this->seed(KhmerCurriculumSeeder::class); $class=$this->cohort(); app(KhmerCurriculumService::class)->syncAll();
        $this->actingAs(User::factory()->create(['role'=>'administrator']));
        $this->post(route('teacher-management.store'),['name'=>'Civics Recruit','email'=>'recruit@school.test','gender'=>'other','program'=>'Khmer','subject1'=>'Civics','weekly_hours'=>20,'working_sessions'=>'Both','status'=>'active'])->assertSessionHasNoErrors()->assertRedirect();
        $teacher=User::where('email','recruit@school.test')->sole();
        $this->assertDatabaseHas('subjects',['school_class_id'=>$class->id,'name'=>'Civics','teacher_id'=>$teacher->id]);
        $this->assertDatabaseHas('teacher_qualifications',['teacher_id'=>$teacher->id,'subject_name'=>'Civics']);
    }

}
