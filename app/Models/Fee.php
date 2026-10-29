<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fee extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'paid_at' => 'date', 'amount' => 'decimal:2', 'paid' => 'decimal:2'];
    }

    public function getBalanceAttribute(): string
    {
        return number_format((float) $this->amount - (float) $this->paid, 2, '.', '');
    }

    public function getPaymentStatusAttribute(): string
    {
        if ((float) $this->paid >= (float) $this->amount) {
            return 'paid';
        }
        if ($this->due_date->lt(today())) {
            return 'overdue';
        }

        return (float) $this->paid > 0 ? 'partially_paid' : 'pending';
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
