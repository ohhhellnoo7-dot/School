<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->change();
            $table->foreignId('school_class_id')->nullable()->after('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->after('school_class_id')->constrained('users')->nullOnDelete();
            $table->string('entry_type', 20)->default('academic')->after('teacher_id');
            $table->string('title_en')->nullable()->after('entry_type');
            $table->string('title_km')->nullable()->after('title_en');
            $table->timestampTz('generated_at')->nullable()->after('room');
        });

        // Backfill rather than recreate schedules so every existing timetable row survives.
        DB::table('schedules')->orderBy('id')->chunkById(200, function ($schedules) {
            $subjects = DB::table('subjects')
                ->whereIn('id', $schedules->pluck('subject_id')->filter()->unique())
                ->get(['id', 'school_class_id', 'teacher_id'])
                ->keyBy('id');

            foreach ($schedules as $schedule) {
                $subject = $subjects->get($schedule->subject_id);
                if ($subject) {
                    DB::table('schedules')->where('id', $schedule->id)->update([
                        'school_class_id' => $subject->school_class_id,
                        'teacher_id' => $subject->teacher_id,
                    ]);
                }
            }
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->index(['school_class_id', 'day', 'starts_at'], 'schedules_class_slot_index');
            $table->index(['teacher_id', 'day', 'starts_at'], 'schedules_teacher_slot_index');
            $table->index(['room', 'day', 'starts_at'], 'schedules_room_slot_index');
            $table->unique(['school_class_id', 'day', 'starts_at'], 'schedules_class_slot_unique');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE schedules ADD CONSTRAINT schedules_entry_integrity_check CHECK ((entry_type = 'academic' AND subject_id IS NOT NULL) OR (entry_type = 'relaxation' AND subject_id IS NULL AND teacher_id IS NULL))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS schedules_entry_integrity_check');
        }

        // The original schema cannot represent subject-less relaxation rows.
        DB::table('schedules')->whereNull('subject_id')->delete();

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropUnique('schedules_class_slot_unique');
            $table->dropIndex('schedules_class_slot_index');
            $table->dropIndex('schedules_teacher_slot_index');
            $table->dropIndex('schedules_room_slot_index');
            $table->dropConstrainedForeignId('school_class_id');
            $table->dropConstrainedForeignId('teacher_id');
            $table->dropColumn(['entry_type', 'title_en', 'title_km', 'generated_at']);
            $table->foreignId('subject_id')->nullable(false)->change();
        });
    }
};
