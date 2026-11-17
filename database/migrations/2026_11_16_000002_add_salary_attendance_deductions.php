<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->date('date');
            $table->string('session');
            $table->string('status');
            $table->string('notes', 1000)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['teacher_id', 'date', 'session']);
        });
        Schema::table('employee_salary_payments', function (Blueprint $table) {
            $table->unsignedInteger('deduction_cents')->default(0);
            $table->json('deduction_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_salary_payments', function (Blueprint $table) {
            $table->dropColumn(['deduction_cents', 'deduction_details']);
        });
        Schema::dropIfExists('employee_salary_attendances');
    }
};
