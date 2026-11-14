<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('programs')->get() as $program) {
            if (! in_array('Evening', json_decode($program->sessions, true) ?? [], true)) continue;
            if (DB::table('evening_study_periods')->where('program', $program->name)->exists()) continue;

            foreach (config('classrooms.chinese_evening_periods') as $period) {
                DB::table('evening_study_periods')->insert($period + [
                    'program' => $program->name, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep study times that may already be used by saved lessons.
    }
};
