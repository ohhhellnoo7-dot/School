<?php

namespace App\Services;

use App\Models\{Classroom, ClassroomBooking, ClassSession, Schedule, SchoolClass, Subject, User};
use Illuminate\Support\Facades\Validator;

class LessonManagementService
{
    public function __construct(private TeacherManagementService $rules) {}

    public function version(Schedule $lesson): string
    {
        return hash('sha256', json_encode($lesson->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function save(array $data, ?Schedule $lesson = null, bool $strict = true): Schedule
    {
        return $this->rules->locked(function () use ($data, $lesson) {
            $lesson = $lesson ? $lesson->fresh() : new Schedule;
            if (! $lesson) $this->rules->fail('The lesson is no longer active.');
            if (isset($data['version']) && ! hash_equals($this->version($lesson), $data['version'])) $this->rules->fail('The lesson changed. Reload before editing.');
            $row = $this->validated($data);
            $subject = Subject::findOrFail($row['subject_id']);
            if ($subject->school_class_id !== $row['school_class_id']) $this->rules->fail('The subject must belong to this student cohort.');
            $lesson->fill($row + ['entry_type' => 'academic']);
            $this->validate($lesson);
            $lesson->saveQuietly();
            $teacher = User::findOrFail($lesson->teacher_id);
            if ($this->rules->breaks($teacher)->isEmpty() && $this->rules->eligibleForBreaks($teacher)) $this->rules->saveBreaks($teacher, $this->rules->proposeBreaks($teacher));
            return $lesson->refresh();
        });
    }

    private function validated(array $data): array
    {
        if (empty($data['classroom_id'])) $this->rules->fail('Select a physical classroom ID. A room label is insufficient.');
        $row = Validator::make($data, [
            'school_class_id' => 'required|integer|exists:school_classes,id', 'subject_id' => 'required|integer|exists:subjects,id',
            'teacher_id' => 'required|integer|exists:users,id', 'classroom_id' => 'required|integer|exists:classrooms,id',
            'day' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday', 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i',
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'room_change_reason' => 'nullable|string|max:500',
        ])->validate();
        $class = SchoolClass::findOrFail($row['school_class_id']);
        if ($class->shared_timetable_class_id) {
            $subject = Subject::findOrFail($row['subject_id']);
            if ($subject->school_class_id !== $class->id) $this->rules->fail('The subject must belong to the selected student cohort.');
            $owner = $class->timetableOwner();
            $row['school_class_id'] = $owner->id;
            $row['subject_id'] = $owner->subjects()->where('name', $subject->name)->firstOrFail()->id;
        }
        if ((isset($data['program']) && $data['program'] !== $class->program) || (isset($data['session']) && $data['session'] !== $class->session)) $this->rules->fail('Program and session must match the selected cohort.');
        $row['room'] = Classroom::findOrFail($row['classroom_id'])->room_number;
        return $row;
    }

    public function validate(Schedule $lesson, bool $teacherRules = true): void
    {
        $class = SchoolClass::findOrFail($lesson->school_class_id);
        if ($class->shared_timetable_class_id) $this->rules->fail('Save lessons on the shared timetable owner.');
        $room = Classroom::findOrFail($lesson->classroom_id);
        $minutes = $this->rules::duration($lesson->starts_at, $lesson->ends_at);
        if (! $lesson->isRelaxation() && $lesson->subject_id && $class->program === 'Khmer' && $minutes !== \App\Support\KhmerLessonDuration::minutes(Subject::findOrFail($lesson->subject_id))) $this->rules->fail('This Khmer subject requires a '.(\App\Support\KhmerLessonDuration::minutes(Subject::findOrFail($lesson->subject_id)) / 60).'-hour consecutive lesson block.');
        if (! in_array($lesson->day, $this->rules::DAYS, true) || ! in_array($minutes, $lesson->isRelaxation() ? [60] : [60, 120], true)) $this->rules->fail('Choose a valid weekday and a one-hour or two-hour lesson.');
        [$start, $end] = $class->sessionHours();
        if ($this->rules::minute($lesson->starts_at) < $this->rules::minute($start) || $this->rules::minute($lesson->ends_at) > $this->rules::minute($end) || ($this->rules::minute($lesson->starts_at) - $this->rules::minute($start)) % 60 !== 0) $this->rules->fail('Lesson is outside its configured program/session hours.');
        if (! ClassSession::where('class_group_id', $class->class_group_id)->where('session', $class->session)->where('starts_at', '<=', $lesson->starts_at)->where('ends_at', '>=', $lesson->ends_at)->exists()) $this->rules->fail('Confirm the class session first.');
        $cohortIds = SchoolClass::whereKey($class->id)->orWhere('shared_timetable_class_id', $class->id)->pluck('id');
        $studentCount = \App\Models\Student::whereIn('school_class_id', $cohortIds)->where('status', 'active')->count();
        if ($room->status !== 'active' || $room->capacity < $studentCount) $this->rules->fail('The physical classroom is inactive or too small.');
        $bookings = ClassroomBooking::with('classSession')->where('day_of_week', $lesson->day)->where('starts_at', '<', $lesson->ends_at)->where('ends_at', '>', $lesson->starts_at)->get();
        if ($bookings->contains(fn ($b) => $b->classroom_id === $room->id && ($b->class_group_id !== $class->class_group_id || $b->classSession?->session !== $class->session))) $this->rules->fail('The physical classroom is reserved for another cohort.');
        $assigned = $bookings->filter(fn ($b) => $b->class_group_id === $class->class_group_id && $b->classSession?->session === $class->session);
        if ($assigned->isNotEmpty() && ! $assigned->contains('classroom_id', $room->id) && ! $lesson->getAttribute('room_change_reason')) $this->rules->fail('Room differs from the cohort reservation. Record an explicit room change reason.');
        $busy = Schedule::where('day', $lesson->day)->where('starts_at', '<', $lesson->ends_at)->where('ends_at', '>', $lesson->starts_at)->when($lesson->academic_year,fn ($q)=>$q->where(fn ($year)=>$year->where('academic_year',$lesson->academic_year)->orWhereNull('academic_year')))->when($lesson->id, fn ($q) => $q->where('id', '!=', $lesson->id))->get();
        if ($busy->contains(fn ($s) => $s->school_class_id === $class->id || $s->classroom_id === $room->id || ($lesson->teacher_id && $s->teacher_id === $lesson->teacher_id))) $this->rules->fail('This lesson overlaps a teacher, student cohort or physical classroom commitment.');
        if ($lesson->isRelaxation() || ! $teacherRules) return;
        $teacher = User::findOrFail($lesson->teacher_id);
        $subject = Subject::findOrFail($lesson->subject_id);
        if ($subject->school_class_id !== $class->id) $this->rules->fail('Subject does not belong to this cohort.');
        if ($teacher->role !== 'teacher' || $teacher->status !== 'active' || ! $this->rules->qualifications($teacher)->contains($subject->name)) $this->rules->fail('Select an active qualified teacher.');
        $this->rules->validateEnglishSession($teacher, $lesson, $class);
        if (! $this->rules->onDuty($teacher, $lesson->day, $lesson->starts_at, $lesson->ends_at)) $this->rules->fail('Teacher is unavailable during this lesson.');
        if ($this->rules->breaks($teacher)->contains(fn ($b) => $b->weekday === $lesson->day && $this->rules::overlaps($lesson->starts_at, $lesson->ends_at, $b->start_time, $b->end_time))) $this->rules->fail('Teacher has a designated free period.');
        $others = Schedule::with('subject')->where('teacher_id', $teacher->id)->when($lesson->academic_year,fn ($q)=>$q->where(fn ($year)=>$year->where('academic_year',$lesson->academic_year)->orWhereNull('academic_year')))->when($lesson->id, fn ($q) => $q->where('id', '!=', $lesson->id))->get();
        if ($others->sum(fn ($s) => $this->rules::duration($s->starts_at, $s->ends_at)) + $minutes > $this->rules->limit($teacher)) $this->rules->fail('Teacher weekly workload limit exceeded.');
        if (Subject::where('teacher_id', $teacher->id)->pluck('name')->concat($others->pluck('subject.name'))->push($subject->name)->filter()->unique()->count() > $this->rules->subjectLimit($teacher)) $this->rules->fail('Khmer teacher exceeds the two-subject limit.');
    }

    public function saveRelaxation(array $data): Schedule
    {
        return $this->rules->locked(function () use ($data) {
            $row = Validator::make($data, ['school_class_id' => 'required|integer|exists:school_classes,id', 'classroom_id' => 'required|integer|exists:classrooms,id', 'day' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday', 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i', 'academic_year'=>['nullable','regex:/^\d{4}-\d{4}$/']])->validate();
            $lesson = new Schedule($row + ['room' => Classroom::findOrFail($row['classroom_id'])->room_number, 'entry_type' => 'relaxation']);
            $this->validate($lesson);
            $lesson->saveQuietly();
            return $lesson;
        });
    }

    public function cancel(Schedule $lesson, ?string $version = null): void
    {
        $this->rules->locked(function () use ($lesson, $version) {
            $fresh = $lesson->fresh();
            if (! $fresh || ($version && ! hash_equals($this->version($fresh), $version))) $this->rules->fail('Lesson changed. Reload before cancelling.');
            Schedule::whereKey($fresh->id)->update(['cancelled_at' => now()]);
        });
    }
}
