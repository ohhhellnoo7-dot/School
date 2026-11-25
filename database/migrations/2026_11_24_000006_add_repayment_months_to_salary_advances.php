<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['teacher_advance_requests', 'school_expenses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedTinyInteger('repayment_months')->default(1);
            });
        }
    }

    public function down(): void
    {
        foreach (['teacher_advance_requests', 'school_expenses'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('repayment_months');
            });
        }
    }
};
