<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolLocation extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'radius_meters' => 'integer'];

    public const LEGACY_TEST_NAME = 'administrator current location (test)';

    public function scopeAttendanceEligible($query)
    {
        return $query->whereRaw('LOWER(TRIM(name)) <> ?', [self::LEGACY_TEST_NAME]);
    }

    public function isLegacyTest(): bool
    {
        return strtolower(trim($this->name)) === self::LEGACY_TEST_NAME;
    }
}
