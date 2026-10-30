<?php

namespace App\Services;

use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;

class TimetableBackup
{
    public function create(): string
    {
        $tables = [];
        foreach (['users', 'students', 'class_groups', 'class_sessions', 'school_classes', 'classrooms', 'classroom_bookings', 'subjects', 'schedules', 'teacher_profiles', 'teacher_qualifications', 'teacher_working_periods', 'teacher_break_plans', 'teacher_breaks'] as $table) {
            $tables[$table] = DB::table($table)->get()->all();
        }
        $path = 'timetable-backups/'.now()->format('Ymd-His').'-'.Str::uuid().'.json';
        if (! Storage::disk('local')->put($path, json_encode(['created_at' => now()->toIso8601String(), 'tables' => $tables], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
            throw new \RuntimeException('Backup failed. No timetable changes were saved.');
        }
        return $path;
    }
}
