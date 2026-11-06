<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_management_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('khmer_teacher_free_periods')->default(3);
        });
    }

    public function down(): void
    {
        Schema::table('teacher_management_settings', fn (Blueprint $table) => $table->dropColumn('khmer_teacher_free_periods'));
    }
};
