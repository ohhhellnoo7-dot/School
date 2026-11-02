<?php

namespace App\Models;

use App\Support\ProgramSessions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Program extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['sessions' => 'array'];
    }

    public static function sessionMap(): array
    {
        if (! Schema::hasTable('programs')) return ProgramSessions::BY_PROGRAM;

        return array_replace(ProgramSessions::BY_PROGRAM, static::orderBy('id')->get()->pluck('sessions', 'name')->all());
    }

    public static function names(): array
    {
        return array_keys(static::sessionMap());
    }
}
