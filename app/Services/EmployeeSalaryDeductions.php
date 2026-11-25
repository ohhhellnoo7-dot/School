<?php

namespace App\Services;

use App\Models\EmployeeSalaryPayment;
use App\Models\EmployeeSalaryProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EmployeeSalaryDeductions
{
    public function counts(Collection $daily, Collection $scans, Collection $overrides): Collection
    {
        return $this->attendanceStatuses($daily, $scans, $overrides)
            ->map(fn ($statuses) => collect(['absent', 'late', 'excused', 'present'])->first(fn ($status) => $statuses->contains($status)))
            ->filter()->countBy();
    }

    public function slipCounts(Collection $daily, Collection $scans, Collection $overrides, EmployeeSalaryProfile|EmployeeSalaryPayment|null $settings): Collection
    {
        $assigned = ['Morning', 'Afternoon', 'Evening'];
        if ($settings && $settings->day_sessions + $settings->evening_sessions > 0) {
            $assigned = match ($settings->day_sessions) {
                2 => ['Morning', 'Afternoon'],
                1 => [app(EmployeeSalaryCalculator::class)->singleDaySession($settings->teacher_id)],
                default => [],
            };
            if ($settings->evening_sessions) {
                $assigned[] = 'Evening';
            }
        }

        return $this->attendanceStatuses($daily, $scans, $overrides)
            ->map(function (Collection $statuses) use ($assigned) {
                $statuses = $statuses->only($assigned);
                $absences = $statuses->filter(fn ($status) => $status === 'absent')->count();
                if ($absences) {
                    return $absences === count($assigned) ? 'absent_full' : 'absent_partial';
                }

                return collect(['late', 'excused', 'present'])->first(fn ($status) => $statuses->contains($status));
            })->filter()->countBy();
    }

    public function usesLeaveAllowance(Collection $daily, Collection $scans, Collection $overrides): bool
    {
        return $this->attendanceStatuses($daily, $scans, $overrides)
            ->contains(fn ($statuses) => $statuses->contains('absent') || $statuses->contains('excused'));
    }

    private function attendanceStatuses(Collection $daily, Collection $scans, Collection $overrides): Collection
    {
        $dates = collect($daily->all())->concat($scans)->concat($overrides)->map(fn ($r) => $r->date->toDateString())->unique();
        return $dates->map(function ($date) use ($daily, $scans, $overrides) {
            $record = $daily->first(fn ($r) => $r->date->toDateString() === $date);
            $marks = $overrides->filter(fn ($r) => $r->date->toDateString() === $date)->keyBy('session');
            $sessionRecords = $scans->filter(fn ($r) => $r->date->toDateString() === $date)->keyBy('session');
            return collect(['Morning', 'Afternoon', 'Evening'])->mapWithKeys(fn ($s) => [
                $s => $marks->get($s)?->status ?? $marks->get('All')?->status ?? $record?->status ?? $sessionRecords->get($s)?->status,
            ]);
        });
    }

    public function apply(array $salary, EmployeeSalaryProfile $profile, Collection $daily, Collection $scans, Collection $overrides, Carbon $asOf): array
    {
        $rates = app(EmployeeSalaryRates::class)->amounts();
        $singleSession = null;
        if ($rates['absence_single_session'] > 0 && $profile->day_sessions + $profile->evening_sessions === 1) {
            $singleSession = $profile->evening_sessions
                ? 'Evening'
                : app(EmployeeSalaryCalculator::class)->singleDaySession($profile->teacher_id);
        }
        $days = collect($daily->all())->concat($scans)->concat($overrides)->map(fn ($r) => $r->date->toDateString())->unique()->sort();
        $details = [];
        $total = 0;
        $freeExcuseUsed = [];
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
            $absence = $absent === 2 ? $rates['absence_two'] : ($absent === 1 ? $rates['absence_one'] : 0);
            if ($singleSession !== null && ($statuses[$singleSession] ?? null) === 'absent') {
                $absence = $rates['absence_single_session'];
            }
            $late = in_array('late', $statuses, true) ? $rates['late'] : 0;
            $hasExcuse = in_array('excused', $statuses, true);
            $month = substr($date, 0, 7);
            $freeExcuse = $hasExcuse && !isset($freeExcuseUsed[$month]);
            if ($hasExcuse) {
                $freeExcuseUsed[$month] = true;
            }
            $excused = $hasExcuse && !$freeExcuse ? $rates['excused'] : 0;
            $amount = $absence + $late + $excused;
            if ($amount || $freeExcuse) {
                $details[] = ['date' => $date, 'statuses' => $statuses, 'absence_cents' => $absence,
                    'late_cents' => $late, 'excused_cents' => $excused, 'total_cents' => $amount, 'free_excuse' => $freeExcuse];
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
