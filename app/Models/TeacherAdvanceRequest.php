<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAdvanceRequest extends Model
{
    protected $fillable = ['teacher_id', 'amount_cents', 'request_key', 'school_expense_id', 'repayment_months'];

    protected $casts = ['amount_cents' => 'integer', 'cancelled_at' => 'datetime', 'rejected_at' => 'datetime', 'repayment_months' => 'integer'];

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('school_expense_id')->whereNull('cancelled_at')->whereNull('rejected_at');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(SchoolExpense::class, 'school_expense_id');
    }
}
