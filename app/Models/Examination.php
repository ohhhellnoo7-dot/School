<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Examination extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
