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
        $classes = SchoolClass::with('subjects', 'classGroup')->orderBy('name')->orderBy('section')->get();
        $plannerClasses = $classes->map(function ($class) {
            return ['id' => $class->id, 'label' => $class->label, 'program' => $class->program, 'session' => $class->session,
                'hours' => $class->sessionHours(), 'subjects' => $class->timetableOwner()->subjects->map(fn ($subject) => [
                    'id' => $subject->id, 'name' => $subject->name,
                    'minutes' => $class->program === 'Khmer' ? KhmerLessonDuration::minutes($subject) : 60,
                ])->values()];
        })->values();

        return ['buildings' => Building::orderBy('name')->get(), 'plannerClasses' => $plannerClasses,
            'programSessions' => \App\Models\Program::sessionMap()];
    }

    public function save(array $attributes, Request $request, ?Classroom $classroom = null): Classroom
    {
        $plan = $request->validate([
            'plan_enabled' => 'nullable|boolean',
            'program' => ['required_if:plan_enabled,1', 'nullable', Rule::in(array_keys(\App\Models\Program::sessionMap()))],
            'session' => 'required_if:plan_enabled,1|nullable|in:Morning,Afternoon,Evening',
            'school_class_id' => 'required_if:plan_enabled,1|nullable|integer|exists:school_classes,id',
            'day' => 'required_if:plan_enabled,1|nullable|in:Monday,Tuesday,Wednesday,Thursday,Friday',
            'academic_year' => ['required_if:plan_enabled,1', 'nullable', 'regex:/^\d{4}-\d{4}$/'],
            'room_change_reason' => 'nullable|string|max:500',
            'slots' => 'nullable|array|max:4',
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
            $classroom ??= new Classroom;
            $classroom->fill($attributes)->save();
            if (! $request->boolean('plan_enabled')) return $classroom;

            $class = SchoolClass::findOrFail($plan['school_class_id']);
            if ($class->program !== $plan['program'] || $class->session !== $plan['session'] || ! in_array($class->session, ProgramSessions::for($class->program), true)) {
                $this->fail('Choose a class matching the selected program and session.');
            }
            $owner = $class->timetableOwner();
            $selected = collect($plan['slots'] ?? [])->filter(fn ($slot) => ! empty($slot['subject_id']) || ! empty($slot['teacher_id']));
            if ($selected->isEmpty()) $this->fail('Choose a subject and teacher for at least one period, or turn off lesson setup.');
            foreach ($selected as $index => $slot) {
                if (empty($slot['subject_id']) || empty($slot['teacher_id'])) $this->fail('Select both subject and teacher for each planned period.');
                $subject = $owner->subjects()->find($slot['subject_id']);
                if (! $subject) $this->fail('Choose a subject from this class curriculum.');
                $minutes = $class->program === 'Khmer' ? KhmerLessonDuration::minutes($subject) : 60;
                $endMinute = TeacherManagementService::minute($slot['starts_at']) + $minutes;
                $end = sprintf('%02d:%02d', intdiv($endMinute, 60), $endMinute % 60);
                try {
                    app(LessonManagementService::class)->save([
                        'school_class_id' => $owner->id, 'subject_id' => $subject->id, 'teacher_id' => $slot['teacher_id'],
                        'classroom_id' => $classroom->id, 'program' => $class->program, 'session' => $class->session,
                        'day' => $plan['day'], 'academic_year' => $plan['academic_year'],
                        'starts_at' => $slot['starts_at'], 'ends_at' => $end,
                        'room_change_reason' => $plan['room_change_reason'] ?? null,
                    ]);
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
