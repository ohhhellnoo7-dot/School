<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BulkPaymentRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'anchor_on' => 'date', 'next_on' => 'date', 'totals' => 'array'];
    }
}
