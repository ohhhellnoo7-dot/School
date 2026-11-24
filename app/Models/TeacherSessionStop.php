<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherSessionStop extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['effective_on' => 'date', 'applied_at' => 'datetime', 'snapshot' => 'array'];
    }
}
