<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherSessionAttendance extends Model
{
    protected $guarded = [];

    public function setDateAttribute($value): void
    {
        $this->attributes['date'] = \Carbon\Carbon::parse($value)->toDateString();
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'scanned_at' => 'datetime', 'scheduled_start' => 'datetime', 'scheduled_end' => 'datetime', 'checked_out_at' => 'datetime'];
    }
}
