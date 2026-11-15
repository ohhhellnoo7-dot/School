<?php

namespace Tests\Feature;

use App\Models\Fee;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        Storage::fake('local');
        $this->get(route('fees.qr'))->assertNotFound();
        $this->get(route('fees.pay', $other))->assertNotFound();
        $this->put('/school/fees/'.$fee->id, ['paid' => '0.10'])->assertForbidden();
        $this->post(route('fees.qr.upload'))->assertForbidden();
        $this->assertSame('0.00', $fee->fresh()->paid);
    }

    public function test_enrollment_checkout_converts_to_khr_and_confirms_cash_once(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $student = Student::factory()->create();
        $this->actingAs($admin)->get(route('students.enrollment-payment', $student))->assertOk()->assertSee('4,100 KHR');
        $data = ['amount_usd' => '12.50', 'currency' => 'KHR', 'method' => 'cash'];
        $this->post(route('students.enrollment-payment.store', $student), $data)->assertSessionHasNoErrors();
        $this->post(route('students.enrollment-payment.store', $student), $data)->assertSessionHasNoErrors();
        $fee = $student->fees()->sole();
        $this->assertSame('51250.00', $fee->amount);
        $this->assertSame('KHR', $fee->currency);
        $this->assertSame('0.00', $fee->paid);
        $this->get(route('fees.pay', $fee))->assertOk()->assertSee('51250.00 KHR');
        $confirm = ['request_key' => (string) Str::uuid(), 'verified' => '1'];
        $this->post(route('fees.confirm', $fee), $confirm)->assertSessionHasErrors('payment');
        $this->post(route('fees.checkout', $fee), ['method' => 'cash'])->assertSessionHasNoErrors();
        $this->get(route('fees.pay', $fee))->assertOk()->assertSee('Awaiting confirmation.');
        $this->getJson(route('fees.status', $fee))->assertJson(['paid' => false, 'pending' => true]);
        $this->post(route('fees.confirm', $fee), $confirm)->assertSessionHasNoErrors();
        $this->post(route('fees.confirm', $fee), $confirm)->assertSessionHasNoErrors();
        $this->assertSame('51250.00', $fee->fresh()->paid);
        $this->assertSame(1, $fee->payments()->count());
        $this->assertSame('KHR', $fee->payments()->sole()->currency);
        $this->getJson(route('fees.status', $fee))->assertJson(['paid' => true]);
        $this->get(route('fees.pay', $fee))->assertOk()->assertSee('Payment confirmed.');
        $this->get(route('finance.receipt', $fee->payments()->sole()))->assertOk();
    }

    public function test_khqr_requires_uploaded_image_and_manual_verified_reference(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'administrator']);
        $user = User::factory()->create(['role' => 'student']);
        $student = Student::factory()->create(['user_id' => $user->id]);
        $this->actingAs($admin)->post(route('students.enrollment-payment.store', $student), ['amount_usd' => '1', 'currency' => 'USD', 'method' => 'qr'])->assertSessionHasNoErrors();
        $fee = $student->fees()->sole();
        $this->post(route('fees.checkout', $fee), ['method' => 'qr'])->assertSessionHasErrors('method');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $this->post(route('fees.qr.upload'), ['qr' => UploadedFile::fake()->createWithContent('school.png', $png)])->assertSessionHasNoErrors();
        $this->actingAs($user)->get(route('fees.pay', $fee))->assertOk()->assertSee('School KHQR for VICHAKA DEN');
        $this->get(route('fees.qr'))->assertOk();
        $this->post(route('fees.checkout', $fee), ['method' => 'qr', 'reference' => 'BANK123'])->assertSessionHasNoErrors();
        $this->assertSame('0.00', $fee->fresh()->paid);
        $confirm = ['request_key' => (string) Str::uuid(), 'verified' => '1'];
        $this->post(route('fees.confirm', $fee), $confirm)->assertForbidden();
        $otherFee = Fee::create(['student_id' => Student::factory()->create()->id, 'description' => 'Other fee', 'amount' => 1, 'due_date' => today()]);
        $this->getJson(route('fees.status', $otherFee))->assertNotFound();
        $this->post(route('fees.checkout', $otherFee), ['method' => 'cash'])->assertNotFound();
        $this->get(route('students.enrollment-payment', $student))->assertForbidden();
        $this->actingAs($admin)->post(route('fees.confirm', $fee), $confirm)->assertSessionHasErrors('reference');
        $this->post(route('fees.confirm', $fee), $confirm + ['reference' => 'BANK123'])->assertSessionHasNoErrors();
        $this->assertSame('1.00', $fee->fresh()->paid);
        $this->assertSame('qr', $fee->payments()->sole()->method);
        $this->post(route('fees.checkout', $otherFee), ['method' => 'qr'])->assertSessionHasNoErrors();
        $this->post(route('fees.confirm', $otherFee), ['request_key' => (string) Str::uuid(), 'verified' => '1', 'reference' => 'BANK123'])->assertSessionHasErrors('reference');
    }
}
