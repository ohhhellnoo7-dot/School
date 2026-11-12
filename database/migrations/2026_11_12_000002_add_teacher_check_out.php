<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendance_session_settings', fn (Blueprint $table) => $table->time('ends_at')->nullable());
        Schema::table('teacher_session_attendances', function (Blueprint $table) {
            $table->timestamp('scheduled_end')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->unsignedInteger('checkout_distance_meters')->nullable();
            $table->unsignedInteger('checkout_accuracy_meters')->nullable();
            $table->foreignId('checkout_location_id')->nullable()->constrained('school_locations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teacher_session_attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checkout_location_id');
            $table->dropColumn(['scheduled_end', 'checked_out_at', 'checkout_distance_meters', 'checkout_accuracy_meters']);
        });
        Schema::table('attendance_session_settings', fn (Blueprint $table) => $table->dropColumn('ends_at'));
    }
};
