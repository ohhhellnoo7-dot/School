<?php

namespace App\Support;

class AcademicYear
{
    public static function current(): string
    {
        $year = now(config('classrooms.timezone'))->year;

        return $year.'-'.($year + 1);
    }

    /** Unknown legacy years remain a commitment until explicitly resolved. */
    public static function overlaps(?string $a, ?string $b): bool
    {
        return ! $a || ! $b || $a === $b;
    }
}
