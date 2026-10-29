<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropUnique(['name', 'section']);
            $table->unique(['name', 'section', 'program', 'session'], 'school_classes_program_session_unique');
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropUnique('school_classes_program_session_unique');
            $table->unique(['name', 'section']);
        });
    }
};
