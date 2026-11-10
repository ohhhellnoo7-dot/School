<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ClassGroup extends Model
{
    public const PROGRAMS = ['Khmer', 'English', 'Chinese'];

    public const STREAMS = ['science' => 'Science Stream', 'social_science' => 'Social Science Stream'];

    public const STATUSES = ['active', 'inactive'];

    public static function assignedStream(?string $program, int $grade, ?string $section): ?string
    {
        if ($program !== 'Khmer' || ! in_array($grade, [11, 12], true)) return null;
        return match (strtoupper(trim($section ?? ''))) {
            'A', 'B', 'C' => 'social_science',
            'D', 'E' => 'science',
            default => null,
        };
    }

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (ClassGroup $group) {
            $group->name = trim((string) $group->name);
            $group->program = trim((string) $group->program);
            $group->section = filled($group->section)
                ? strtoupper(trim((string) $group->section))
                : null;
            $assigned = self::assignedStream($group->program, (int) $group->grade, $group->section);
            if ($assigned && blank($group->stream)) $group->stream = $assigned;
        });
    }

    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'capacity' => 'integer',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class)->orderBy('starts_at');
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class)->orderBy('starts_at');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ClassroomBooking::class);
    }

    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function legacyClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function students(): HasManyThrough
    {
        return $this->hasManyThrough(
            Student::class,
            StudentClassEnrollment::class,
            'class_group_id',
            'id',
            'id',
            'student_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForProgram(Builder $query, string $program): Builder
    {
        return $query->where('program', $program);
    }

    /** @return list<string> */
    public static function allowedSessions(string $program): array
    {
        return \App\Support\ProgramSessions::for($program);
    }

    public function getDisplayNameAttribute(): string
    {
        $label = trim((string) $this->name);
        $section = filled($this->section) ? strtoupper(trim((string) $this->section)) : '';

        if ($section !== '' && ! preg_match('/'.preg_quote($section, '/').'$/i', $label)) {
            $label .= $section;
        }

        if (! str_starts_with(mb_strtolower($label), mb_strtolower($this->program))) {
            $label = trim($this->program.' '.$label);
        }

        return $label.(isset(self::STREAMS[$this->stream]) ? ' - '.self::STREAMS[$this->stream] : '');
    }
}
