<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Examination;
use App\Models\Grade;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SchoolDependencies
{
    public static function hasDependents(Model $record): bool
    {
        return match (true) {
            $record instanceof Student => $record->attendances()->exists() || $record->grades()->exists() || $record->fees()->exists(),
            $record instanceof User => $record->children()->exists() || $record->subjects()->exists() || $record->classes()->exists()
                || Student::where('user_id', $record->id)->exists() || Announcement::where('created_by', $record->id)->exists(),
            $record instanceof SchoolClass => $record->students()->exists() || $record->subjects()->exists(),
            $record instanceof Subject => Examination::where('subject_id', $record->id)->exists()
                || Assignment::where('subject_id', $record->id)->exists() || Schedule::where('subject_id', $record->id)->exists(),
            $record instanceof Examination => Grade::where('examination_id', $record->id)->exists(),
            default => false,
        };
    }
}
