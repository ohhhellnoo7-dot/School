<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendance_session_settings', function (Blueprint $table) {
            $table->string('session')->primary();
            $table->time('starts_at')->nullable();
            $table->unsignedSmallInteger('grace_minutes')->default(10);
            $table->timestamps();
        });
        Schema::table('teacher_session_attendances', function (Blueprint $table) {
            $table->timestamp('scheduled_start')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_session_attendances', fn (Blueprint $table) => $table->dropColumn('scheduled_start'));
        Schema::dropIfExists('attendance_session_settings');
    }
};
