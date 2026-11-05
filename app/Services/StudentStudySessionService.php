<?php

namespace App\Services;

use App\Models\{SchoolClass, Student};
use Illuminate\Validation\ValidationException;

class StudentStudySessionService
{
    public function sync(Student $student, array $additionalIds): void
    {
        app(TeacherManagementService::class)->locked(function () use ($student, $additionalIds) {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            $ids = collect([$student->school_class_id])->merge($additionalIds)->map(fn ($id) => (int) $id);
            if ($ids->count() > 3 || $ids->unique()->count() !== $ids->count()) $this->fail('Choose up to three different study classes.');
            $classes = SchoolClass::whereIn('id', $ids)->lockForUpdate()->get();
            if ($classes->count() !== $ids->count()) $this->fail('One of the selected classes is no longer available.');
            if ($classes->pluck('session')->unique()->count() !== $classes->count()) $this->fail('Choose only one class per session: Morning, Afternoon or Evening.');
            if ($classes->pluck('program')->unique()->count() !== $classes->count()) $this->fail('Choose a different program for each additional session.');
            foreach ($classes as $class) {
                if (!in_array($class->session, \App\Support\ProgramSessions::for($class->program), true) ||
                    ($class->classGroup && $class->classGroup->status !== 'active')) $this->fail('Choose an active class with a valid study session.');
                [$start, $end] = $class->sessionHours();
                foreach ($classes as $other) {
                    if ($class->is($other)) continue;
                    [$otherStart, $otherEnd] = $other->sessionHours();
                    if (TeacherManagementService::overlaps($start, $end, $otherStart, $otherEnd)) $this->fail('The selected study sessions have overlapping hours.');
                }
                if ($class->classGroup?->capacity && $class->students()->where('status', 'active')->where('students.id', '!=', $student->id)->count() >= $class->classGroup->capacity) $this->fail('A selected class is full. Choose another class.');
            }
            $removed = $student->studyClasses()->whereNotIn('school_classes.id', $ids)->pluck('school_classes.id');
            if ($student->grades()->whereHas('examination.subject', fn ($q) => $q->whereIn('school_class_id', $removed))->exists()) $this->fail('A class with examination results cannot be removed without archiving those results.');
            $student->studyClasses()->sync($classes->mapWithKeys(fn ($class) => [$class->id => ['class_group_id' => $class->class_group_id]])->all());
        });
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['additional_class_ids' => $message]);
    }
}
