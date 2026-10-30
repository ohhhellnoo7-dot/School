<?php

namespace Tests\Feature;

use App\Models\{ClassGroup, ClassSession, Classroom, Schedule, SchoolClass, Subject, User};
use App\Services\{ClassroomBookingService, KhmerClassTimetableReport, KhmerTimetableDraft, LessonManagementService, RotationTimetableGenerator, TeacherManagementService, UnifiedTimetableGenerator};
use App\Support\KhmerLessonDuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AllKhmerClassTimetablesTest extends TestCase
{
    use RefreshDatabase;

    private function cohort(int $grade, string $section, string $sessionName = 'Morning'): array
    {
        $group = ClassGroup::firstOrCreate(['grade'=>$grade, 'section'=>$section, 'program'=>'Khmer'], ['name'=>'Grade '.$grade, 'status'=>'active']);
        [$from,$to] = SchoolClass::SESSION_HOURS[$sessionName];
        $session = ClassSession::create(['class_group_id'=>$group->id, 'session'=>$sessionName, 'starts_at'=>$from, 'ends_at'=>$to]);
        $class = SchoolClass::create(['class_group_id'=>$group->id, 'name'=>'Grade '.$grade, 'section'=>$section, 'program'=>'Khmer', 'session'=>$sessionName]);
        $room = Classroom::firstOrCreate(['room_number'=>$grade.$section], ['name'=>'Room '.$grade.$section, 'capacity'=>40, 'status'=>'active']);
        foreach (TeacherManagementService::DAYS as $day) app(ClassroomBookingService::class)->create(['class_group_id'=>$group->id, 'class_session_id'=>$session->id, 'classroom_id'=>$room->id, 'teacher_id'=>null, 'day_of_week'=>$day, 'starts_at'=>$from, 'ends_at'=>$to]);
        $teacher = User::factory()->create(['role'=>'teacher','program'=>'Khmer','status'=>'active']);
        $subjects = [];
        foreach (['Mathematics', 'Khmer Literature'] as $index=>$name) $subjects[] = Subject::create(['school_class_id'=>$class->id,'teacher_id'=>$teacher->id,'name'=>$name,'code'=>$class->id.'-'.$index,'required_weekly_minutes'=>120,'lesson_minutes'=>60]);
        $rules = app(TeacherManagementService::class);
        $rules->saveProfile($teacher, ['Mathematics','Khmer Literature'],1200, array_map(fn ($day)=>['weekday'=>$day,'start_time'=>$from,'end_time'=>$to,'unavailable'=>false],$rules::DAYS));
        $start = $sessionName === 'Morning' ? '10:00' : '16:00';
        $rules->saveBreaks($teacher, array_map(fn ($day)=>['weekday'=>$day,'start_time'=>$start,'end_time'=>$to],['Monday','Wednesday','Friday']));
        return compact('class','room','teacher','subjects');
    }

    public function test_all_30_identities_and_existing_cohorts_are_generated_validated_and_shared(): void
    {
        Storage::fake('local');
        $fixtures = [];
        foreach (range(7,12) as $grade) foreach (range('A','E') as $section) $fixtures[] = $this->cohort($grade,$section);
        // One real afternoon cohort under an existing identity, not a duplicate identity.
        $fixtures[] = $this->cohort(12,'E','Afternoon');
        $draft = app(KhmerTimetableDraft::class)->prepare('2026-2027');
        $this->assertSame('draft',$draft['status'], json_encode($draft['blockers']));
        $this->assertTrue($draft['plan']['complete_school']);
        $this->assertCount(31,$draft['plan']['plans']);
        $this->assertSame(30,$draft['plan']['validation']['valid_identities']);
        $this->assertSame(0,Schedule::withoutGlobalScope('active')->count());
        foreach ($draft['plan']['plans'] as $plan) foreach ($plan['rows'] as $row) if ($row['subject_id']) $this->assertSame(120,TeacherManagementService::duration($row['starts_at'],$row['ends_at']));
        app(UnifiedTimetableGenerator::class)->confirm($draft['plan']);
        $report = app(KhmerClassTimetableReport::class)->report();
        $this->assertSame(30,$report['identity_count']);
        $this->assertSame(31,$report['cohort_count']);
        $this->assertSame(30,$report['valid_identities']);
        $admin = User::factory()->create(['role'=>'administrator']);
        foreach ([$fixtures[0],$fixtures[30]] as $fixture) {
            $lesson = Schedule::where('school_class_id',$fixture['class']->id)->where('entry_type','academic')->firstOrFail();
            foreach ([['view'=>'students','class'=>$fixture['class']->id],['view'=>'teachers','teacher'=>$fixture['teacher']->id],['view'=>'classrooms','classroom'=>$fixture['room']->id]] as $query) $this->actingAs($admin)->get(route('timetables.index',$query))->assertOk()->assertSee('data-lesson-id="'.$lesson->id.'"',false)->assertSee('rowspan="2"',false);
        }
    }

    public function test_batch_replacement_preserves_the_exact_existing_relaxation_times(): void
    {
        $f = $this->cohort(10,'B');
        $original = app(LessonManagementService::class)->saveRelaxation(['school_class_id'=>$f['class']->id,'classroom_id'=>$f['room']->id,'day'=>'Thursday','starts_at'=>'09:00','ends_at'=>'10:00']);
        $plan = app(UnifiedTimetableGenerator::class)->preview([$f['class']->id]);
        $fixed = collect($plan['plans'][0]['rows'])->where('entry_type','relaxation')->first(fn ($row)=>$row['day']==='Thursday' && substr($row['starts_at'],0,5)==='09:00');
        $this->assertNotNull($fixed);
        $this->assertSame('10:00',substr($fixed['ends_at'],0,5));
        $this->assertSame(1,Schedule::count());
        $this->assertNull($original->fresh()->cancelled_at);
    }

    public function test_odd_weekly_mathematics_hours_are_reported_without_shortening_a_lesson(): void
    {
        $f = $this->cohort(7,'A');
        $f['subjects'][0]->update(['required_weekly_minutes'=>180]);
        try { app(RotationTimetableGenerator::class)->preview($f['class']); $this->fail('Odd Mathematics hours were accepted.'); }
        catch (ValidationException $e) { $this->assertStringContainsString('complete 2-hour lesson blocks',$e->getMessage()); }
        $this->assertSame(0,Schedule::count());
    }

    public function test_other_subjects_require_one_hour_and_non_khmer_settings_are_preserved(): void
    {
        $f = $this->cohort(7,'A');
        $history = Subject::create(['school_class_id'=>$f['class']->id,'name'=>'History','code'=>'KH-HISTORY','lesson_minutes'=>120]);
        $this->assertSame(60,KhmerLessonDuration::minutes($history));
        $english = SchoolClass::create(['name'=>'English 1','section'=>'A','program'=>'English','session'=>'Morning']);
        $subject = Subject::create(['school_class_id'=>$english->id,'name'=>'History','code'=>'EN-HISTORY','lesson_minutes'=>120]);
        $this->assertSame(120,KhmerLessonDuration::minutes($subject));
        $this->actingAs(User::factory()->create(['role'=>'administrator']))->get(route('classrooms.show',$f['room']))->assertOk()->assertSee('View Lessons')->assertSee('No confirmed lesson records for this reservation.')->assertSee('room-detail-hero');
    }

    public function test_history_is_generated_as_one_hour_blocks_even_with_an_old_two_hour_setting(): void
    {
        $f = $this->cohort(7,'A');
        $f['subjects'][1]->update(['teacher_id'=>null,'required_weekly_minutes'=>0]);
        $history = Subject::create(['school_class_id'=>$f['class']->id,'name'=>'History','code'=>'H-7A','required_weekly_minutes'=>120,'lesson_minutes'=>120]);
        $rules = app(TeacherManagementService::class);
        $rules->saveProfile($f['teacher'], ['Mathematics','History'],1200,array_map(fn ($day)=>['weekday'=>$day,'start_time'=>'07:00','end_time'=>'11:00','unavailable'=>false],$rules::DAYS));
        $history->update(['teacher_id'=>$f['teacher']->id]);
        $plan = app(RotationTimetableGenerator::class)->preview($f['class']);
        $rows = collect($plan['rows'])->where('subject_id',$history->id);
        $this->assertCount(2,$rows);
        foreach ($rows as $row) $this->assertSame(60,$rules::duration($row['starts_at'],$row['ends_at']));
    }

    public function test_legacy_hourly_generator_cannot_publish_an_established_khmer_cohort(): void
    {
        $f = $this->cohort(7,'A');
        try { app(\App\Services\AutomaticTimetableGenerator::class)->generate($f['class']); $this->fail('Legacy generator bypassed the required preview.'); }
        catch (ValidationException $e) { $this->assertStringContainsString('replacement preview',$e->getMessage()); }
        $this->assertSame(0,Schedule::withoutGlobalScope('active')->count());
    }
}
