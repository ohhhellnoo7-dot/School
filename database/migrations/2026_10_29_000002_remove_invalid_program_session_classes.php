<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $classIds = DB::table('school_classes')
                ->where(fn ($query) => $query
                    ->where('program', 'Chinese')->whereIn('session', ['Morning', 'Afternoon']))
                ->orWhere(fn ($query) => $query
                    ->where('program', 'Khmer')->where('session', 'Evening'))
                ->orderBy('id')->lockForUpdate()->pluck('id');

            if ($classIds->isEmpty()) {
                return;
            }

            $studentCount = DB::table('students')->whereIn('school_class_id', $classIds)->count();
            $subjectIds = DB::table('subjects')->whereIn('school_class_id', $classIds)->pluck('id');
            $scheduleCount = DB::table('schedules')->whereIn('school_class_id', $classIds)
                ->orWhereIn('subject_id', $subjectIds)->count();
            $examCount = DB::table('examinations')->whereIn('subject_id', $subjectIds)->count();
            $assignmentCount = DB::table('assignments')->whereIn('subject_id', $subjectIds)->count();

            if ($studentCount || $subjectIds->isNotEmpty() || $scheduleCount || $examCount || $assignmentCount) {
                throw new RuntimeException(
                    "Cannot remove invalid program/session classes while they contain linked records: {$studentCount} students, "
                    .$subjectIds->count()." subjects, {$scheduleCount} schedules, {$examCount} examinations, "
                    ."and {$assignmentCount} assignments. Reassign those records first."
                );
            }

            DB::table('school_classes')->whereIn('id', $classIds)->delete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE school_classes
                ADD CONSTRAINT school_classes_program_session_check
                CHECK (
                    (program <> 'Chinese' OR COALESCE(session, '') = 'Evening')
                    AND
                    (program <> 'Khmer' OR COALESCE(session, '') IN ('', 'Morning', 'Afternoon'))
                )
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE school_classes DROP CONSTRAINT IF EXISTS school_classes_program_session_check');
        }

        // Removed empty class variants are not recreated on rollback.
    }
};
