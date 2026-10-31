<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_management_settings',function (Blueprint $table) {
            $table->unsignedInteger('preparation_minutes')->default(600);
            $table->unsignedInteger('marking_minutes')->default(300);
            $table->unsignedInteger('other_duties_minutes')->default(300);
        });
    }

    public function down(): void
    {
        Schema::table('teacher_management_settings',fn (Blueprint $table)=>$table->dropColumn(['preparation_minutes','marking_minutes','other_duties_minutes']));
    }
};
