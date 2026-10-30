<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    protected $guarded = [];

    public function save(array $options = [])
    {
        $service = app(\App\Services\TeacherManagementService::class);
        return $service->locked(function () use ($options, $service) {
            if ($this->teacher_id && $service->managed($this->teacher_id) && $this->isDirty(['teacher_id', 'name', 'school_class_id'])) {
                $this->unsetRelation('schoolClass');
                $service->validateSubject($this);
            }
            $result = parent::save($options);
            if ($result && $this->wasChanged('teacher_id')) Schedule::where('subject_id', $this->id)->update(['teacher_id' => $this->teacher_id]);
            return $result;
        });
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
