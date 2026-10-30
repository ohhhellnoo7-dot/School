<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS schedules_active_class_day_start_unique');
        DB::statement("CREATE UNIQUE INDEX schedules_active_class_day_start_unique ON schedules (school_class_id, day, starts_at, COALESCE(academic_year, '')) WHERE cancelled_at IS NULL");
    }
    public function down(): void
    {
        if (DB::table('schedules')->whereNull('cancelled_at')->whereNotNull('school_class_id')->select('school_class_id','day','starts_at')->groupBy('school_class_id','day','starts_at')->havingRaw('COUNT(*) > 1')->exists()) throw new RuntimeException('Different academic years share active slots. Review them before reverting; no records were deleted.');
        DB::statement('DROP INDEX IF EXISTS schedules_active_class_day_start_unique');
        DB::statement('CREATE UNIQUE INDEX schedules_active_class_day_start_unique ON schedules (school_class_id, day, starts_at) WHERE cancelled_at IS NULL');
    }
};
