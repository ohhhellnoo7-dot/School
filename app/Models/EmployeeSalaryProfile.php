<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['service_started_on' => 'date', 'day_sessions' => 'integer', 'evening_sessions' => 'integer'];
    }
}
