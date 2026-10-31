<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\{DemoTeacherRemovalService,TeacherEmployeeConfirmation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TeacherEmployeeConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_confirmation_removes_the_label_without_claiming_independent_verification(): void
    {
        Storage::fake('local');
        $teacher=User::factory()->create(['role'=>'teacher','program'=>'Khmer','name'=>'Sok Dara','teacher_record_source'=>'generated_unverified','email_verified_at'=>null]);
        $english=User::factory()->create(['role'=>'teacher','program'=>'English','teacher_record_source'=>'generated_unverified']);
        $this->assertTrue($teacher->requiresTeacherConfirmation());
        $service=app(TeacherEmployeeConfirmation::class);
        $result=$service->confirmKhmerRecords();
        $this->assertSame(1,$result['confirmed']);
        $this->assertFalse($result['independent_identity_check']);
        $teacher->refresh();
        $this->assertFalse($teacher->requiresTeacherConfirmation());
        $this->assertSame('generated_unverified',$teacher->teacher_record_source);
        $this->assertSame('explicit_user_instruction',$teacher->employee_confirmation_source);
        $this->assertNotNull($teacher->employee_confirmed_at);
        $this->assertNull($teacher->email_verified_at);
        $this->assertNull($english->fresh()->employee_confirmed_at);
        $this->assertSame(0,$service->confirmKhmerRecords()['confirmed']);
        $admin=User::factory()->create(['role'=>'administrator']);
        $this->actingAs($admin)->get(route('timetables.index',['view'=>'teachers','program'=>'Khmer']))->assertOk()->assertSee('Sok Dara')->assertDontSee('(unverified)');
        $this->get(route('teacher-management.show',$teacher))->assertOk()->assertSee('Employee status: manually confirmed')->assertDontSee('Staff identity and proposed subjects require school confirmation');
        app(DemoTeacherRemovalService::class)->removeAllSamples();
        $this->assertNotNull(User::find($teacher->id));
    }
}
