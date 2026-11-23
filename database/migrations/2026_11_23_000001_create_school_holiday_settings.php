<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_holiday_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('enabled')->default(false);
        });

        DB::table('school_holiday_settings')->insert(['id' => 1, 'enabled' => false]);
    }

    public function down(): void
    {
        Schema::dropIfExists('school_holiday_settings');
    }
};
