<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StudentExcuse extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start);
    }
}
