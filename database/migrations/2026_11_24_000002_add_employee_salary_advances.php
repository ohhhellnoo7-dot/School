<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_expenses', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->constrained('users');
            $table->unsignedBigInteger('advance_recovered_cents')->default(0);
            $table->index(['employee_id', 'spent_on']);
        });
        Schema::table('employee_salary_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('advance_cents')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('employee_salary_payments', fn (Blueprint $table) => $table->dropColumn('advance_cents'));
        Schema::table('school_expenses', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'spent_on']);
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn('advance_recovered_cents');
        });
    }
};
