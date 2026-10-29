<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'enrollment_date' => 'date'];
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->user?->profile_photo_url
            ?? ($this->photo ? route('students.photo', $this) : null);
    }

    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    public function getAttendancePercentageAttribute(): float
    {
        $total = $this->attendances()->count();

        return $total ? round($this->attendances()->whereIn('status', ['present', 'late'])->count() * 100 / $total, 1) : 0;
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }

    public function fees()
    {
        return $this->hasMany(Fee::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');
        // A name prefix must not match unrelated letters in an email or student ID.
        if (preg_match('/^[\p{L}\p{M}]+$/u', $search)) {
            $prefix = mb_strtolower($search).'%';

            return $query->where(function (Builder $match) use ($prefix) {
                $match->whereRaw('LOWER(students.name) LIKE ?', [$prefix])
                    ->orWhereHas('user', fn (Builder $account) => $account->whereRaw('LOWER(users.name) LIKE ?', [$prefix]));
            });
        }
        $terms = preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($terms as $term) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
            $query->where(function (Builder $match) use ($pattern) {
                $match->whereRaw("LOWER(students.name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(students.student_number) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(students.email) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('user', function (Builder $account) use ($pattern) {
                        $account->whereRaw("LOWER(users.name) LIKE ? ESCAPE '!'", [$pattern])
                            ->orWhereRaw("LOWER(users.email) LIKE ? ESCAPE '!'", [$pattern]);
                    });
            });
        }

        return $query;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'administrator' => $query,
            'teacher' => $query->whereHas('schoolClass', fn ($q) => $q->where('teacher_id', $user->id)->orWhereHas('subjects', fn ($s) => $s->where('teacher_id', $user->id))),
            'parent' => $query->where('parent_id', $user->id),
            default => $query->where('user_id', $user->id),
        };
    }
}
