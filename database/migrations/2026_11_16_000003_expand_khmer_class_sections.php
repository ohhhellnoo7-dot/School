<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('ALTER TABLE class_groups DROP CONSTRAINT class_groups_identity_check');
        DB::statement("ALTER TABLE class_groups ADD CONSTRAINT class_groups_identity_check CHECK ((program = 'Khmer' AND grade BETWEEN 7 AND 12 AND section IS NOT NULL AND section ~ '^[A-Z]$') OR (program <> 'Khmer' AND section IS NULL))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;
        DB::statement('ALTER TABLE class_groups DROP CONSTRAINT class_groups_identity_check');
        DB::statement("ALTER TABLE class_groups ADD CONSTRAINT class_groups_identity_check CHECK ((program = 'Khmer' AND grade BETWEEN 7 AND 12 AND section IS NOT NULL AND section IN ('A','B','C','D','E')) OR (program <> 'Khmer' AND section IS NULL))");
    }
};
