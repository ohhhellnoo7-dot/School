<?php

namespace App\Http\Controllers;

use App\Models\{Schedule, SchoolClass, Subject, User};
use App\Services\TeacherManagementService;
use App\Support\AcademicYear;
use Illuminate\Http\Request;

class ClassroomSetupController extends Controller
{
    public function options(Request $request, TeacherManagementService $rules)
    {
        $data = $request->validate([
            'school_class_id' => 'required|integer|exists:school_classes,id',
            'subject_id' => 'required|integer|exists:subjects,id',
            'day' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday',
            'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i|after:starts_at',
            'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/'],
        ]);
        $class = SchoolClass::findOrFail($data['school_class_id'])->timetableOwner();
        $subject = $class->subjects()->findOrFail($data['subject_id']);
        $teachers = User::where('role', 'teacher')->orderBy('name')->get()->map(function ($teacher) use ($rules, $subject, $class, $data) {
            $qualifications = $rules->qualifications($teacher);
            $lessons = $rules->lessons($teacher)->filter(fn ($s) => AcademicYear::overlaps($s->academic_year, $data['academic_year']));
            $breaks = $rules->breaks($teacher);
            $reasons = [];
            if ($teacher->status !== 'active') $reasons[] = 'Inactive';
            if (! $qualifications->contains($subject->name)) $reasons[] = 'Not qualified for this subject';
            if (! $rules->onDuty($teacher, $data['day'], $data['starts_at'], $data['ends_at'])) $reasons[] = 'Outside working hours or unavailable';
            if ($breaks->contains(fn ($b) => $b->weekday === $data['day'] && $rules::overlaps($b->start_time, $b->end_time, $data['starts_at'], $data['ends_at']))) $reasons[] = 'Scheduled break';
            if ($lessons->contains(fn ($s) => $s->day === $data['day'] && $rules::overlaps($s->starts_at, $s->ends_at, $data['starts_at'], $data['ends_at']))) $reasons[] = 'Busy teaching';
            if ($lessons->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at)) + $rules::duration($data['starts_at'], $data['ends_at']) > $rules->limit($teacher)) $reasons[] = 'Weekly teaching limit reached';
            if (Subject::where('teacher_id', $teacher->id)->pluck('name')->concat($lessons->pluck('subject.name'))->push($subject->name)->filter()->unique()->count() > $rules->subjectLimit($teacher)) $reasons[] = 'Subject limit exceeded';
            try {
                $rules->validateEnglishSession($teacher, new Schedule(['day' => $data['day'], 'starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'academic_year' => $data['academic_year']]), $class);
            } catch (\Illuminate\Validation\ValidationException $e) { $reasons[] = 'Already assigned in this English session'; }

            return ['id' => $teacher->id, 'name' => $teacher->name, 'program' => $teacher->program,
                'subjects' => $qualifications->values(), 'available' => $reasons === [], 'reasons' => $reasons,
                'lessons' => $lessons->map(fn ($s) => ['day' => $s->day, 'start' => substr($s->starts_at, 0, 5), 'end' => substr($s->ends_at, 0, 5), 'subject' => $s->subject?->name, 'class' => $s->schoolClass?->label])->values(),
                'breaks' => $breaks->map(fn ($b) => ['day' => $b->weekday, 'start' => substr($b->start_time, 0, 5), 'end' => substr($b->end_time, 0, 5)])->values(),
                'working' => $rules->periods($teacher)->map(fn ($p) => ['day' => $p->weekday, 'start' => substr($p->start_time, 0, 5), 'end' => substr($p->end_time, 0, 5), 'unavailable' => (bool) $p->unavailable])->values(),
            ];
        });
        return response()->json(['teachers' => $teachers->sortBy(fn ($t) => ! $t['available'])->values()])->header('Cache-Control', 'no-store');
    }
}
