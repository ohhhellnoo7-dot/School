<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('school_location_check_ins', function (Blueprint $table) {
            $table->dropUnique(['teacher_attendance_id']);
            $table->foreignId('teacher_session_attendance_id')->nullable()->unique()
                ->constrained('teacher_session_attendances')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Multiple session check-ins cannot safely be collapsed into one daily row.
        Schema::table('school_location_check_ins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('teacher_session_attendance_id');
        });
    }
};
