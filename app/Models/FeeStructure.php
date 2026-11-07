<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['charges' => 'array', 'monthly_tuition' => 'decimal:2', 'discount' => 'decimal:2', 'starts_on' => 'date', 'effective_from' => 'date', 'effective_until' => 'date'];
    }

    public function getAnnualTuitionAttribute(): string
    {
        return Money::decimal(Money::cents($this->monthly_tuition) * $this->billable_months);
    }
}
