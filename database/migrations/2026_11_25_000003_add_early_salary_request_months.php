<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_advance_requests', function (Blueprint $table) {
            $table->date('salary_month')->nullable();
            $table->timestamp('approved_at')->nullable();
        });
        Schema::table('school_expenses', fn (Blueprint $table) => $table->date('recover_from_month')->nullable());
    }

    public function down(): void
    {
        Schema::table('teacher_advance_requests', fn (Blueprint $table) => $table->dropColumn(['salary_month', 'approved_at']));
        Schema::table('school_expenses', fn (Blueprint $table) => $table->dropColumn('recover_from_month'));
    }
};
