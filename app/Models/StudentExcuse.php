<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Carbon\CarbonImmutable;

class StudentExcuse extends Model
{
    public const RANDOM_SESSION = 'Random attendance';

    public const RANDOM_REASONS = [
        'Feeling unwell',
        'Family obligation',
        'Visiting hometown',
        'Personal appointment',
        'Transportation issues',
        'Helping family at home',
    ];

    public function displayReason(): string
    {
        if ($this->session === self::RANDOM_SESSION && $this->reason === 'Automatically marked excused by Random attendance.') {
            return self::RANDOM_REASONS[$this->id % count(self::RANDOM_REASONS)];
        }

        return $this->reason;
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_on' => 'immutable_date', 'ends_on' => 'immutable_date'];
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function setStartsOnAttribute($value): void
    {
        $this->attributes['starts_on'] = CarbonImmutable::parse($value)->toDateString();
    }

    public function setEndsOnAttribute($value): void
    {
        $this->attributes['ends_on'] = CarbonImmutable::parse($value)->toDateString();
    }

    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query->where('starts_on', '<=', $end)->where('ends_on', '>=', $start);
    }

    public function scopeSubmittedToday(Builder $query): Builder
    {
        $day = today();

        return $query->visibleInReport()->where('created_at', '>=', $day)->where('created_at', '<', $day->copy()->addDay());
    }

    public function scopeVisibleInReport(Builder $query): Builder
    {
        return $query->where(fn ($excuses) => $excuses
            ->where('session', '!=', self::RANDOM_SESSION)
            ->orWhereExists(Attendance::query()->selectRaw('1')
                ->whereColumn('attendances.student_id', 'student_excuses.student_id')
                ->whereColumn('attendances.date', 'student_excuses.starts_on')
                ->where('attendances.status', 'excused')->toBase()));
    }
}
