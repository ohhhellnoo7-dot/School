<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class EmployeeSalaryRates
{
    public const DEFAULTS = [
        'morning' => 12500,
        'afternoon' => 12500,
        'evening' => 3125,
        'late' => 100,
        'excused' => 500,
        'absence_one' => 1000,
        'absence_two' => 1500,
    ];

    private ?array $rates = null;

    public function amounts(): array
    {
        return $this->rates ??= array_replace(self::DEFAULTS, json_decode(
            DB::table('teacher_management_settings')->where('id', 1)->value('salary_rates') ?? '{}',
            true,
            flags: JSON_THROW_ON_ERROR,
        ));
    }

    public function save(array $rates): void
    {
        DB::table('teacher_management_settings')->where('id', 1)->update([
            'salary_rates' => json_encode($rates, JSON_THROW_ON_ERROR),
        ]);
        $this->rates = $rates;
    }
}
