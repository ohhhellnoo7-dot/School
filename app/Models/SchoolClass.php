<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    protected $guarded = [];

    public const SESSION_HOURS = ['Morning' => ['07:00', '11:00'], 'Afternoon' => ['13:00', '17:00'], 'Evening' => ['16:30', '18:30']];

    public function sessionHours(): array
    {
        $session = ClassSession::where('class_group_id',$this->class_group_id)->where('session',$this->session)->first();
        if ($session) return [substr($session->starts_at,0,5),substr($session->ends_at,0,5)];
        return $this->program === 'Chinese' && $this->session === 'Evening' ? ['17:30','19:30'] : (self::SESSION_HOURS[$this->session] ?? ['00:00','00:00']);
    }

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
