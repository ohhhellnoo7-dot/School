<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('employee_salary_payments', function (Blueprint $table) {
            $table->unsignedInteger('combined_class_cents')->default(0);
        });

        Schema::table('temporary_class_studies', function (Blueprint $table) {
            $table->foreignId('covering_teacher_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('coverage_cents')->default(0);
            $table->foreignId('salary_payment_id')->nullable()->constrained('employee_salary_payments')->nullOnDelete();
            $table->index(['covering_teacher_id', 'salary_payment_id', 'date'], 'class_studies_payroll_index');
        });
    }

    public function down(): void
    {
        Schema::table('temporary_class_studies', function (Blueprint $table) {
            $table->dropIndex('class_studies_payroll_index');
            $table->dropConstrainedForeignId('salary_payment_id');
            $table->dropConstrainedForeignId('covering_teacher_id');
            $table->dropColumn('coverage_cents');
        });
        Schema::table('employee_salary_payments', fn (Blueprint $table) => $table->dropColumn('combined_class_cents'));
    }
};
