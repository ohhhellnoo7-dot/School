<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fee extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'paid_at' => 'date', 'amount' => 'decimal:2', 'paid' => 'decimal:2', 'opening_paid' => 'decimal:2'];
    }

    public function getBalanceAttribute(): string
    {
        return Money::decimal($this->balanceCents());
    }

    public function payments()
    {
        return $this->hasMany(FeePayment::class);
    }

    public function adjustments()
    {
        return $this->hasMany(FinancialAdjustment::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function netCents(): int
    {
        $net = Money::cents($this->amount);
        foreach ($this->adjustments as $a) {
            if ($a->kind === 'charge') {
                $net += Money::cents($a->amount);
            } elseif (in_array($a->kind, ['discount', 'waiver', 'credit'])) {
                $net -= Money::cents($a->amount);
            }
        }

        return $net;
    }

    public function balanceCents(): int
    {
        return $this->netCents() - Money::cents($this->paid);
    }

    public function getDisplayNumberAttribute(): string
    {
        return $this->invoice_number ?: 'LEGACY-'.$this->id;
    }

    public function getPaymentStatusAttribute(): string
    {
        if ($this->netCents() === 0 && ($this->adjustments->contains('kind', 'waiver') || $this->items->contains(fn ($item) => Money::cents($item->amount) < 0))) {
            return 'waived';
        }
        if ($this->balanceCents() <= 0) {
            return 'paid';
        }
        if (Money::cents($this->paid) === 0 && $this->adjustments->contains('kind', 'refund')) {
            return 'refunded';
        }
        if ($this->due_date->lt(today())) {
            return 'overdue';
        }

        return Money::cents($this->paid) > 0 ? 'partially_paid' : 'unpaid';
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
