<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_management_settings', function (Blueprint $table) {
            $table->json('salary_rates')->nullable();
        });
        Schema::table('employee_salary_payments', function (Blueprint $table) {
            $table->unsignedInteger('full_salary_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_salary_payments', fn (Blueprint $table) => $table->dropColumn('full_salary_cents'));
        Schema::table('teacher_management_settings', fn (Blueprint $table) => $table->dropColumn('salary_rates'));
    }
};
