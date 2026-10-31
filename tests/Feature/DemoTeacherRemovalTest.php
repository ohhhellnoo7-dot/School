<?php

namespace Tests\Feature;

use App\Models\{ClassGroup,ClassSession,Classroom,Schedule,SchoolClass,Subject,User};
use App\Services\{ClassroomBookingService,DemoTeacherRemovalService,LessonManagementService,TeacherManagementService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoTeacherRemovalTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $qualifiedOriginal=false): array
    {
        Storage::fake('local');
        $original=User::factory()->create(['role'=>'teacher','program'=>'Khmer','name'=>'Sample Original Khmer','status'=>'active']);
        $demo=User::factory()->create(['role'=>'teacher','program'=>'Khmer','name'=>'Sample Extra Demo','profile_number'=>'DEMO-TEST','email'=>'demo-timetable-test@example.test','status'=>'active']);
        $group=ClassGroup::create(['name'=>'Grade 10','grade'=>10,'section'=>'A','program'=>'Khmer','status'=>'active']);
        $session=ClassSession::create(['class_group_id'=>$group->id,'session'=>'Morning','starts_at'=>'07:00','ends_at'=>'11:00']);
        $room=Classroom::create(['room_number'=>'10A','name'=>'Room 10A','capacity'=>40,'status'=>'active']);
        app(ClassroomBookingService::class)->create(['class_group_id'=>$group->id,'class_session_id'=>$session->id,'classroom_id'=>$room->id,'day_of_week'=>'Monday','starts_at'=>'07:00','ends_at'=>'11:00']);
        $class=SchoolClass::create(['name'=>'Grade 10','section'=>'A','program'=>'Khmer','session'=>'Morning','class_group_id'=>$group->id]);
        $subject=Subject::create(['school_class_id'=>$class->id,'name'=>'History','code'=>'H10','required_weekly_minutes'=>60,'lesson_minutes'=>60]);
        $periods=[['weekday'=>'Monday','start_time'=>'07:00','end_time'=>'11:00','unavailable'=>false]];
        $rules=app(TeacherManagementService::class);
        $rules->saveProfile($demo,['History'],1200,$periods);
        if($qualifiedOriginal) $rules->saveProfile($original,['History'],1200,$periods);
        $subject->update(['teacher_id'=>$demo->id]);
        $lesson=app(LessonManagementService::class)->save(['school_class_id'=>$class->id,'subject_id'=>$subject->id,'classroom_id'=>$room->id,'teacher_id'=>$demo->id,'day'=>'Monday','starts_at'=>'07:00','ends_at'=>'08:00','academic_year'=>'2026-2027']);
        return [$original,$demo,$subject,$lesson];
    }

    public function test_only_extra_demos_are_removed_and_original_sample_named_accounts_and_history_are_preserved(): void
    {
        [$original,$demo,$subject,$lesson]=$this->fixture();
        $report=app(DemoTeacherRemovalService::class)->removeExtras();
        $this->assertSame(1,$report['removed']);
        $this->assertSame(1,$report['unfilled_lessons']);
        $this->assertNotNull(User::find($original->id));
        $this->assertNull(User::find($demo->id));
        $this->assertNotNull(User::withoutGlobalScope('archived')->findOrFail($demo->id)->archived_at);
        $this->assertNull($subject->fresh()->teacher_id);
        $this->assertSame(0,Schedule::count());
        $history=Schedule::withoutGlobalScope('active')->findOrFail($lesson->id);
        $this->assertNotNull($history->cancelled_at);
        $this->assertSame($demo->name,$history->teacher->name);
        Storage::disk('local')->assertExists($report['backup']);
        $admin=User::factory()->create(['role'=>'administrator']);
        $this->actingAs($admin)->get(route('timetables.index',['view'=>'teachers','program'=>'Khmer','teacher'=>$demo->id]))->assertOk()->assertViewHas('selected',$original->id)->assertDontSee($demo->name);
        $this->get('/school/teachers')->assertOk()->assertSee($original->name)->assertDontSee($demo->name);
        $this->assertSame(0,app(DemoTeacherRemovalService::class)->removeExtras()['removed']);
    }

    public function test_a_qualified_original_teacher_can_replace_a_demo_without_changing_the_old_teacher_history(): void
    {
        [$original,$demo,$subject,$lesson]=$this->fixture(true);
        $report=app(DemoTeacherRemovalService::class)->removeExtras();
        $this->assertSame(1,$report['reassigned_lessons']);
        $this->assertSame(0,$report['unfilled_lessons']);
        $replacement=Schedule::sole();
        $this->assertSame($original->id,$replacement->teacher_id);
        $this->assertNotSame($lesson->id,$replacement->id);
        $this->assertSame($original->id,$subject->fresh()->teacher_id);
        $this->assertSame($demo->id,Schedule::withoutGlobalScope('active')->findOrFail($lesson->id)->teacher_id);
        app(LessonManagementService::class)->validate($replacement);
    }

    public function test_all_sample_removal_also_removes_original_sample_names_and_keeps_unlabelled_accounts(): void
    {
        [$original,$demo,$subject,$lesson]=$this->fixture();
        $existing=User::factory()->create(['role'=>'teacher','program'=>'Khmer','name'=>'Existing Khmer Teacher','status'=>'active']);
        $parent=User::factory()->create(['role'=>'parent','name'=>'Sample Parent','status'=>'active']);
        $report=app(DemoTeacherRemovalService::class)->removeAllSamples();
        $this->assertSame('all_samples',$report['scope']);
        $this->assertSame(2,$report['removed']);
        $this->assertNull(User::find($original->id));
        $this->assertNull(User::find($demo->id));
        $this->assertNotNull(User::find($existing->id));
        $this->assertNotNull(User::find($parent->id));
        $this->assertSame(1,User::where('role','teacher')->count());
        $this->assertSame($demo->name,Schedule::withoutGlobalScope('active')->findOrFail($lesson->id)->teacher->name);
        $this->assertSame(0,Schedule::count());
    }
}
