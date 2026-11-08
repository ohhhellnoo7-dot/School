<?php
namespace Tests\Feature;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class ProgramReportChartTest extends TestCase
{
    use RefreshDatabase;
    public function test_reports_filter_finance_by_academic_year_and_show_real_invoice_statuses(): void
    {
        $admin=User::factory()->create(['role'=>'administrator']);
        $student=Student::factory()->create();
        foreach (['2025-2026'=>'20.00','2026-2027'=>'100.00'] as $year=>$paid) {
            \App\Models\Fee::create(['student_id'=>$student->id,'description'=>'Tuition','amount'=>'100.00','paid'=>$paid,'opening_paid'=>$paid,'due_date'=>today(),'currency'=>'USD','program'=>'Khmer','academic_year'=>$year]);
        }
        $this->actingAs($admin)->get(route('reports',['academic_year'=>'2026-2027']))->assertOk()
            ->assertSee('reports-hero.png')->assertSee('data-program-collection', false)->assertSee('data-invoice-status', false)
            ->assertViewHas('summary', fn ($summary)=>$summary['USD']['paid']===10000)
            ->assertViewHas('reportDetails', fn ($details)=>$details['USD']['statuses']['paid']===1 && $details['USD']['statuses']['partial']===0);
        $this->get(route('reports'))->assertOk()->assertViewHas('reportDetails', fn ($details)=>$details['USD']['statuses']['partial']===1);
    }

    public function test_report_counts_students_once_per_program_and_excludes_inactive_enrollments(): void
    {
        $admin=User::factory()->create(['role'=>'administrator']);
        $morning=SchoolClass::create(['name'=>'Grade 7','program'=>'Khmer','session'=>'Morning','section'=>'A','capacity'=>40]);
        $afternoon=SchoolClass::create(['name'=>'Grade 7','program'=>'Khmer','session'=>'Afternoon','section'=>'A','capacity'=>40]);
        $chinese=SchoolClass::create(['name'=>'Grade 1','program'=>'Chinese','session'=>'Evening','section'=>'A','capacity'=>40]);
        $student=Student::factory()->create(['school_class_id'=>$morning->id,'status'=>'active','enrollment_date'=>today()]);
        $student->studyClasses()->syncWithoutDetaching([$afternoon->id,$chinese->id]);
        Student::factory()->create(['school_class_id'=>$morning->id,'status'=>'inactive']);
        Student::factory()->create(['school_class_id'=>$morning->id,'status'=>'active','enrollment_date'=>today()->addDay()]);
        $this->actingAs($admin)->get(route('reports'))->assertOk()->assertSee('programStudentsChart')->assertSee('report-charts.js')
            ->assertViewHas('studentsByProgram',fn ($counts)=>$counts['Khmer']===1 && $counts['Chinese']===1 && $counts['English']===0 && $counts['Japan']===0);
        $this->actingAs(User::factory()->create(['role'=>'parent']))->get(route('reports'))->assertForbidden();
    }
}

