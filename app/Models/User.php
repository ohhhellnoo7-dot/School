<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected static function booted(): void
    {
        static::addGlobalScope('archived',fn($query)=>$query->whereNull('users.archived_at'));
    }

    protected $attributes = ['role' => 'student'];

    public function requiresTeacherConfirmation(): bool
    {
        return $this->teacher_record_source === 'generated_unverified' && $this->employee_confirmed_at === null;
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'program',
        'phone', 'profile_number', 'gender', 'address', 'qualification', 'hire_date', 'salary', 'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? route('users.photo', $this) : null;
    }

    public function ensureParentProfileNumber(): void
    {
        if ($this->exists && $this->role === 'parent' && empty($this->getAttributes()['profile_number'])) {
            $this->profile_number = $this->automaticParentNumber();
            $this->saveQuietly();
        }
    }

    private function automaticParentNumber(): string
    {
        $base = 'PAR'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
        $number = $base;
        $suffix = 0;
        while (static::where('profile_number', $number)->where('id', '!=', $this->id)->exists()) {
            $number = $base.'-'.++$suffix;
        }
        return $number;
    }

    public function getProfileNumberAttribute($value): ?string
    {
        return $value ?: ($this->exists && $this->role === 'parent' ? $this->automaticParentNumber() : null);
    }

    public function children()
    {
        return $this->hasMany(Student::class, 'parent_id');
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class, 'teacher_id');
    }

    public function classes()
    {
        return $this->hasMany(SchoolClass::class, 'teacher_id');
    }

    protected function casts(): array
    {
        return [
            'teacher_planned_grades' => 'array',
            'employee_confirmed_at' => 'datetime',
            'hire_date' => 'date', 'salary' => 'decimal:2',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
