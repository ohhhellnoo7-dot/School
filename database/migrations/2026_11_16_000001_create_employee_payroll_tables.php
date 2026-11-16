<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->unique()->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('day_sessions')->default(0);
            $table->unsignedTinyInteger('evening_sessions')->default(0);
            $table->date('service_started_on');
            $table->foreignId('updated_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('employee_salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->date('salary_month');
            $table->unsignedTinyInteger('day_sessions');
            $table->unsignedTinyInteger('evening_sessions');
            $table->date('service_started_on');
            $table->unsignedInteger('base_cents');
            $table->unsignedInteger('raise_cents');
            $table->unsignedInteger('total_cents');
            $table->date('paid_on');
            $table->string('reference')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['teacher_id', 'salary_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_payments');
        Schema::dropIfExists('employee_salary_profiles');
    }
};
