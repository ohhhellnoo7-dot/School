<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
    public function up(): void {
        Schema::create('evening_study_periods', function (Blueprint $table) {
            $table->id(); $table->string('program', 20); $table->string('label', 80);
            $table->time('starts_at'); $table->time('ends_at'); $table->timestamps();
            $table->unique(['program', 'starts_at', 'ends_at']);
        });
        foreach (config('classrooms.chinese_evening_periods') as $id => $period) {
            DB::table('evening_study_periods')->insert($period + ['id' => $id, 'program' => 'Chinese', 'created_at' => now(), 'updated_at' => now()]);
        }
        if (DB::getDriverName() === 'pgsql') DB::statement("SELECT setval(pg_get_serial_sequence('evening_study_periods', 'id'), (SELECT MAX(id) FROM evening_study_periods))");
    }
    public function down(): void { Schema::dropIfExists('evening_study_periods'); }
};
