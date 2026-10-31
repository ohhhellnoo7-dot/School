<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users',function(Blueprint $table) {
            $table->string('teacher_record_source')->nullable();
            $table->json('teacher_planned_grades')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users',fn(Blueprint $table)=>$table->dropColumn(['teacher_record_source','teacher_planned_grades']));
    }
};
