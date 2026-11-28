<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeacherWeekendSalary
{
    public function settingsFor(array $teacherIds, Carbon $month): Collection
    {
        $effectiveMonth = $month->copy()->startOfMonth()->toDateString();

        return DB::table('teacher_weekend_salary_settings as settings')
            ->whereIn('settings.teacher_id', $teacherIds)
            ->where('settings.effective_month', '<=', $effectiveMonth)
            ->whereNotExists(function ($query) use ($effectiveMonth) {
                $query->selectRaw('1')->from('teacher_weekend_salary_settings as newer')
                    ->whereColumn('newer.teacher_id', 'settings.teacher_id')
                    ->where('newer.effective_month', '<=', $effectiveMonth)
                    ->where(fn ($query) => $query->whereColumn('newer.effective_month', '>', 'settings.effective_month')
                        ->orWhere(fn ($query) => $query->whereColumn('newer.effective_month', 'settings.effective_month')
                            ->whereColumn('newer.id', '>', 'settings.id')));
            })->get()->keyBy('teacher_id');
    }

    public function approve(int $teacherId, int $approverId, int $hourlyCents, int $dailyMinutes): void
    {
        DB::table('teacher_weekend_salary_settings')->insert([
            'teacher_id' => $teacherId,
            'approved_by' => $approverId,
            'effective_month' => today()->startOfMonth()->toDateString(),
            'hourly_cents' => $hourlyCents,
            'daily_minutes' => $dailyMinutes,
            'created_at' => now(),
        ]);
    }
}
