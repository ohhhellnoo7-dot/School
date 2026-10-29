<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class ClassroomBooking extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (ClassroomBooking $booking) {
            $booking->starts_at = self::normalizeTime($booking->starts_at);
            $booking->ends_at = self::normalizeTime($booking->ends_at);
            $booking->starts_minute = self::toMinute($booking->starts_at);
            $booking->ends_minute = self::toMinute($booking->ends_at);
        });
    }

    protected function casts(): array
    {
        return [
            'starts_minute' => 'integer',
            'ends_minute' => 'integer',
        ];
    }

    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class);
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function scopeOnDay(Builder $query, string $day): Builder
    {
        return $query->where('day_of_week', $day);
    }

    public function scopeOverlapping(Builder $query, int $startsMinute, int $endsMinute): Builder
    {
        return $query
            ->where('starts_minute', '<', $endsMinute)
            ->where('ends_minute', '>', $startsMinute);
    }

    public function getDayAttribute(): string
    {
        return (string) $this->day_of_week;
    }

    public static function normalizeTime(DateTimeInterface|string|null $time): string
    {
        if ($time instanceof DateTimeInterface) {
            return $time->format('H:i');
        }

        $value = trim((string) $time);
        if (! preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/', $value)) {
            throw new InvalidArgumentException("Invalid classroom booking time [{$value}].");
        }

        return substr($value, 0, 5);
    }

    public static function toMinute(DateTimeInterface|string $time): int
    {
        $value = self::normalizeTime($time);

        return ((int) substr($value, 0, 2) * 60) + (int) substr($value, 3, 2);
    }
}
