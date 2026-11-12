<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceSessionSetting extends Model
{
    protected $primaryKey = 'session';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['grace_minutes' => 'integer'];
}
