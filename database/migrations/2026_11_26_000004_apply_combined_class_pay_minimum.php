<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $unpaid = DB::table('temporary_class_studies')
            ->whereNotNull('covering_teacher_id')->whereNull('salary_payment_id');

        $unpaid->select(['id', 'starts_at', 'ends_at', 'coverage_cents'])->chunkById(200, function ($studies) use ($unpaid) {
            foreach ($studies as $study) {
                $seconds = static function (string $time): int {
                    [$hours, $minutes, $seconds] = array_pad(array_map('intval', explode(':', $time)), 3, 0);

                    return $hours * 3600 + $minutes * 60 + $seconds;
                };
                $duration = $seconds($study->ends_at) - $seconds($study->starts_at);
                $amount = (int) round(max(3600, $duration) * 125 / 3600);
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
