<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TeacherExcuse extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date', 'reviewed_at' => 'datetime'];
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start);
    }
}
