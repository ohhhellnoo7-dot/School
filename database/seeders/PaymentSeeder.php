<?php

namespace Database\Seeders;

use App\Models\Fee;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Tuition Fee' => 350, 'Registration Fee' => 25, 'Examination Fee' => 15, 'Transportation Fee' => 40, 'Library Fee' => 10];
        foreach (CambodianSchoolData::students() as $index => $student) {
            foreach ($categories as $category => $amount) {
                $kind = ($index + array_search($category, array_keys($categories))) % 4;
                $paid = match ($kind) {
                    0 => $amount, 2 => $amount / 2, default => 0
                };
                $paidDate = today()->startOfMonth()->subMonths($index % 6)->addDays(3);
                if ($paidDate->lt($student->enrollment_date)) {
                    $paidDate = $student->enrollment_date->copy();
                }
                if ($paidDate->gt(today())) {
                    $paidDate = today();
                }
                Fee::firstOrCreate(['student_id' => $student->id, 'description' => $category], [
                    'amount' => $amount, 'paid' => $paid,
                    'due_date' => ($kind === 3 ? today()->subDays(14) : today()->addDays(14))->toDateString(),
                    'paid_at' => $paid > 0 ? $paidDate->toDateString() : null,
                ]);
            }
        }
    }
}
