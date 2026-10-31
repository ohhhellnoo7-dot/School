<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes',fn (Blueprint $table) => $table->foreignId('shared_timetable_class_id')->nullable()->constrained('school_classes')->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('school_classes',fn (Blueprint $table) => $table->dropConstrainedForeignId('shared_timetable_class_id'));
    }
};
