<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->string('program')->nullable();
            $table->string('session')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', fn (Blueprint $table) => $table->dropColumn(['program', 'session']));
    }
};
