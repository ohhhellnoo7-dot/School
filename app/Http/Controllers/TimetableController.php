<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\SchoolModules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\{RotationTimetableGenerator, TeacherManagementService};
use App\Models\Subject;
use Illuminate\Support\Str;

class TimetableController extends Controller
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    public function show(Request $request, SchoolClass $schoolClass): View
    {
        $this->authorizeView($request, $schoolClass);
        $request->validate(['day' => 'nullable|in:Monday,Tuesday,Wednesday,Thursday,Friday']);

        $entries = Schedule::with(['subject.teacher', 'teacher'])
            ->where('school_class_id', $schoolClass->id)
            ->orderBy('starts_at')
            ->get();

        $slots = $entries->groupBy('day');
        $availableClasses = $this->visibleClasses($request)
            ->orderBy('name')
            ->orderBy('section')
            ->orderBy('program')
            ->get();

        $periods = match ($schoolClass->session) {
            'Afternoon' => [
                ['13:00', '14:00'], ['14:00', '15:00'],
                ['15:00', '16:00'], ['16:00', '17:00'],
            ],
            'Evening' => [
                ['16:30', '17:30'], ['17:30', '18:30'],
            ],
            default => [
                ['07:00', '08:00'], ['08:00', '09:00'],
                ['09:00', '10:00'], ['10:00', '11:00'],
            ],
        };

        $stats = [
            'total' => $entries->count(),
            'academic' => $entries->where('entry_type', 'academic')->count(),
            'relaxation' => $entries->where('entry_type', 'relaxation')->count(),
            'academic_minutes' => $entries->where('entry_type', 'academic')->sum(fn ($s) => TeacherManagementService::duration($s->starts_at, $s->ends_at)),
            'relaxation_minutes' => $entries->where('entry_type', 'relaxation')->sum(fn ($s) => TeacherManagementService::duration($s->starts_at, $s->ends_at)),
        ];
        $schoolClass->load('subjects.teacher');

        return view('school.classes.timetable', [
            'schoolClass' => $schoolClass,
            'availableClasses' => $availableClasses,
            'days' => $request->filled('day') ? [$request->input('day')] : self::DAYS,
            'periods' => $periods,
            'slots' => $slots,
            'stats' => $stats,
            'cancelled' => Schedule::withoutGlobalScope('active')->where('school_class_id', $schoolClass->id)->whereNotNull('cancelled_at')->orderByDesc('cancelled_at')->get(),
        ]);
    }

    public function generate(Request $request, SchoolClass $schoolClass, RotationTimetableGenerator $generator): View
    {
        $this->authorizeAdministrator($request);
        return $this->preview($request, $schoolClass, $generator, false);
    }

    public function regenerate(Request $request, SchoolClass $schoolClass, RotationTimetableGenerator $generator): View
    {
        $this->authorizeAdministrator($request);
        return $this->preview($request, $schoolClass, $generator, true);
    }

    private function preview(Request $request, SchoolClass $class, RotationTimetableGenerator $generator, bool $replace): View
    {
        $plan = $generator->preview($class, $replace);
        $token = (string) Str::uuid();
        $request->session()->put('rotation_preview', ['plan' => $plan, 'token' => $token, 'created' => time()]);
        $old = Schedule::with('subject.teacher')->where('school_class_id', $class->id)->get();
        $proposed = collect($plan['rows'])->map(fn ($row) => (new Schedule($row))->setRelation('subject', $row['subject_id'] ? Subject::with('teacher')->find($row['subject_id']) : null)->setRelation('teacher', $row['teacher_id'] ? \App\Models\User::find($row['teacher_id']) : null));
        return view('school.classes.rotation-preview', compact('class', 'plan', 'token', 'old', 'proposed'));
    }

    public function confirm(Request $request, SchoolClass $schoolClass, RotationTimetableGenerator $generator): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $request->validate(['token' => 'required|string', 'confirm' => 'required|accepted', 'replace' => 'sometimes|accepted']);
        $preview = $request->session()->get('rotation_preview');
        if (! $preview || $preview['plan']['class_id'] !== $schoolClass->id || time() - $preview['created'] > 1800 || ! hash_equals($preview['token'], $request->input('token'))) app(TeacherManagementService::class)->fail('The preview expired or changed. Generate another preview.');
        if ($preview['plan']['replace'] && ! $request->boolean('replace')) app(TeacherManagementService::class)->fail('Confirm replacement of the existing timetable.');
        app(TeacherManagementService::class)->locked(function () use ($generator, $preview) {
            app(\App\Services\TimetableBackup::class)->create();
            $generator->confirm($preview['plan']);
        });
        $request->session()->forget('rotation_preview');
        return redirect()->route('classes.timetable', $schoolClass)->with('success', 'Confirmed timetable saved. Teacher schedules use these same lessons.');
    }

    public function settings(Request $request, SchoolClass $schoolClass, TeacherManagementService $service): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate(['subjects' => 'required|array', 'subjects.*.id' => 'required|integer', 'subjects.*.weekly_hours' => 'required|integer|min:0|max:20', 'subjects.*.lesson_minutes' => 'required|in:60,120', 'relaxation_hours' => 'required|integer|min:0|max:5', 'confirm' => 'required|accepted']);
        $service->locked(function () use ($data, $schoolClass, $service) {
            if (collect($data['subjects'])->sum('weekly_hours') + $data['relaxation_hours'] > 20) $service->fail('Curriculum and relaxation exceed the 20 available session hours.');
            if (collect($data['subjects'])->pluck('id')->unique()->count() !== count($data['subjects']) || count($data['subjects']) !== $schoolClass->subjects()->count()) $service->fail('Configure every class subject exactly once.');
            foreach ($data['subjects'] as $row) {
                $subject = $schoolClass->subjects()->findOrFail($row['id']);
                \App\Support\KhmerLessonDuration::validateWeeklyHours($subject, $row['weekly_hours'] * 60);
                $subject->update(['required_weekly_minutes' => $row['weekly_hours'] * 60, 'lesson_minutes' => $schoolClass->program === 'Khmer' ? \App\Support\KhmerLessonDuration::minutes($subject) : $row['lesson_minutes']]);
            }
            $schoolClass->update(['weekly_relaxation_minutes' => $data['relaxation_hours'] * 60]);
        });
        return back()->with('success', 'Scheduling settings saved. Existing lessons were preserved. Generate a preview to apply the curriculum.');
    }

    public function cancelLesson(Request $request, Schedule $schedule, TeacherManagementService $service): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $request->validate(['confirm' => 'required|accepted']);
        $service->locked(fn () => Schedule::whereKey($schedule->id)->update(['cancelled_at' => now()]));
        return back()->with('success', 'Lesson cancelled. Its history remains available; room reservations remain in place.');
    }

    private function authorizeView(Request $request, SchoolClass $schoolClass): void
    {
        if ($request->user()->role === 'parent') {
            abort_unless(Student::visibleTo($request->user())->where('school_class_id', $schoolClass->id)->exists(), 404);

            return;
        }

        SchoolModules::query('classes', $request->user())->findOrFail($schoolClass->id);
    }

    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user()->role === 'administrator', 403);
    }

    private function visibleClasses(Request $request): Builder
    {
        if ($request->user()->role === 'parent') {
            return SchoolClass::query()->whereIn('id', Student::visibleTo($request->user())->select('school_class_id'));
        }

        return SchoolModules::query('classes', $request->user());
    }
}
