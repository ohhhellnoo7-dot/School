<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TeacherManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeacherWeeklyWorkPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_forty_hour_work_plan_keeps_classroom_teaching_capped_at_twenty(): void
    {
        $service=app(TeacherManagementService::class);
        $teacher=User::factory()->create(['role'=>'teacher','program'=>'Khmer']);
        $this->assertEquals(['teaching'=>20,'preparation'=>10,'marking'=>5,'other_duties'=>5,'total'=>40],$service->weeklyWorkPlan());
        $this->assertSame(1200,$service->limit($teacher));
        DB::table('teacher_profiles')->insert(['teacher_id'=>$teacher->id,'weekly_minutes'=>600]);
        $this->assertSame(600,$service->limit($teacher));
        $admin=User::factory()->create(['role'=>'administrator']);
        $this->actingAs($admin)->get(route('teacher-management.reports'))->assertOk()->assertSee('40 hours per week')->assertSee('Lesson preparation');
        $this->get(route('teacher-management.show',$teacher))->assertOk()->assertSee('30 hours per week')->assertSee('planning allowances');
    }

    public function test_non_teaching_hours_are_saved_without_adding_teaching_capacity_or_lessons(): void
    {
        $admin=User::factory()->create(['role'=>'administrator']);
        $teacher=User::factory()->create(['role'=>'teacher','program'=>'Khmer']);
        $before=DB::table('schedules')->count();
        $this->actingAs($admin)->post(route('teacher-management.settings'),['target'=>30,'weekly_hours'=>20,'preparation_hours'=>11,'marking_hours'=>4,'other_duties_hours'=>5])->assertSessionHasNoErrors();
        $service=app(TeacherManagementService::class);
        $this->assertEquals(40,$service->weeklyWorkPlan()['total']);
        $this->assertSame(660,(int)$service->settings()->preparation_minutes);
        $this->assertSame(240,(int)$service->settings()->marking_minutes);
        $this->assertSame(1200,$service->limit($teacher));
        $this->assertSame($before,DB::table('schedules')->count());
        $this->post(route('teacher-management.settings'),['target'=>30,'weekly_hours'=>20,'preparation_hours'=>-1])->assertSessionHasErrors('preparation_hours');
        $this->actingAs(User::factory()->create(['role'=>'teacher']))->post(route('teacher-management.settings'),['target'=>30,'weekly_hours'=>20])->assertForbidden();
    }

    public function test_khmer_forty_hour_teaching_limit_is_separate_from_other_programs_and_additional_work(): void
    {
        $admin=User::factory()->create(['role'=>'administrator']);
        $khmer=User::factory()->create(['role'=>'teacher','program'=>'Khmer']);
        $english=User::factory()->create(['role'=>'teacher','program'=>'English']);
        $this->actingAs($admin)->post(route('teacher-management.settings'),['target'=>30,'weekly_hours'=>20,'khmer_weekly_hours'=>40])->assertSessionHasNoErrors();
        $service=app(TeacherManagementService::class);
        $this->assertSame(2400,$service->limit($khmer));
        $this->assertSame(1200,$service->limit($english));
        $this->assertEquals(40,$service->weeklyWorkPlan($khmer)['teaching']);
        $this->assertEquals(60,$service->weeklyWorkPlan($khmer)['total']);
        $this->get(route('teacher-management.show',$khmer))->assertOk()->assertSee('60 hours per week')->assertSee('8 classroom hours per day');
        $this->get(route('teacher-management.reports'))->assertOk()->assertSee('Khmer teacher work plan')->assertSee('60 hours per week');
        DB::table('teacher_profiles')->insert(['teacher_id'=>$khmer->id,'weekly_minutes'=>1200]);
        $this->assertSame(1200,$service->limit($khmer));
    }
}
