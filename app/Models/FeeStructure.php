<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['charges' => 'array', 'session_prices' => 'array', 'quarterly_tuition' => 'decimal:2', 'monthly_tuition' => 'decimal:2', 'discount' => 'decimal:2', 'starts_on' => 'date', 'effective_from' => 'date', 'effective_until' => 'date'];
    }

    public function getAnnualTuitionAttribute(): string
    {
        return $this->tuitionForMonths($this->billable_months);
    }

    public function monthlyForSession(?string $session): string
    {
        return $this->session_prices[$session]['monthly'] ?? $this->monthly_tuition;
    }

    public function quarterlyForSession(?string $session): ?string
    {
        $row = $this->session_prices[$session] ?? [];

        return array_key_exists('quarterly', $row) ? $row['quarterly'] : $this->quarterly_tuition;
    }

    public function tuitionForMonths(int $months, ?string $session = null): string
    {
        $monthly = Money::cents($this->monthlyForSession($session));
        $quarterly = $this->quarterlyForSession($session);

        $duration = $this->packageMonths();

        return Money::decimal($quarterly !== null ? intdiv($months, $duration) * Money::cents($quarterly) + ($months % $duration) * $monthly : $months * $monthly);
    }

    public function packageMonths(): int
    {
        return (int) ($this->package_months ?: 3);
    }

    public function ratesForSessions(array $sessions): array
    {
        $rates = [];
        foreach ($sessions as $session) {
            $monthly = $this->monthlyForSession($session);
            $quarterly = $this->tuitionForMonths(3, $session);
            $key = $monthly.'|'.$quarterly;
            if (! isset($rates[$key])) {
                $rates[$key] = ['sessions' => [], 'monthly' => $monthly, 'quarterly' => $quarterly, 'six_month' => $this->tuitionForMonths(6, $session), 'annual' => $this->tuitionForMonths($this->billable_months, $session), 'package' => $this->quarterlyForSession($session) !== null];
            }
            $rates[$key]['sessions'][] = $session;
        }

        return array_values($rates);
    }
}
