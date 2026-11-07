<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function cents(string|int|float $value): int
    {
        if (! preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/', (string) $value, $m)) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount with at most two decimal places.']);
        }

        return ($m[1] === '-' ? -1 : 1) * ((int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0'));
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function display(int $cents): string
    {
        $parts = explode('.', self::decimal($cents));

        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $parts[0]).'.'.$parts[1];
    }
}
