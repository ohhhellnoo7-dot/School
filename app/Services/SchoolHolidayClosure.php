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

    public function holiday(CarbonInterface|string $date): ?string
    {
        $day = is_string($date) ? CarbonImmutable::parse($date, config('app.timezone')) : $date;
        $holiday = config('khmer-holidays.'.$day->year.'.'.$day->format('n-j'));

        return $holiday && $this->enabled() ? __('khmer-calendar.holidays.'.$holiday) : null;
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
