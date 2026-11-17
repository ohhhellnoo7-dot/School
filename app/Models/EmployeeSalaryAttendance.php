<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryAttendance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
