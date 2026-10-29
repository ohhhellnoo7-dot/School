<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $guarded = [];

    public const SESSION_HOURS = ['Morning' => ['07:00', '11:00'], 'Afternoon' => ['13:00', '17:00'], 'Evening' => ['16:30', '18:30']];

    public function getLabelAttribute(): string
    {
        return collect([$this->name, $this->section, $this->program, $this->session])->filter()->join(' · ');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function classGroup()
    {
        return $this->belongsTo(ClassGroup::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
