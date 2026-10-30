<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    public const TYPE_ACADEMIC = 'academic';

    public const TYPE_RELAXATION = 'relaxation';

    protected $guarded = [];

    public function save(array $options = [])
    {
        $service = app(\App\Services\TeacherManagementService::class);
        return $service->locked(function () use ($options, $service) {
            if ($this->subject_id && $this->isDirty(['subject_id', 'day', 'starts_at', 'ends_at', 'room', 'classroom_id'])) {
                $subject = Subject::findOrFail($this->subject_id);
                if ($subject->teacher_id && $service->managed($subject->teacher_id)) $service->validateLesson($subject, $this);
            }
            return parent::save($options);
        });
    }

    protected static function booted(): void
    {
        static::addGlobalScope('active', fn ($q) => $q->whereNull('schedules.cancelled_at'));
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
        return ['generated_at' => 'datetime', 'cancelled_at' => 'datetime'];
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

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function isRelaxation(): bool
    {
        return $this->entry_type === self::TYPE_RELAXATION;
    }
}
