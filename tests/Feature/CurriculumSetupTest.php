<?php

namespace Tests\Feature;

use App\Models\{SchoolClass, Subject, User};
use App\Services\CurriculumSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurriculumSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_curriculum_fits_weekly_session_and_whole_lesson_blocks_and_is_idempotent(): void
    {
        $class = SchoolClass::create(['name'=>'Grade 10','section'=>'A','program'=>'Khmer','session'=>'Morning','weekly_relaxation_minutes'=>180]);
        foreach (['Mathematics','Khmer','History','Geography'] as $index=>$name) Subject::create(['school_class_id'=>$class->id,'name'=>$name,'code'=>'CUR-'.$index,'lesson_minutes'=>60]);
        $service = app(CurriculumSetupService::class);
        $result = $service->configure($class);
        $this->assertSame('balanced_school_defaults',$result['source']);
        $subjects = $class->subjects()->get();
        $this->assertSame(1020,(int)$subjects->sum('required_weekly_minutes'));
        foreach ($subjects as $subject) $this->assertSame(0,$subject->required_weekly_minutes % \App\Support\KhmerLessonDuration::minutes($subject));
        $this->assertSame(0,$service->configure($class)['configured']);
    }

    public function test_full_existing_study_week_is_preserved_as_the_curriculum(): void
    {
        $class = SchoolClass::create(['name'=>'Grade 10','section'=>'A','program'=>'Khmer','session'=>'Morning','weekly_relaxation_minutes'=>180]);
        $subject = Subject::create(['school_class_id'=>$class->id,'name'=>'Mathematics','code'=>'FULL']);
        foreach (\App\Services\TeacherManagementService::DAYS as $day) foreach ([['07:00','09:00'],['09:00','11:00']] as [$start,$end]) DB::table('schedules')->insert(['school_class_id'=>$class->id,'subject_id'=>$subject->id,'entry_type'=>'academic','day'=>$day,'starts_at'=>$start,'ends_at'=>$end,'room'=>'10A']);
        $result = app(CurriculumSetupService::class)->configure($class);
        $this->assertSame('existing_complete_timetable',$result['source']);
        $this->assertSame(1200,$subject->fresh()->required_weekly_minutes);
        $this->assertSame(0,$class->fresh()->weekly_relaxation_minutes);
        $this->assertSame(10,DB::table('schedules')->count());
    }

    public function test_configured_hours_are_preserved_and_missing_class_subjects_use_existing_program_catalog(): void
    {
        $class = SchoolClass::create(['name'=>'Grade 1','section'=>'A','program'=>'English','session'=>'Morning','weekly_relaxation_minutes'=>0]);
        $subject = Subject::create(['school_class_id'=>$class->id,'name'=>'Reading','code'=>'KEEP','required_weekly_minutes'=>180,'lesson_minutes'=>60]);
        $service = app(CurriculumSetupService::class);
        $service->configure($class);
        $this->assertSame(180,$subject->fresh()->required_weekly_minutes);
        $other = SchoolClass::create(['name'=>'Grade 1','section'=>'B','program'=>'English','session'=>'Morning','weekly_relaxation_minutes'=>0]);
        $result = $service->configure($other);
        $this->assertCount(1,$result['created_subjects']);
        $this->assertSame(1200,(int)$other->subjects()->sum('required_weekly_minutes'));
        $this->assertNull($other->subjects()->first()->teacher_id);
    }

    public function test_configured_class_displays_actual_curriculum_shortages_instead_of_unknown_settings(): void
    {
        $admin = User::factory()->create(['role'=>'administrator']);
        $class = SchoolClass::create(['name'=>'Grade 10','section'=>'A','program'=>'Khmer','session'=>'Morning','weekly_relaxation_minutes'=>180]);
        foreach (['Mathematics','History'] as $index=>$name) Subject::create(['school_class_id'=>$class->id,'name'=>$name,'code'=>'UI-'.$index,'lesson_minutes'=>60]);
        app(CurriculumSetupService::class)->configure($class);
        $this->actingAs($admin)->get(route('timetables.index',['class'=>$class->id]))->assertOk()
            ->assertDontSee('Weekly curriculum hours are not configured')
            ->assertSee('Incomplete timetable: required curriculum hours are missing for')
            ->assertSee('Mathematics')->assertSee('History');
    }

    public function test_evening_settings_respect_the_actual_session_capacity(): void
    {
        $admin = User::factory()->create(['role'=>'administrator']);
        $group = \App\Models\ClassGroup::create(['name'=>'English 1','grade'=>1,'program'=>'English','status'=>'active']);
        \App\Models\ClassSession::create(['class_group_id'=>$group->id,'session'=>'Evening','starts_at'=>'17:30','ends_at'=>'18:30']);
        $class = SchoolClass::create(['name'=>'Grade 1','section'=>'A','program'=>'English','session'=>'Evening','class_group_id'=>$group->id,'weekly_relaxation_minutes'=>0]);
        $subject = Subject::create(['school_class_id'=>$class->id,'name'=>'Reading','code'=>'EVENING','lesson_minutes'=>60]);
        $result = app(CurriculumSetupService::class)->configure($class);
        $this->assertSame(300,$result['targets'][$subject->id]);
        $this->actingAs($admin)->post(route('classes.timetable.settings',$class),[
            'subjects'=>[['id'=>$subject->id,'weekly_hours'=>6,'lesson_minutes'=>60]],'relaxation_hours'=>0,'confirm'=>1,
        ])->assertSessionHasErrors();
        $this->assertSame(300,$subject->fresh()->required_weekly_minutes);
    }

    public function test_admin_can_initialize_all_classes_and_students_cannot_change_curriculum(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $admin = User::factory()->create(['role'=>'administrator']);
        foreach (['A','B'] as $section) {
            $class = SchoolClass::create(['name'=>'Grade 10','section'=>$section,'program'=>'Khmer','session'=>'Morning','weekly_relaxation_minutes'=>180]);
            foreach (['Mathematics','History'] as $index=>$name) Subject::create(['school_class_id'=>$class->id,'name'=>$name,'code'=>$section.$index,'lesson_minutes'=>60]);
        }
        $this->actingAs($admin)->post(route('timetables.curriculum.setup'),['use_school_defaults'=>1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0,Subject::whereNull('required_weekly_minutes')->count());
        $this->actingAs(User::factory()->create(['role'=>'student']))->post(route('timetables.curriculum.setup'),['use_school_defaults'=>1])->assertForbidden();
    }
}
