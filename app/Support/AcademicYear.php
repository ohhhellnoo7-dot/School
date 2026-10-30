<?php

namespace App\Support;

class AcademicYear
{
    /** Unknown legacy years remain a commitment until explicitly resolved. */
    public static function overlaps(?string $a, ?string $b): bool
    {
        return ! $a || ! $b || $a === $b;
    }
}
