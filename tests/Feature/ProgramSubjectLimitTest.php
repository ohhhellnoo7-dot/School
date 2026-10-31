<?php

namespace Tests\Feature;

use App\Models\{SchoolClass, Subject, User};
use App\Services\TeacherManagementService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProgramSubjectLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_and_chinese_profiles_and_assignments_can_have_more_than_two_subjects(): void
    {
        foreach (['English','Chinese'] as $program) {
            $teacher = User::factory()->create(['role'=>'teacher','program'=>$program]);
            $class = SchoolClass::create(['name'=>'Grade 1','section'=>'A','program'=>$program,'session'=>'Morning']);
            $subjects = collect(['Reading','Writing','Language'])->map(fn ($name) => Subject::create(['school_class_id'=>$class->id,'name'=>$program.' '.$name,'code'=>$program.'-'.$name]));
            app(TeacherManagementService::class)->saveProfile($teacher,$subjects->pluck('name')->all(),1200,[['weekday'=>'Monday','start_time'=>'07:00','end_time'=>'11:00','unavailable'=>false]]);
            foreach ($subjects as $subject) $subject->update(['teacher_id'=>$teacher->id]);
            $this->assertCount(3,app(TeacherManagementService::class)->qualifications($teacher));
            $this->assertCount(3,app(TeacherManagementService::class)->workload($teacher)['subjects']);
        }
    }

    public function test_khmer_profile_still_rejects_a_third_subject(): void
    {
        $teacher = User::factory()->create(['role'=>'teacher','program'=>'Khmer']);
        $this->expectException(ValidationException::class);
        app(TeacherManagementService::class)->saveProfile($teacher,['Math','History','Geography'],1200,[]);
    }

    public function test_database_still_rejects_a_third_khmer_qualification_slot(): void
    {
        $teacher = User::factory()->create(['role'=>'teacher','program'=>'Khmer']);
        $this->expectException(QueryException::class);
        DB::table('teacher_qualifications')->insert(['teacher_id'=>$teacher->id,'subject_name'=>'History','slot'=>3]);
    }

    public function test_teacher_edit_retains_additional_qualifications(): void
    {
        $admin = User::factory()->create(['role'=>'administrator']);
        $teacher = User::factory()->create(['role'=>'teacher','program'=>'English']);
        $class = SchoolClass::create(['name'=>'Grade 1','section'=>'A','program'=>'English','session'=>'Morning']);
        foreach (['Reading','Writing','Language'] as $name) Subject::create(['school_class_id'=>$class->id,'name'=>$name,'code'=>$name]);
        $this->actingAs($admin)->put(route('teacher-management.update',$teacher),[
            'name'=>$teacher->name,'gender'=>'female','email'=>$teacher->email,'program'=>'English','status'=>'active',
            'subject1'=>'Reading','subject2'=>'Writing','additional_subjects'=>['Language'],'weekly_hours'=>20,'working_sessions'=>'Morning',
        ])->assertRedirect(route('teacher-management.show',$teacher));
        $this->assertSame(['Reading','Writing','Language'],app(TeacherManagementService::class)->qualifications($teacher)->all());
    }
}
