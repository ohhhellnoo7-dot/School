<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryPayment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['salary_month' => 'date', 'service_started_on' => 'date', 'paid_on' => 'date',
            'base_cents' => 'integer', 'full_salary_cents' => 'integer', 'raise_cents' => 'integer', 'total_cents' => 'integer',
            'deduction_cents' => 'integer', 'deduction_details' => 'array'];
    }
}
