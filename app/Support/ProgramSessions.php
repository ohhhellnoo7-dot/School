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
        return \App\Models\Program::sessionMap()[$program] ?? ['Morning', 'Afternoon', 'Evening'];
    }
}
