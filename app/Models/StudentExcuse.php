<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Carbon\CarbonImmutable;

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

    public function setStartsOnAttribute($value): void
    {
        $this->attributes['starts_on'] = CarbonImmutable::parse($value)->toDateString();
    }

    public function setEndsOnAttribute($value): void
    {
        $this->attributes['ends_on'] = CarbonImmutable::parse($value)->toDateString();
    }

    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query->where('starts_on', '<=', $end)->where('ends_on', '>=', $start);
    }
}
