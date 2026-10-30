<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ClassroomBooking::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function scopeOperational(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->operational();
    }
}
