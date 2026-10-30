<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subjects', fn (Blueprint $t) => $t->unsignedSmallInteger('lesson_minutes')->default(60));
        Schema::table('school_classes', fn (Blueprint $t) => $t->unsignedSmallInteger('weekly_relaxation_minutes')->default(180));
        Schema::table('schedules', function (Blueprint $t) {
            $t->foreignId('classroom_id')->nullable()->constrained()->restrictOnDelete();
            $t->timestamp('cancelled_at')->nullable()->index();
            $t->index(['classroom_id', 'day', 'starts_at', 'ends_at'], 'schedule_physical_room_time_index');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subjects ADD CONSTRAINT subject_lesson_duration CHECK (lesson_minutes IN (60,120))');
            DB::statement('ALTER TABLE school_classes ADD CONSTRAINT class_relaxation_minutes CHECK (weekly_relaxation_minutes BETWEEN 0 AND 1200 AND weekly_relaxation_minutes % 60 = 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE subjects DROP CONSTRAINT IF EXISTS subject_lesson_duration');
            DB::statement('ALTER TABLE school_classes DROP CONSTRAINT IF EXISTS class_relaxation_minutes');
        }
        Schema::table('schedules', function (Blueprint $t) {
            $t->dropIndex('schedule_physical_room_time_index');
            $t->dropConstrainedForeignId('classroom_id');
            $t->dropColumn('cancelled_at');
        });
        Schema::table('subjects', fn (Blueprint $t) => $t->dropColumn('lesson_minutes'));
        Schema::table('school_classes', fn (Blueprint $t) => $t->dropColumn('weekly_relaxation_minutes'));
    }
};
