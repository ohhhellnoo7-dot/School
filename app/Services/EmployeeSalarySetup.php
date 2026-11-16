<?php

namespace App\Services;

use App\Models\{EmployeeSalaryProfile, User};
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeSalarySetup
{
    public function plan(Carbon $baseline): Collection
    {
        $existing = EmployeeSalaryProfile::pluck('teacher_id');
        $teachers = User::where('role', 'teacher')->whereNotIn('id', $existing)->orderBy('id')->get();
        $duties = DB::table('teacher_working_periods')->whereIn('teacher_id', $teachers->modelKeys())
            ->where('unavailable', false)->get()->groupBy('teacher_id');

        return $teachers->map(function ($teacher) use ($duties, $baseline) {
            $sessions = $this->sessions($duties->get($teacher->id, collect()));

            return ['teacher_id' => $teacher->id, 'day_sessions' => $sessions['day'], 'evening_sessions' => $sessions['evening'],
                'service_started_on' => $baseline->copy()->startOfMonth()->toDateString(),
                'start_date_source' => 'salary_cycle_start',
                'session_source' => 'teacher_working_periods', 'eligible' => $sessions['day'] + $sessions['evening'] > 0];
        });
    }

    public function sessions(Collection $duties): array
    {
        $day = 0;
        $evening = 0;
        foreach ($duties->groupBy('weekday') as $periods) {
            $bands = [];
            $eveningMinutes = [];
            foreach ($periods as $period) {
                if ($period->unavailable) continue;
                $start = TeacherManagementService::minute($period->start_time);
                $end = TeacherManagementService::minute($period->end_time);
                if ($start < 660 && $end > 420) $bands['Morning'] = true;
                if ($start < 1020 && $end > 780) $bands['Afternoon'] = true;
                // Count unique evening duty minutes, avoiding duplicate or overlapping periods.
                for ($minute = max($start, 1050); $minute < min($end, 1170); $minute++) {
                    $eveningMinutes[$minute] = true;
                }
            }
            $day = max($day, count($bands));
            $evening = max($evening, min(2, (int) ceil(count($eveningMinutes) / 60)));
        }

        return compact('day', 'evening');
    }

    public function apply(Collection $plan, User $actor): int
    {
        abort_unless($actor->role === 'administrator', 403);

        return DB::transaction(function () use ($plan, $actor) {
            $created = 0;
            foreach ($plan->where('eligible', true) as $row) {
                $teacher = User::where('role', 'teacher')->whereKey($row['teacher_id'])->lockForUpdate()->firstOrFail();
                $profile = EmployeeSalaryProfile::firstOrCreate(['teacher_id' => $teacher->id],
                    collect($row)->only(['day_sessions', 'evening_sessions', 'service_started_on'])->all() + ['updated_by' => $actor->id]);
                $created += (int) $profile->wasRecentlyCreated;
            }

            return $created;
        });
    }
}
