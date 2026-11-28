<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_weekend_salary_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_month');
            $table->unsignedInteger('hourly_cents');
            $table->unsignedSmallInteger('daily_minutes');
            $table->timestamp('created_at');
            $table->index(['teacher_id', 'effective_month', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_weekend_salary_settings');
    }
};
