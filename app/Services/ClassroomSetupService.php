<?php

namespace App\Services;

use App\Models\{Building, Classroom, SchoolClass};
use App\Support\{KhmerLessonDuration, ProgramSessions};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassroomSetupService
{
    public function formData(): array
    {
        $classes = SchoolClass::whereHas('classGroup', fn ($group) => $group->active())
            ->with('subjects', 'classGroup')->orderBy('name')->orderBy('section')->get();
        $plannerClasses = $classes->map(function ($class) {
            return ['id' => $class->id, 'class_group_id' => $class->class_group_id, 'label' => $class->label, 'program' => $class->program, 'session' => $class->session, 'teacher_id' => $class->teacher_id,
                'hours' => $class->sessionHours(), 'subjects' => $class->timetableOwner()->subjects->filter(fn ($subject) => $class->program !== 'Khmer' || ! $class->subjects->contains(fn ($s) => $s->curriculum_requirement_id) || $subject->curriculum_requirement_id !== null)->map(fn ($subject) => [
                    'id' => $subject->id, 'name' => $subject->name,
                    'minutes' => $class->program === 'Khmer' ? KhmerLessonDuration::minutes($subject) : 60,
                ])->values()];
        })->values();

        $lessons = \App\Models\Schedule::whereHas('schoolClass', fn ($query) => $query->where('program', 'Japan'))->get(['teacher_id', 'school_class_id']);
        $teachers = \App\Models\User::where('role', 'teacher')->where('program', 'Japan')->where('status', 'active')->orderBy('name')->get(['id', 'name']);
        foreach ($teachers as $teacher) {
            $lessonClasses = $lessons->where('teacher_id', $teacher->id)->pluck('school_class_id');
            $teacher->setAttribute('assignments', $classes->filter(fn ($class) => $class->program === 'Japan' &&
                ($class->teacher_id === $teacher->id || $class->classGroup?->teacher_id === $teacher->id || $lessonClasses->contains($class->id)))
                ->map(fn ($class) => ['id' => $class->id, 'class_group_id' => $class->class_group_id, 'name' => $class->name])->values());
        }
        return ['buildings' => Building::orderBy('name')->get(), 'plannerClasses' => $plannerClasses,
            'japaneseTeachers' => $teachers,
            'classTeachers' => \App\Models\User::where('role', 'teacher')->where('status', 'active')->orderBy('name')->get(['id', 'name', 'program']),
            'eveningStudyPeriods' => \App\Models\EveningStudyPeriod::orderBy('starts_at')->get()->groupBy('program')->map(fn ($items) => $items->mapWithKeys(fn ($p) => [$p->id => ['label' => $p->label, 'starts_at' => substr($p->starts_at, 0, 5), 'ends_at' => substr($p->ends_at, 0, 5)]])),
            'programSessions' => \App\Models\Program::sessionMap()];
    }

    public function save(array $attributes, Request $request, ?Classroom $classroom = null): Classroom
    {
        $plan = $request->validate([
            'plan_enabled' => 'nullable|boolean',
            'evening_period' => 'nullable|integer|exists:evening_study_periods,id',
            'class_days' => 'nullable|in:weekday,weekend',
            'program' => ['required_if:plan_enabled,1', 'nullable', Rule::in(array_keys(\App\Models\Program::sessionMap()))],
            'session' => 'required_if:plan_enabled,1|nullable|in:Morning,Afternoon,Evening',
            'school_class_id' => 'required_if:plan_enabled,1|nullable|integer|exists:school_classes,id',
            'day' => 'required_if:plan_enabled,1|nullable|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'academic_year' => ['required_if:plan_enabled,1', 'nullable', 'regex:/^\d{4}-\d{4}$/'],
            'room_change_reason' => 'nullable|string|max:500',
            'class_teacher_id' => 'nullable|integer|exists:users,id',
            'slots' => 'nullable|array|max:20',
            'slots.*.day' => 'sometimes|required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'slots.*.subject_id' => 'nullable|integer|exists:subjects,id',
            'slots.*.teacher_id' => 'nullable|integer|exists:users,id',
            'slots.*.starts_at' => 'required|date_format:H:i',
        ]);

        return app(TeacherManagementService::class)->locked(function () use ($attributes, $request, $classroom, $plan) {
            $newName = trim($attributes['new_building_name'] ?? '');
            unset($attributes['new_building_name']);
            if ($attributes['building'] === '__new') {
                if ($newName === '') $this->fail('Enter the new building name.');
                $building = Building::firstOrCreate(['name' => $newName], ['code' => (string) Str::uuid()]);
                $attributes['building'] = $building->code;
            }
            $editing = $classroom?->exists ?? false;
            $classroom ??= new Classroom;
            $classroom->fill($attributes)->save();
            if (! $request->boolean('plan_enabled')) return $classroom;

            $class = SchoolClass::findOrFail($plan['school_class_id']);
            if (! $class->classGroup || $class->classGroup->status !== 'active') {
                $this->fail('Choose an active student class created in Student classes.');
            }
            if ($class->program !== $plan['program'] || $class->session !== $plan['session'] || ! in_array($class->session, ProgramSessions::for($class->program), true)) {
                $this->fail('Choose a class matching the selected program and session.');
            }
            $allowedDays = ($plan['class_days'] ?? 'weekday') === 'weekend' ? ['Saturday', 'Sunday'] : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
            if ($class->program === 'Khmer' && (($plan['class_days'] ?? 'weekday') === 'weekend' || in_array($plan['day'], ['Saturday', 'Sunday'], true))) {
                $this->fail('Khmer classes run Monday to Friday only.');
            }
            foreach ($plan['slots'] ?? [] as $slot) {
                if ((! empty($slot['subject_id']) || ! empty($slot['teacher_id'])) && ! in_array($slot['day'] ?? $plan['day'], $allowedDays, true)) {
                    $this->fail('Choose lesson days matching Weekday or Weekend.');
                }
            }
            $studyPeriod = empty($plan['evening_period']) ? null : \App\Models\EveningStudyPeriod::findOrFail($plan['evening_period']);
            if ($studyPeriod && ($class->session !== 'Evening' || $studyPeriod->program !== $class->program)) $this->fail('Choose an evening period for this program.');
            $owner = $class->timetableOwner();
            if ($class->program === 'Japan') {
                $teacherId = $plan['class_teacher_id'] ?? $owner->teacher_id;
                $teacher = \App\Models\User::where('role', 'teacher')->where('program', 'Japan')->where('status', 'active')->find($teacherId);
                if (! $teacher) $this->fail('Choose an active Japanese class teacher for Monday through Friday.');
                $otherClasses = SchoolClass::where('program', 'Japan')->where('teacher_id', $teacher->id)
                    ->whereKeyNot($owner->id)->get();
                if ($otherClasses->contains(fn ($other) => ! $owner->class_group_id || $other->class_group_id !== $owner->class_group_id)) {
                    $this->fail('This Japanese teacher is already assigned to another class. Choose a teacher who can teach this class Monday through Friday.');
                }
                if ($owner->schedules()->where('teacher_id', '!=', $teacher->id)->exists()) $this->fail('This class already has lessons with another teacher. Update those lessons before changing its class teacher.');
                $owner->update(['teacher_id' => $teacher->id]);
                $owner->classGroup?->update(['teacher_id' => $teacher->id]);
            }
            $selected = collect($plan['slots'] ?? [])->filter(fn ($slot) => ! empty($slot['subject_id']) || ! empty($slot['teacher_id']));
            if ($selected->isEmpty()) $this->fail('Choose a subject and teacher for at least one period, or turn off lesson setup.');
            if ($class->program === 'Japan' && $selected->pluck('subject_id')->filter()->duplicates()->isNotEmpty()) $this->fail('Choose each Japan subject only once in the weekly plan.');
            foreach ($selected as $index => $slot) {
                if ($class->program === 'Japan') $slot['teacher_id'] = $teacher->id;
                if (empty($slot['subject_id']) || empty($slot['teacher_id'])) $this->fail('Select both subject and teacher for each planned period.');
                $subject = $owner->subjects()->find($slot['subject_id']);
                if (! $subject) $this->fail('Choose a subject from this class curriculum.');
                $minutes = $class->program === 'Khmer' ? KhmerLessonDuration::minutes($subject) : 60;
                $endMinute = TeacherManagementService::minute($slot['starts_at']) + $minutes;
                if ($studyPeriod && ($slot['starts_at'] < substr($studyPeriod->starts_at, 0, 5) || $endMinute > TeacherManagementService::minute($studyPeriod->ends_at))) $this->fail('Lesson must fit within the selected evening period.');
                $end = sprintf('%02d:%02d', intdiv($endMinute, 60), $endMinute % 60);
                try {
                    $existing = $editing ? \App\Models\Schedule::where('classroom_id', $classroom->id)->where('school_class_id', $owner->id)
                        ->where('academic_year', $plan['academic_year'])->where('day', $slot['day'] ?? $plan['day'])
                        ->get()->first(fn ($lesson) => substr($lesson->starts_at, 0, 5) === $slot['starts_at']) : null;
                    app(LessonManagementService::class)->save([
                        'school_class_id' => $owner->id, 'subject_id' => $subject->id, 'teacher_id' => $slot['teacher_id'],
                        'classroom_id' => $classroom->id, 'program' => $class->program, 'session' => $class->session,
                        'day' => $slot['day'] ?? $plan['day'], 'academic_year' => $plan['academic_year'],
                        'starts_at' => $slot['starts_at'], 'ends_at' => $end,
                        'room_change_reason' => $plan['room_change_reason'] ?? null,
                    ], $existing);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages(['slots.'.$index.'.teacher_id' => $slot['starts_at'].': '.collect($e->errors())->flatten()->join(' ')]);
                }
            }
            return $classroom;
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['slots' => $message]);
    }
}
