<?php

namespace App\Services;

use App\Models\EmployeeSalaryProfile;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class EmployeeAttendanceBonus
{
    public const DEFAULT_SERVICE_MONTHS = 6;

    public function requiredServiceMonths(): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('teacher_management_settings')->where('id', 1)->value('bonus_service_months');
    }

    public function timetables(array $teacherIds, int $year): Collection
    {
        return Schedule::with('schoolClass')->whereIn('teacher_id', $teacherIds)
            ->forAcademicYear($year.'-'.($year + 1))
            ->where(fn ($query) => $query->whereNull('entry_type')->orWhere('entry_type', Schedule::TYPE_ACADEMIC))
            ->get()->filter(fn ($lesson) => $lesson->schoolClass?->session)
            ->groupBy('teacher_id');
    }

    public function closedDates(SchoolHolidayClosure $closure): array
    {
        return $closure->enabled() ? array_fill_keys($closure->calendarDates(), true) : [];
    }

    public function forYear(
        int $teacherId,
        Carbon $selectedMonth,
        ?EmployeeSalaryProfile $profile,
        Collection $payments,
        Collection $lessons,
        Collection $daily,
        Collection $scans,
        Collection $overrides,
        array $closedDates,
    ): array {
        $byDate = fn ($record) => $record->date->toDateString();
        $daily = $daily->keyBy($byDate);
        $scans = $scans->groupBy($byDate)->map->keyBy('session');
        $overrides = $overrides->groupBy($byDate)->map->keyBy('session');
        $lessons = $lessons->groupBy('day')->map(fn ($dayLessons) => $dayLessons->pluck('schoolClass.session')->unique());
        $today = today();
        $monthly = [];
        $pendingAmount = null;
        foreach (range(1, 12) as $number) {
            $month = $selectedMonth->copy()->month($number)->startOfMonth();
            $end = $month->copy()->endOfMonth();
            $settings = $payments->get($number) ?? $profile;
            if (!$settings || $month->gt($today) || $lessons->isEmpty()) {
                $monthly[$number] = null;
                continue;
            }

            $workDays = 0;
            $workedSessions = collect();
            $qualifies = true;
            $missing = false;
            $verifiedSessions = 0;
            $open = $end->gte($today);
            for ($day = $month->copy(); $day->lte($end); $day->addDay()) {
                $date = $day->toDateString();
                $required = $lessons->get($day->englishDayOfWeek, collect());
                if (isset($closedDates[$date]) || $required->isEmpty()) continue;
                if ($day->lt($settings->service_started_on)) {
                    $qualifies = false;
                    break;
                }
                $workDays++;
                $workedSessions = $workedSessions->merge($required)->unique();
                if ($day->gt($today)) continue;
                $dayRecord = $daily->get($date);
                $manualStatus = $dayRecord && (int) $dayRecord->recorded_by !== $teacherId ? $dayRecord->status : null;
                foreach ($required as $session) {
                    $status = $overrides->get($date)?->get($session)?->status
                        ?? $overrides->get($date)?->get('All')?->status
                        ?? $manualStatus
                        ?? $scans->get($date)?->get($session)?->status;
                    if (in_array($status, ['absent', 'excused'], true)) {
                        $qualifies = false;
                        break 2;
                    }
                    if (in_array($status, ['present', 'late'], true)) {
                        $verifiedSessions++;
                    } elseif ($day->lt($today)) {
                        $missing = true;
                    }
                }
            }

            $daySessions = $workedSessions->intersect(['Morning', 'Afternoon'])->count();
            $coversWork = $daySessions >= $settings->day_sessions
                && (!$settings->evening_sessions || $workedSessions->contains('Evening'));
            $amount = $this->monthlyRate($settings->day_sessions, $settings->evening_sessions);
            if ($open && $qualifies) {
                $monthly[$number] = null;
                if ($number === $selectedMonth->month && !$missing && $verifiedSessions && $coversWork) {
                    $pendingAmount = $amount;
                }
            } else {
                $monthly[$number] = $qualifies && !$missing && $workDays && $coversWork ? $amount : 0;
            }
        }

        $settings = $payments->get($selectedMonth->month) ?? $profile;

        return [
            'monthly' => $monthly,
            'month' => $monthly[$selectedMonth->month],
            'year' => array_sum($monthly),
            'qualifyingMonths' => array_keys(array_filter($monthly, fn ($amount) => $amount > 0)),
            'pending' => $pendingAmount,
            'annualMaximum' => $settings ? 12 * $this->monthlyRate($settings->day_sessions, $settings->evening_sessions) : null,
        ];
    }

    private function monthlyRate(int $daySessions, int $eveningSessions): int
    {
        return 1000 * $daySessions + 250 * $eveningSessions;
    }
}
