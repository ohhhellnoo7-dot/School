<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    protected $guarded = [];
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
}
