<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('temporary_class_studies')
            ->whereNotNull('covering_teacher_id')
            ->whereNull('salary_payment_id')
            ->where('coverage_cents', '!=', 125)
            ->update(['coverage_cents' => 125]);
    }

    public function down(): void
    {
        // Preserve the recorded earnings and any payments already linked to them.
    }
};
