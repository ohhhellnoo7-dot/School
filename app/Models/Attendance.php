<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
