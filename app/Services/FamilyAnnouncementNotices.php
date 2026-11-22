<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\ClassAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FamilyAnnouncementNotices
{
    public function holidays(CarbonImmutable $today): array
    {
        $days = [];
        foreach (config('khmer-holidays') as $year => $schedule) {
            foreach ($schedule as $monthDay => $holiday) {
                [$month, $day] = array_map('intval', explode('-', $monthDay));
                $date = CarbonImmutable::createMidnightDate($year, $month, $day, $today->timezone);
                $days[$date->toDateString()] = ['name' => $holiday, 'date' => $date];
            }
        }
        ksort($days);

        $periods = [];
        foreach ($days as ['name' => $name, 'date' => $date]) {
            $index = array_key_last($periods);
            $previous = $days[$date->subDay()->toDateString()] ?? null;
            if ($previous && $previous['name'] === $name) {
                $periods[$index]['end'] = $date;
            } else {
                $periods[] = ['name' => $name, 'start' => $date, 'end' => $date];
            }
        }

        return array_filter($periods, fn ($holiday) => $holiday['start']->lte($today->addDays(3))
            && $holiday['end']->gte($today));
    }

    public function attendance(Collection $children, CarbonImmutable $today): Collection
    {
        if ($today->isWeekend() || $children->isEmpty()) {
            return collect();
        }

        $ids = $children->pluck('id');
        $date = $today->toDateString();
        $daily = Attendance::whereIn('student_id', $ids)->whereDate('date', $date)
            ->whereIn('status', MonthlyStudentAttendance::STATUSES)->get()->keyBy('student_id');
        $classRecords = ClassAttendance::with('schoolClass.classGroup')
            ->whereIn('student_id', $ids->diff($daily->keys()))->whereDate('date', $date)
            ->whereIn('status', MonthlyStudentAttendance::STATUSES)->latest('updated_at')->latest('id')
            ->get()->unique('student_id')->keyBy('student_id');

        return $children->toBase()->map(function ($child) use ($daily, $classRecords) {
            $record = $daily->get($child->id) ?? $classRecords->get($child->id);

            return $record ? ['child' => $child, 'record' => $record] : null;
        })->filter();
    }
}
