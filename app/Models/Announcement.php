<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    public const CATEGORIES = [
        'general' => 'General',
        'academic' => 'Academic',
        'activities' => 'Activities',
        'events' => 'Events',
    ];

    protected $guarded = [];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['published_at' => 'date'];
    }
}
