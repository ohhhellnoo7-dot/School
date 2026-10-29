<?php

namespace App\Http\Controllers;

use App\Models\ClassGroup;
use App\Models\Classroom;
use App\Models\User;
use App\Support\SchoolModules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassGroupController extends Controller
{
    /** @var array<string, array<string, array{0: string, 1: string}>> */
    private const PROGRAM_SESSIONS = [
        'Khmer' => [
            'Morning' => ['07:00', '11:00'],
            'Afternoon' => ['13:00', '17:00'],
        ],
        'English' => [
            'Morning' => ['07:00', '11:00'],
            'Afternoon' => ['13:00', '17:00'],
            'Evening' => ['17:30', '18:30'],
        ],
        'Chinese' => [
            'Evening' => ['17:30', '18:30'],
        ],
    ];

    public function index(Request $request): View
    {
        abort_unless(SchoolModules::canView($request->user(), 'classes'), 403);

        // Preserve the legacy class directory until normalized class groups
        // have actually been seeded. Existing installations and tests may
        // still rely on SchoolClass rows during the additive migration.
        if (! ClassGroup::query()->exists()) {
            return app(SchoolModuleController::class)->index($request, 'classes');
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'program' => ['nullable', Rule::in(array_keys(self::PROGRAM_SESSIONS))],
            'grade' => ['nullable', 'integer', 'between:1,12'],
            'session' => ['nullable', Rule::in(['Morning', 'Afternoon', 'Evening'])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $classGroups = $this->visibleClassGroups($request->user())
            ->with([
                'teacher:id,name',
                'sessions' => fn ($query) => $query->orderBy('starts_at'),
                'bookings.classroom:id,room_number,name',
            ])
            ->withCount('students')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function ($nested) use ($needle) {
                    $nested->where('name', 'like', $needle)
                        ->orWhere('section', 'like', $needle)
                        ->orWhereHas('teacher', fn ($teacher) => $teacher->where('name', 'like', $needle));
                });
            })
            ->when($filters['program'] ?? null, fn ($query, string $program) => $query->where('program', $program))
            ->when($filters['grade'] ?? null, fn ($query, int|string $grade) => $query->where('grade', (int) $grade))
            ->when($filters['session'] ?? null, fn ($query, string $session) => $query->whereHas('sessions', fn ($sessions) => $sessions->where('session', $session)))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw("CASE program WHEN 'Khmer' THEN 1 WHEN 'English' THEN 2 WHEN 'Chinese' THEN 3 ELSE 4 END")
            ->orderBy('grade')
            ->orderBy('section')
            ->paginate(25)
            ->withQueryString();

        $programCounts = $this->visibleClassGroups($request->user())
            ->selectRaw('program, count(*) as aggregate')
            ->groupBy('program')
            ->pluck('aggregate', 'program');

        return view('school.class-groups.index', [
            'classGroups' => $classGroups,
            'programCounts' => $programCounts,
            'totalClassGroups' => $programCounts->sum(),
            'physicalClassrooms' => Classroom::count(),
        ]);
    }

    public function show(Request $request, ClassGroup $classGroup): View
    {
        abort_unless($this->visibleClassGroups($request->user())->whereKey($classGroup)->exists(), 403);

        $classGroup->load([
            'teacher:id,name,email',
            'sessions' => fn ($query) => $query->orderBy('starts_at'),
            'bookings' => fn ($query) => $query
                ->with(['classroom:id,room_number,name', 'teacher:id,name', 'classSession'])
                ->orderBy('day_of_week')
                ->orderBy('starts_at'),
            'schoolClasses',
        ])->loadCount('students');

        return view('school.class-groups.show', compact('classGroup'));
    }

    public function create(): View
    {
        return view('school.class-groups.form', [
            'classGroup' => new ClassGroup(['capacity' => 30, 'status' => 'active']),
            'teachers' => $this->teachers(),
            'programSessions' => self::PROGRAM_SESSIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $attributes = $this->validated($request);
        $classGroup = ClassGroup::create($attributes);
        $this->syncSessions($classGroup);

        return redirect()->route('class-groups.show', $classGroup)
            ->with('success', "{$classGroup->display_name} was created with its study sessions.");
    }

    public function edit(ClassGroup $classGroup): View
    {
        $classGroup->load('sessions');

        return view('school.class-groups.form', [
            'classGroup' => $classGroup,
            'teachers' => $this->teachers(),
            'programSessions' => self::PROGRAM_SESSIONS,
        ]);
    }

    public function update(Request $request, ClassGroup $classGroup): RedirectResponse
    {
        $attributes = $this->validated($request, $classGroup);

        if ($attributes['program'] !== $classGroup->program && $classGroup->bookings()->exists()) {
            throw ValidationException::withMessages([
                'program' => 'Remove or reassign this class group\'s classroom bookings before changing its academic program.',
            ]);
        }

        $classGroup->update($attributes);
        $this->syncSessions($classGroup);

        return redirect()->route('class-groups.show', $classGroup)
            ->with('success', "{$classGroup->display_name} was updated.");
    }

    public function destroy(ClassGroup $classGroup): RedirectResponse
    {
        if ($classGroup->students()->exists() || $classGroup->bookings()->exists() || $classGroup->schoolClasses()->exists()) {
            throw ValidationException::withMessages([
                'class_group' => 'This class group is linked to students, legacy class records, or classroom bookings and cannot be deleted.',
            ]);
        }

        $name = $classGroup->display_name;
        $classGroup->delete();

        return redirect()->route('class-groups.index')->with('success', "{$name} was deleted.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ClassGroup $classGroup = null): array
    {
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'grade' => ['required', 'integer', 'between:1,12'],
            'section' => ['nullable', 'string', 'max:5'],
            'program' => ['required', Rule::in(array_keys(self::PROGRAM_SESSIONS))],
            'teacher_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'teacher')),
            ],
            'capacity' => ['required', 'integer', 'between:1,500'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $attributes['name'] = trim($attributes['name']);
        $attributes['section'] = filled($attributes['section'] ?? null)
            ? strtoupper(trim($attributes['section']))
            : null;

        if ($attributes['program'] === 'Khmer') {
            if ($attributes['grade'] < 7 || ! in_array($attributes['section'], ['A', 'B', 'C', 'D', 'E'], true)) {
                throw ValidationException::withMessages([
                    'section' => 'Khmer class groups must use Grades 7–12 and a section from A to E.',
                ]);
            }
        } elseif ($attributes['section'] !== null) {
            throw ValidationException::withMessages([
                'section' => "{$attributes['program']} has one class identity per grade, so the section must be empty.",
            ]);
        }

        $duplicate = ClassGroup::query()
            ->where('program', $attributes['program'])
            ->where('grade', $attributes['grade'])
            ->where(function ($query) use ($attributes) {
                $attributes['section'] === null
                    ? $query->whereNull('section')
                    : $query->where('section', $attributes['section']);
            })
            ->when($classGroup, fn ($query) => $query->whereKeyNot($classGroup->getKey()))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'That program, grade, and section already identify an existing class group.',
            ]);
        }

        return $attributes;
    }

    private function syncSessions(ClassGroup $classGroup): void
    {
        $sessions = self::PROGRAM_SESSIONS[$classGroup->program];

        foreach ($sessions as $session => [$startsAt, $endsAt]) {
            $classGroup->sessions()->updateOrCreate(
                ['session' => $session],
                ['starts_at' => $startsAt, 'ends_at' => $endsAt],
            );
        }

        $obsolete = $classGroup->sessions()->whereNotIn('session', array_keys($sessions))->get();
        foreach ($obsolete as $classSession) {
            if (! $classSession->bookings()->exists()) {
                $classSession->delete();
            }
        }
    }

    private function teachers()
    {
        return User::query()
            ->where('role', 'teacher')
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', 'active');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'program']);
    }

    private function visibleClassGroups(User $user)
    {
        $query = ClassGroup::query();

        return match ($user->role) {
            'administrator' => $query,
            'teacher' => $query->where(function ($assigned) use ($user) {
                $assigned->where('teacher_id', $user->id)
                    ->orWhereHas('bookings', fn ($bookings) => $bookings->where('teacher_id', $user->id));
            }),
            'student' => $query->whereHas('students', fn ($students) => $students->where('students.user_id', $user->id)),
            'parent' => $query->whereHas('students', fn ($students) => $students->where('students.parent_id', $user->id)),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
