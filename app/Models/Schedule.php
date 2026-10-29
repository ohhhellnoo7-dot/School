<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    public const TYPE_ACADEMIC = 'academic';

    public const TYPE_RELAXATION = 'relaxation';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Schedule $schedule) {
            if ($schedule->subject_id) {
                $subject = Subject::find($schedule->subject_id);
                if ($subject) {
                    $schedule->school_class_id = $subject->school_class_id;
                    $schedule->teacher_id = $subject->teacher_id;
                    $schedule->entry_type = self::TYPE_ACADEMIC;
                }
            }
        });
    }

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function isRelaxation(): bool
    {
        return $this->entry_type === self::TYPE_RELAXATION;
    }
}
