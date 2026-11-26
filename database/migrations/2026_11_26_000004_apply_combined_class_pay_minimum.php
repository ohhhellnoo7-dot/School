<?php

use App\Services\CombinedClassPay;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $unpaid = DB::table('temporary_class_studies')
            ->whereNotNull('covering_teacher_id')->whereNull('salary_payment_id');

        $unpaid->select(['id', 'starts_at', 'ends_at', 'coverage_cents'])->chunkById(200, function ($studies) use ($unpaid) {
            foreach ($studies as $study) {
                $amount = CombinedClassPay::lessonCents($study->starts_at, $study->ends_at);
                if ($amount !== (int) $study->coverage_cents) {
                    (clone $unpaid)->where('id', $study->id)->update(['coverage_cents' => $amount]);
                }
            }
        });
    }

    public function down(): void
    {
        // Preserve recorded earnings and any payments already linked to them.
    }
};
