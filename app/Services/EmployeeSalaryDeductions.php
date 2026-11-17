<?php

namespace App\Services;

use App\Models\EmployeeSalaryProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EmployeeSalaryDeductions
{
    public function counts(Collection $daily, Collection $scans, Collection $overrides): Collection
    {
        $dates = collect($daily->all())->concat($scans)->concat($overrides)->map(fn ($r) => $r->date->toDateString())->unique();
        return $dates->map(function ($date) use ($daily, $scans, $overrides) {
            $record = $daily->first(fn ($r) => $r->date->toDateString() === $date);
            $marks = $overrides->filter(fn ($r) => $r->date->toDateString() === $date)->keyBy('session');
            $sessionRecords = $scans->filter(fn ($r) => $r->date->toDateString() === $date)->keyBy('session');
            $statuses = collect(['Morning', 'Afternoon', 'Evening'])->map(fn ($s) =>
                $marks->get($s)?->status ?? $marks->get('All')?->status ?? $record?->status ?? $sessionRecords->get($s)?->status);
            return collect(['absent', 'late', 'excused', 'present'])->first(fn ($s) => $statuses->contains($s));
        })->filter()->countBy();
    }

    public function apply(array $salary, EmployeeSalaryProfile $profile, Collection $daily, Collection $scans, Collection $overrides, Carbon $asOf): array
    {
        $days = collect($daily->all())->concat($scans)->concat($overrides)->map(fn ($r) => $r->date->toDateString())->unique()->sort();
        $details = [];
        $total = 0;
        foreach ($days as $date) {
            if ($date > $asOf->toDateString() || $date < $profile->service_started_on->toDateString()) continue;
            $day = $daily->first(fn ($r) => $r->date->toDateString() === $date);
            $custom = $overrides->filter(fn ($r) => $r->date->toDateString() === $date)->keyBy('session');
            $sessions = $scans->filter(fn ($r) => $r->date->toDateString() === $date)->keyBy('session');
            $statuses = [];
            foreach (['Morning', 'Afternoon', 'Evening'] as $session) {
                if ($session === 'Evening' && ! $profile->evening_sessions) continue;
                if ($session !== 'Evening' && ! $profile->day_sessions) continue;
                $statuses[$session] = $custom->get($session)?->status ?? $custom->get('All')?->status
                    ?? $day?->status ?? $sessions->get($session)?->status;
            }
            $absent = $profile->day_sessions === 2 ? count(array_filter([
                $statuses['Morning'] ?? null, $statuses['Afternoon'] ?? null,
            ], fn ($status) => $status === 'absent')) : 0;
            $absence = $absent === 2 ? 1500 : ($absent === 1 ? 1000 : 0);
            $late = in_array('late', $statuses, true) ? 100 : 0;
            $excused = in_array('excused', $statuses, true) ? 500 : 0;
            $amount = $absence + $late + $excused;
            if ($amount) {
                $details[] = ['date' => $date, 'statuses' => $statuses, 'absence_cents' => $absence,
                    'late_cents' => $late, 'excused_cents' => $excused, 'total_cents' => $amount];
                $total += $amount;
            }
        }

        $salary['gross_cents'] = $salary['total_cents'];
        $salary['deduction_cents'] = $total;
        $salary['deduction_details'] = $details;
        $salary['total_cents'] = max(0, $salary['gross_cents'] - $total);

        return $salary;
    }
}
