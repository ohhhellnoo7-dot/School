<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
