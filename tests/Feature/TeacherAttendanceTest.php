<?php
namespace Tests\Feature;
use App\Models\Attendance;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class TeacherAttendanceTest extends TestCase
{
    use RefreshDatabase;
    public function test_admin_marks_all_programs_and_updates_without_duplicates_or_student_changes(): void {
        $admin = User::factory()->create(['role'=>'administrator']);
        $teachers = collect(['Khmer','English','Chinese','Japan'])->map(fn ($program) => User::factory()->create(['role'=>'teacher','program'=>$program,'status'=>'active']));
        $this->actingAs($admin)->get(route('teacher-attendance.index'))->assertOk()->assertSee('Unmarked');
        $rows = $teachers->values()->map(fn ($t,$i) => ['teacher_id'=>$t->id,'status'=>['present','absent','late','excused'][$i],'notes'=>'Test'])->all();
        $this->post(route('teacher-attendance.store'), ['date'=>today()->toDateString(),'records'=>$rows])->assertSessionHasNoErrors();
        $this->assertSame(4, TeacherAttendance::count());
        $this->assertSame(0, Attendance::count());
        $rows[0]['status']='late';
        $this->post(route('teacher-attendance.store'), ['date'=>today()->toDateString(),'records'=>$rows])->assertSessionHasNoErrors();
        $this->assertSame(4, TeacherAttendance::count());
        $this->get(route('teacher-attendance.index',['program'=>'Chinese']))->assertOk()->assertSee($teachers[2]->name)->assertDontSee($teachers[0]->email);
        $this->get('/dashboard')->assertOk()->assertSee('Teacher attendance report');
        $this->getJson('/dashboard/data')->assertOk();
    }
    public function test_access_and_invalid_inputs_do_not_create_records(): void {
        $admin = User::factory()->create(['role'=>'administrator']);
        $teacher = User::factory()->create(['role'=>'teacher','program'=>'Khmer','status'=>'active']);
        $other = User::factory()->create(['role'=>'teacher','program'=>'Chinese','status'=>'active']);
        $data=['date'=>today()->toDateString(),'records'=>[['teacher_id'=>$teacher->id,'status'=>'present']]];
        $this->actingAs($teacher)->get(route('teacher-attendance.index'))->assertOk()->assertSee($teacher->email)->assertDontSee($other->email);
        $this->get(route('teacher-attendance.index',['program'=>'Chinese']))->assertOk()->assertDontSee($other->email);
        $this->post(route('teacher-attendance.store'),$data)->assertForbidden();
        foreach (['parent','student'] as $role) {
            $user=User::factory()->create(['role'=>$role]);
            $this->actingAs($user)->get(route('teacher-attendance.index'))->assertForbidden();
            $this->post(route('teacher-attendance.store'),$data)->assertForbidden();
        }
        $this->actingAs($admin)->post(route('teacher-attendance.store'),array_replace($data,['date'=>today()->addDay()->toDateString()]))->assertSessionHasErrors('date');
        $this->post(route('teacher-attendance.store'),$data+['program'=>'Chinese'])->assertSessionHasErrors('records');
        $this->post(route('teacher-attendance.store'),['date'=>today()->toDateString(),'records'=>[['teacher_id'=>$admin->id,'status'=>'present']]])->assertSessionHasErrors('records');
        $this->post(route('teacher-attendance.store'),['date'=>today()->toDateString(),'records'=>[['teacher_id'=>$teacher->id,'status'=>'invalid']]])->assertSessionHasErrors();
        $this->assertSame(0,TeacherAttendance::count());
    }
    public function test_date_history_keeps_program_snapshot_and_unmarked_is_not_absent(): void {
        $admin=User::factory()->create(['role'=>'administrator']);
        $teacher=User::factory()->create(['role'=>'teacher','program'=>'Khmer','status'=>'active']);
        $date=today()->subDay()->toDateString();
        $this->actingAs($admin)->post(route('teacher-attendance.store'),['date'=>$date,'records'=>[['teacher_id'=>$teacher->id,'status'=>'present']]])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('teacher_attendances', ['teacher_id'=>$teacher->id, 'program'=>'Khmer', 'status'=>'present']);
        $teacher->update(['program'=>'English']);
        $this->get(route('teacher-attendance.index',['date'=>$date,'program'=>'Khmer']))->assertOk()->assertSee($teacher->email)->assertViewHas('counts',fn ($counts)=>$counts['present']===1);
        $this->get(route('teacher-attendance.index'))->assertOk()->assertViewHas('records',fn ($records)=>$records->isEmpty());
        $this->post(route('teacher-attendance.store'),['date'=>today()->toDateString(),'records'=>[['teacher_id'=>$teacher->id,'status'=>null]]])->assertSessionHasNoErrors();
        $this->assertSame(1,TeacherAttendance::count());
    }
}
