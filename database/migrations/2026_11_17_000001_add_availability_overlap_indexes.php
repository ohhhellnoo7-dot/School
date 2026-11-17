<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->index(['day', 'starts_at', 'ends_at'], 'schedules_day_overlap_index');
        });
        Schema::table('classroom_bookings', function (Blueprint $table) {
            $table->index(['day_of_week', 'starts_at', 'ends_at'], 'bookings_day_overlap_index');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', fn (Blueprint $table) => $table->dropIndex('schedules_day_overlap_index'));
        Schema::table('classroom_bookings', fn (Blueprint $table) => $table->dropIndex('bookings_day_overlap_index'));
    }
};
