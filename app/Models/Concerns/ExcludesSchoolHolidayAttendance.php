<?php

namespace App\Models\Concerns;

use App\Services\SchoolHolidayClosure;
use Illuminate\Database\Eloquent\Builder;

trait ExcludesSchoolHolidayAttendance
{
    protected static function bootExcludesSchoolHolidayAttendance(): void
    {
        static::addGlobalScope('school-holiday', function (Builder $query): void {
            $dates = app(SchoolHolidayClosure::class)->calendarDates();
            if ($dates === []) {
                return;
            }

            $connection = $query->getQuery()->getConnection();
            $date = $query->qualifyColumn('date');
            if ($connection->getDriverName() === 'sqlite') {
                // SQLite also stores date casts as timestamps.
                $column = $connection->getQueryGrammar()->wrap($date);
                $date = $connection->raw("date({$column})");
            }

            $closed = $connection->table('school_holiday_settings')
                ->where('id', 1)->where('enabled', true)->whereIn($date, $dates);

            $query->whereNotExists($closed);
        });
    }
}

