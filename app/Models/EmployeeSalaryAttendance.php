<?php

namespace App\Models;

use App\Models\Concerns\ExcludesSchoolHolidayAttendance;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalaryAttendance extends Model
{
    use ExcludesSchoolHolidayAttendance;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
