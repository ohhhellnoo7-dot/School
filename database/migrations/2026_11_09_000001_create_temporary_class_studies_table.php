<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('temporary_class_studies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_schedule_id')->constrained('schedules')->restrictOnDelete();
            $table->foreignId('target_schedule_id')->constrained('schedules')->restrictOnDelete();
            $table->foreignId('source_classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('target_classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->date('date');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('source_class');
            $table->string('target_class');
            $table->string('subject');
            $table->string('teacher_name');
            $table->string('source_room');
            $table->string('target_room');
            $table->timestamps();
            $table->unique(['source_schedule_id', 'date']);
            $table->index(['date', 'source_classroom_id']);
            $table->index(['date', 'target_classroom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_class_studies');
    }
};
