<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('school_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('school_location_check_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_attendance_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('distance_meters');
            $table->unsignedInteger('accuracy_meters');
            $table->timestamps();
            $table->unique('teacher_attendance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_location_check_ins');
        Schema::dropIfExists('school_locations');
    }
};
