<?php

namespace Tests\Feature;

use App\Models\{ClassGroup,ClassSession,Classroom,Schedule,SchoolClass,Subject,User};
use App\Services\{ClassroomBookingService,SchoolRoomAssignmentRepair,TeacherManagementService,TimetableRoomReconciliation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;

class SchoolWideRoomRepairTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $program='English', int $grade=1): array
    {
        [$sessionName,$from,$to] = match ($program) { 'Khmer'=>['Morning','07:00','11:00'], 'Chinese'=>['Evening','18:30','19:30'], default=>['Afternoon','13:00','17:00'] };
        $group = ClassGroup::create(['program'=>$program,'grade'=>$grade,'section'=>$program === 'Khmer'?'A':null,'name'=>'Grade '.$grade,'status'=>'active']);
        $session = ClassSession::create(['class_group_id'=>$group->id,'session'=>$sessionName,'starts_at'=>$from,'ends_at'=>$to]);
        $class = SchoolClass::create(['class_group_id'=>$group->id,'name'=>'Grade '.$grade,'section'=>'A','program'=>$program,'session'=>$sessionName]);
        $teacher = User::factory()->create(['role'=>'teacher','program'=>$program,'status'=>'active']);
        $subject = Subject::create(['school_class_id'=>$class->id,'teacher_id'=>$teacher->id,'name'=>$program === 'Khmer'?'Mathematics':'Writing','code'=>$program.$grade,'required_weekly_minutes'=>120]);
        $room = Classroom::create(['room_number'=>$program.$grade,'name'=>'Physical '.$program.' room '.$grade,'status'=>'active','capacity'=>40]);
        app(ClassroomBookingService::class)->create(['class_group_id'=>$group->id,'class_session_id'=>$session->id,'classroom_id'=>$room->id,'teacher_id'=>null,'day_of_week'=>'Monday','starts_at'=>$from,'ends_at'=>$to]);
        $end = $program === 'Chinese' ? '19:30' : ($program === 'Khmer'?'09:00':'15:00');
        $id = DB::table('schedules')->insertGetId(['school_class_id'=>$class->id,'subject_id'=>$subject->id,'teacher_id'=>$teacher->id,'day'=>'Monday','starts_at'=>$from,'ends_at'=>$end,'room'=>'LEGACY-'.$program.$grade,'entry_type'=>'academic','academic_year'=>'2026-2027']);
        return compact('group','session','class','teacher','subject','room','id');
    }

    public function test_unique_explicit_reservations_repair_all_programs_without_changing_lessons(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role'=>'administrator']);
        $fixtures = [$this->fixture('Khmer',7),$this->fixture('English'),$this->fixture('Chinese')];
        $before = DB::table('schedules')->get()->keyBy('id');
        $service = app(SchoolRoomAssignmentRepair::class);
        $this->assertCount(3,$service->scan()['verified']);
        $result = $service->repairUnambiguous($admin->id);
        $this->assertSame(3,$result['repaired']);
        $this->assertSame(0,$result['after']['affected_lessons']);
        foreach ($fixtures as $f) {
            $lesson = Schedule::findOrFail($f['id']);
            $this->assertSame($f['room']->id,$lesson->classroom_id);
            foreach (['subject_id','teacher_id','school_class_id','day','starts_at','ends_at','academic_year'] as $field) $this->assertSame($before[$lesson->id]->$field,$lesson->$field);
            foreach ([['view'=>'students','class'=>$f['class']->id],['view'=>'teachers','teacher'=>$f['teacher']->id],['view'=>'classrooms','classroom'=>$f['room']->id]] as $query) $this->actingAs($admin)->get(route('timetables.index',$query))->assertOk()->assertSee('data-lesson-id="'.$lesson->id.'"',false)->assertDontSee('Room assignment needs review.');
        }
        $this->assertSame(3,DB::table('lesson_room_corrections')->count());
        Storage::disk('local')->assertExists($result['backup']);
    }

    public function test_ambiguous_room_names_require_confirmation_and_valid_ids_are_preserved(): void
    {
        $f = $this->fixture();
        Classroom::create(['room_number'=>'OTHER','name'=>$f['room']->room_number,'status'=>'active','capacity'=>40]);
        DB::table('schedules')->where('id',$f['id'])->update(['room'=>$f['room']->room_number]);
        $scan = app(SchoolRoomAssignmentRepair::class)->scan();
        $this->assertCount(0,$scan['verified']);
        $this->assertStringContainsString('ambiguous',$scan['manual_review'][0]['reason']);
        DB::table('schedules')->where('id',$f['id'])->update(['classroom_id'=>$f['room']->id]);
        $before = Schedule::findOrFail($f['id'])->getAttributes();
        $service = app(SchoolRoomAssignmentRepair::class);
        $this->assertSame(0,$service->scan()['affected_lessons']);
        $this->assertSame($before,Schedule::findOrFail($f['id'])->getAttributes());
    }

    public function test_conflicting_proposals_do_not_get_auto_repaired_and_reservations_are_checked(): void
    {
        $a = $this->fixture('English',1); $b = $this->fixture('English',2);
        DB::table('schedules')->where('id',$b['id'])->update(['room'=>$a['room']->room_number]);
        $scan = app(SchoolRoomAssignmentRepair::class)->scan();
        $this->assertCount(0,$scan['verified']);
        $this->assertCount(2,$scan['manual_review']);
        $this->assertStringContainsString('reserved',$scan['records'][1]['reason']);
        $this->assertSame(0,Schedule::whereNotNull('classroom_id')->count());
    }

    public function test_different_years_do_not_conflict_and_one_shared_lesson_filters_consistently(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $row = (array) DB::table('schedules')->find($f['id']); unset($row['id']);
        $row['academic_year'] = '2027-2028';
        $otherId = DB::table('schedules')->insertGetId($row);
        $service = app(SchoolRoomAssignmentRepair::class);
        $scan = $service->scan();
        $this->assertCount(2,$scan['verified']);
        $this->assertCount(0,$scan['conflicts']);
        $admin = User::factory()->create(['role'=>'administrator']);
        $this->assertSame(2,$service->repairUnambiguous($admin->id)['repaired']);
        $snapshot = app(\App\Services\ClassroomAvailabilityService::class)->forDateAndTime('2026-11-02','13:30')->firstWhere('classroom.id',$f['room']->id);
        $this->assertSame($f['id'],$snapshot['currentLesson']->id);
        $this->assertFalse($snapshot['lessonConflict']);
        $this->actingAs($admin)->get(route('timetables.index',['class'=>$f['class']->id,'academic_year'=>'2026-2027']))->assertOk()->assertSee('data-lesson-id="'.$f['id'].'"',false)->assertDontSee('data-lesson-id="'.$otherId.'"',false);
    }

    public function test_unique_legacy_label_can_be_repaired_without_guessing_from_class_names(): void
    {
        Storage::fake('local'); $f = $this->fixture();
        DB::table('classroom_bookings')->delete();
        DB::table('schedules')->where('id',$f['id'])->update(['room'=>$f['room']->name]);
        $admin = User::factory()->create(['role'=>'administrator']);
        $result = app(SchoolRoomAssignmentRepair::class)->repairUnambiguous($admin->id);
        $this->assertSame(1,$result['repaired']);
        $this->assertSame($f['room']->id,Schedule::findOrFail($f['id'])->classroom_id);
    }

    public function test_room_changes_require_an_explicit_reason_and_the_school_report_covers_every_program(): void
    {
        Storage::fake('local');
        $f = $this->fixture();
        $admin = User::factory()->create(['role'=>'administrator']);
        $other = Classroom::create(['room_number'=>'LAB','name'=>'Computer Lab','status'=>'active','capacity'=>40]);
        $manual = app(TimetableRoomReconciliation::class);
        try { $manual->preview([$f['id']=>$other->id]); $this->fail('Reservation mismatch was accepted without approval.'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertStringContainsString('approved room change',$e->getMessage()); }
        $plan = $manual->preview([$f['id']=>$other->id],[$f['id']=>'Approved computer lab lesson']);
        $manual->confirm($plan,$admin->id);
        $this->assertSame('Approved computer lab lesson',Schedule::findOrFail($f['id'])->room_change_reason);
        $this->fixture('Khmer',7); $this->fixture('Chinese');
        $response = $this->actingAs($admin)->getJson(route('timetables.school-audit',['format'=>'json']))->assertOk();
        $this->assertSame(3,$response->json('total_classes_checked'));
        $this->assertSame(['English'=>1,'Khmer'=>1,'Chinese'=>1],$response->json('programs'));
        $this->actingAs(User::factory()->create(['role'=>'student']))->get(route('timetables.school-audit'))->assertForbidden();
    }

    public function test_grade_and_session_filters_do_not_keep_a_previous_unrelated_class_selected(): void
    {
        $english = $this->fixture('English',1); $khmer = $this->fixture('Khmer',7);
        $admin = User::factory()->create(['role'=>'administrator']);
        $this->actingAs($admin)->get(route('timetables.index',['view'=>'students','class'=>$khmer['class']->id,'grade'=>1,'session'=>'Afternoon']))->assertOk()->assertSee('data-lesson-id="'.$english['id'].'"',false)->assertDontSee('data-lesson-id="'.$khmer['id'].'"',false);
    }
}
