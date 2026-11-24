<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_management_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('bonus_service_months')->default(6);
        });
    }

    public function down(): void
    {
        Schema::table('teacher_management_settings', function (Blueprint $table) {
            $table->dropColumn('bonus_service_months');
        });
    }
};
