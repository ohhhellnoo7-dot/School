<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    public const NAMES = ['Morning', 'Afternoon', 'Evening'];

    protected $guarded = [];

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ClassroomBooking::class);
    }

    public function getNameAttribute(): string
    {
        return (string) $this->session;
    }

    public function getLabelAttribute(): string
    {
        return sprintf(
            '%s %s-%s',
            $this->session,
            substr((string) $this->starts_at, 0, 5),
            substr((string) $this->ends_at, 0, 5)
        );
    }
}
