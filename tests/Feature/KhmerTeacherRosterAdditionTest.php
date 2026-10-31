<?php

namespace Tests\Feature;

use App\Models\{SchoolClass,Subject,User};
use App\Services\{KhmerTeacherRosterAddition,TeacherManagementService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KhmerTeacherRosterAdditionTest extends TestCase
{
    use RefreshDatabase;

    public function test_twenty_records_are_added_once_with_normal_names_provenance_and_two_subjects(): void
    {
        Storage::fake('local');
        for($i=0;$i<10;$i++) User::factory()->create(['role'=>'teacher','program'=>'Khmer','name'=>'Existing Teacher '.$i]);
        $class=SchoolClass::create(['name'=>'Grade 7','section'=>'A','program'=>'Khmer','session'=>'Morning']);
        $names=collect(KhmerTeacherRosterAddition::RECORDS)->flatMap(fn($record)=>array_slice($record,1))->unique()->values();
        foreach($names as $index=>$name) Subject::create(['name'=>$name,'code'=>'CAT-'.$index,'school_class_id'=>$class->id,'required_weekly_minutes'=>60]);
        $service=app(KhmerTeacherRosterAddition::class);
        $result=$service->apply();
        $this->assertSame(20,$result['created']);
        $this->assertSame(30,User::where('role','teacher')->where('program','Khmer')->count());
        $rules=app(TeacherManagementService::class);
        foreach(User::where('teacher_record_source','generated_unverified')->get() as $teacher) {
            $this->assertStringNotContainsString('Sample',$teacher->name);
            $this->assertSame(['Grade 7'],$teacher->teacher_planned_grades);
            $this->assertSame(2400,$rules->limit($teacher));
            $this->assertCount(2,$rules->qualifications($teacher));
            $this->assertCount(10,$rules->periods($teacher));
            $this->assertCount(3,$rules->breaks($teacher));
        }
        $this->assertSame(0,$service->apply()['created']);
        $admin=User::factory()->create(['role'=>'administrator']);
        $teacher=User::where('profile_number','KHM-REG-001')->sole();
        $this->actingAs($admin)->get(route('teacher-management.show',$teacher))->assertOk()->assertSee('Generated teacher record')->assertSee('school confirmation');
        $this->get(route('timetables.index',['view'=>'teachers','program'=>'Khmer']))->assertOk()->assertSee('Sok Dara')->assertSee('(unverified)');
    }
}
