<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\AutomaticTimetableGenerator;
use App\Support\SchoolModules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableController extends Controller
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    public function show(Request $request, SchoolClass $schoolClass): View
    {
        $this->authorizeView($request, $schoolClass);

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
        ];

        return view('school.classes.timetable', [
            'schoolClass' => $schoolClass,
            'availableClasses' => $availableClasses,
            'days' => self::DAYS,
            'periods' => $periods,
            'slots' => $slots,
            'stats' => $stats,
        ]);
    }

    public function generate(Request $request, SchoolClass $schoolClass, AutomaticTimetableGenerator $generator): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $created = $generator->generate($schoolClass);

        return redirect()->route('classes.timetable', $schoolClass)
            ->with('success', "Timetable generated successfully with {$created->count()} weekly entries.");
    }

    public function regenerate(Request $request, SchoolClass $schoolClass, AutomaticTimetableGenerator $generator): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $request->validate(['confirm_regeneration' => ['required', 'accepted']]);
        $created = $generator->generate($schoolClass, true);

        return redirect()->route('classes.timetable', $schoolClass)
            ->with('success', "Timetable regenerated successfully with {$created->count()} weekly entries.");
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
