<?php

namespace App\Support;

class ProgramSessions
{
    public const BY_PROGRAM = [
        'Khmer' => ['Morning', 'Afternoon'],
        'English' => ['Morning', 'Afternoon', 'Evening'],
        'Chinese' => ['Evening'],
    ];

    public static function for(?string $program): array
    {
        return self::BY_PROGRAM[$program] ?? ['Morning', 'Afternoon', 'Evening'];
    }
}
