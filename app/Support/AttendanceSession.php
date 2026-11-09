<?php

namespace App\Support;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceSession
{
    public const OPTIONS = ['Morning', 'Afternoon', 'Evening'];

    public static function selected(Request $request, string $key = 'session'): ?string
    {
        return $request->validate([$key => ['nullable', Rule::in(self::OPTIONS)]])[$key] ?? null;
    }

    public static function students(Builder $query, ?string $session): Builder
    {
        return $query->when($session, fn ($q) => $q->whereHas('studyClasses', fn ($classes) => $classes->where('session', $session)));
    }

    public static function teachers(Builder $query, ?string $session): Builder
    {
        return $query->when($session, fn ($q) => $q->where(function ($teachers) use ($session) {
            $teachers->whereHas('classes', fn ($classes) => $classes->where('session', $session))
                ->orWhereHas('subjects.schoolClass', fn ($classes) => $classes->where('session', $session))
                ->orWhereIn('users.id', Schedule::whereHas('schoolClass', fn ($classes) => $classes->where('session', $session))->select('teacher_id'));
        }));
    }
}
