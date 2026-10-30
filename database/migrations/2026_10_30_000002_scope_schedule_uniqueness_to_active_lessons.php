<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('schedules', fn (Blueprint $t) => $t->dropUnique('schedules_class_slot_unique'));
        DB::statement('CREATE UNIQUE INDEX schedules_active_class_day_start_unique ON schedules (school_class_id, day, starts_at) WHERE cancelled_at IS NULL');
    }

    public function down(): void
    {
        if (DB::table('schedules')->whereNotNull('school_class_id')->select('school_class_id', 'day', 'starts_at')->groupBy('school_class_id', 'day', 'starts_at')->havingRaw('COUNT(*) > 1')->exists()) throw new RuntimeException('Review cancellation history before restoring the original unique constraint. No history was deleted.');
        DB::statement('DROP INDEX IF EXISTS schedules_active_class_day_start_unique');
        Schema::table('schedules', fn (Blueprint $t) => $t->unique(['school_class_id', 'day', 'starts_at'], 'schedules_class_slot_unique'));
    }
};
