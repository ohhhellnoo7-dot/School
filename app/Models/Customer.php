<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'tbl_customer';
    protected $primaryKey = 'cid';
    protected $fillable = ['name'];
    public $timestamps = false;
}
