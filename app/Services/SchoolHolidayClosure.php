<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SchoolHolidayClosure
{
    public function enabled(): bool
    {
        return (bool) DB::table('school_holiday_settings')->where('id', 1)->value('enabled');
    }

    public function setEnabled(bool $enabled): void
    {
        DB::table('school_holiday_settings')->where('id', 1)->update(['enabled' => $enabled]);
    }

    public function calendarDates(): array
    {
        $dates = [];
        foreach (config('khmer-holidays', []) as $year => $schedule) {
            foreach (array_keys($schedule) as $monthDay) {
                [$month, $day] = explode('-', $monthDay);
                $dates[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        return $dates;
    }

    public function holiday(CarbonInterface|string $date): ?string
    {
        $holiday = $this->calendarHoliday($date);

        return $holiday && $this->enabled() ? $holiday : null;
    }

    public function calendarHoliday(CarbonInterface|string $date): ?string
    {
        $holiday = $this->calendarHolidayKey($date);

        return $holiday ? __('khmer-calendar.holidays.'.$holiday) : null;
    }

    public function calendarHolidayKey(CarbonInterface|string $date): ?string
    {
        $day = is_string($date) ? CarbonImmutable::parse($date, config('app.timezone')) : $date;

        return config('khmer-holidays.'.$day->year.'.'.$day->format('n-j'));
    }

    public function ensureOpen(CarbonInterface|string $date): void
    {
        if ($holiday = $this->holiday($date)) {
            throw ValidationException::withMessages(['date' => $this->message($holiday)]);
        }
    }

    public function message(string $holiday): string
    {
        return __('School closed for :holiday. No classes or attendance for students and teachers.', ['holiday' => $holiday]);
    }
}
