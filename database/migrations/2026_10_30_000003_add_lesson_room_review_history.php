<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Some existing installations already carry this legacy column.
        if (! Schema::hasColumn('schedules', 'academic_year')) Schema::table('schedules', fn (Blueprint $table) => $table->string('academic_year', 9)->nullable());
        Schema::table('schedules', fn (Blueprint $table) => $table->string('room_change_reason', 500)->nullable());
        if (Schema::hasTable('lesson_room_corrections')) {
            $required = ['schedule_id', 'previous_room', 'previous_classroom_id', 'classroom_id', 'corrected_by', 'created_at', 'updated_at'];
            if (array_diff($required, Schema::getColumnListing('lesson_room_corrections'))) throw new RuntimeException('Existing room correction history has an incompatible schema; review it before migrating.');
            return;
        }
        Schema::create('lesson_room_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->restrictOnDelete();
            $table->string('previous_room')->nullable();
            $table->foreignId('previous_classroom_id')->nullable()->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void
    {
        // Retain audit history, including tables inherited from older installations.
        Schema::table('schedules', fn (Blueprint $table) => $table->dropColumn('room_change_reason'));
    }
};
