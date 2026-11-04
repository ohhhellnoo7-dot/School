<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $teachers = DB::table('users')->whereNotNull('program')->whereNotIn('program', ['Khmer', ''])->pluck('id');
            $plans = DB::table('teacher_break_plans')->whereIn('teacher_id', $teachers)->pluck('id');
            DB::table('teacher_breaks')->whereIn('teacher_break_plan_id', $plans)->delete();
            DB::table('teacher_break_plans')->whereIn('id', $plans)->delete();
        });
    }

    public function down(): void
    {
        // Removed free periods cannot be reconstructed.
    }
};
