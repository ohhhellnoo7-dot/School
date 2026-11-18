<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_reset_settings', function (Blueprint $table) {
            $table->id();
            $table->string('password_hash');
            $table->uuid('batch')->nullable();
            $table->timestamp('restore_until')->nullable();
        });

        DB::table('timetable_reset_settings')->insert([
            'id' => 1,
            'password_hash' => '$2y$12$uAV9UPhjDBxXghjXhPPKj.ngGAEV7wxoC4iBxxtspGDuRwceoOQLO',
        ]);

        Schema::table('schedules', function (Blueprint $table) {
            $table->uuid('reset_batch')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['reset_batch']);
            $table->dropColumn('reset_batch');
        });
        Schema::dropIfExists('timetable_reset_settings');
    }
};
