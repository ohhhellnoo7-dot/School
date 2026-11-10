<?php

namespace App\Services;

class ChineseEveningPeriod
{
    public static function matches(mixed $entry, ?array $period): bool
    {
        return ! $period || (substr((string) $entry->starts_at, 0, 5) < $period['ends_at']
            && substr((string) $entry->ends_at, 0, 5) > $period['starts_at']);
    }
}
