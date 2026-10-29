<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('schedules')->where('starts_at', '>=', '16:00')
            ->whereIn('subject_id', function ($query) {
                $query->select('subjects.id')->from('subjects')
                    ->join('users', 'subjects.teacher_id', '=', 'users.id')
                    ->where('users.role', 'teacher')
                    ->where(fn ($q) => $q->where('users.program', 'Khmer')->orWhereNull('users.program')->orWhere('users.program', ''));
            })->delete();
    }

    public function down(): void
    {
        // Removed lessons are not recreated when application code is rolled back.
    }
};
