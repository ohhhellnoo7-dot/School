<?php

namespace Tests\Feature;

use App\Models\Fee;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_enrollment_creates_unpaid_ten_cent_test_fee(): void
    {
        $this->seed(SchoolSeeder::class);
        $existing = Student::firstOrFail();
        $admin = User::where('role', 'administrator')->firstOrFail();
        $this->actingAs($admin)->post('/students', [
            'student_number' => 'QR-TEST', 'name' => 'QR Test Student', 'email' => 'qr-test@example.com',
            'gender' => 'female', 'date_of_birth' => '2013-01-01', 'school_class_id' => $existing->school_class_id,
            'guardian_name' => 'Guardian', 'guardian_phone' => '12345', 'enrollment_date' => today()->toDateString(),
            'status' => 'active', 'create_test_fee' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $student = Student::where('email', 'qr-test@example.com')->firstOrFail();
        $fee = $student->fees()->sole();
        $this->assertSame('0.10', $fee->amount);
        $this->assertSame('0.00', $fee->paid);
        $this->get(route('fees.pay', $fee))->assertOk()->assertSee('$0.10 USD')->assertSee('VICHAKA DEN');
        $this->assertSame('0.00', $fee->fresh()->paid);
    }

    public function test_student_can_only_view_own_fees_and_cannot_confirm_payment(): void
    {
        $this->seed(SchoolSeeder::class);
        $user = User::where('email', 'student@school.test')->firstOrFail();
        $student = Student::visibleTo($user)->firstOrFail();
        $fee = Fee::create(['student_id' => $student->id, 'description' => 'Test fee', 'amount' => '0.10', 'paid' => 0, 'due_date' => today()]);
        $other = Fee::create(['student_id' => Student::where('id', '!=', $student->id)->firstOrFail()->id, 'description' => 'Other fee', 'amount' => 1, 'paid' => 0, 'due_date' => today()]);
        $this->get(route('fees.pay', $fee))->assertRedirect('/login');
        $this->actingAs($user)->get(route('fees.pay', $fee))->assertOk()->assertSee('Scanning does not confirm payment');
        $this->get(route('fees.qr'))->assertOk();
        $this->get(route('fees.pay', $other))->assertNotFound();
        $this->put('/school/fees/'.$fee->id, ['paid' => '0.10'])->assertForbidden();
        $this->post(route('fees.qr.upload'))->assertForbidden();
        $this->assertSame('0.00', $fee->fresh()->paid);
    }
}
