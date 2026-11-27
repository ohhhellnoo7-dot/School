<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Examination;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\ClassAttendanceReportService;
use App\Services\MonthlyStudentAttendance;
use App\Services\TuitionPaymentCoverage;
use App\Support\AcademicYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as ModelCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class FamilyDashboardController extends Controller
{
    private const TABS = ['overview', 'attendance', 'timetable', 'results', 'fees'];

    public function index(Request $request)
    {
        $guardian = $request->user();
        abort_unless(in_array($guardian->role, User::GUARDIAN_ROLES, true), 403);
        $search = $request->validate(['search' => 'nullable|string|max:255'])['search'] ?? null;
        $family = $guardian->children();
        $childCount = $search ? (clone $family)->count() : null;
        $children = $family->search($search)->with([
            'user', 'schoolClass', 'studyClasses',
            'attendances' => fn ($query) => $query->whereDate('date', today()),
            'grades' => fn ($query) => $query->whereHas('examination')->with('examination.subject')
                ->orderByDesc(Examination::select('date')->whereColumn('examinations.id', 'grades.examination_id'))
                ->orderByDesc('grades.id')->limit(1),
            'fees.adjustments',
        ])->withCount([
            'attendances as recorded_days' => fn ($query) => $query->whereDate('date', '<=', today())
                ->whereIn('status', ['present', 'late', 'absent', 'excused']),
            'attendances as attended_days' => fn ($query) => $query->whereDate('date', '<=', today())
                ->whereIn('status', ['present', 'late']),
        ])->orderBy('name')->orderBy('id')->get();
        $childCount ??= $children->count();

        $classes = $children->mapWithKeys(fn (Student $child) => [$child->id => $this->classes($child)]);
        $owners = $classes->flatten(1)->map->timetableOwnerId()->unique();
        $subjects = Subject::whereIn('school_class_id', $owners)->get(['id', 'school_class_id'])->groupBy('school_class_id');
        $days = TimetableController::DAYS;
        $now = now();
        $dayIndex = $now->dayOfWeekIso <= 5 ? $now->dayOfWeekIso - 1 : 0;
        $upcoming = Schedule::with('subject')->forAcademicYear(AcademicYear::current())->whereIn('school_class_id', $owners)
            ->whereIn('day', $days)->orderBy('starts_at')->get()
            ->sortBy(function ($lesson) use ($days, $dayIndex, $now) {
                $offset = (array_search($lesson->day, $days, true) - $dayIndex + 5) % 5;

                return $lesson->day === $now->format('l') && $lesson->starts_at <= $now->format('H:i:s') ? 5 : $offset;
            })
            ->values();
        $showAllUpdates = $request->boolean('all_updates');
        $updates = Announcement::whereIn('audience', ['all', 'parent'])
            ->whereDate('published_at', '<=', today())->latest('published_at')->orderByDesc('id');
        $announcements = $children->isEmpty() ? collect()
            : ($showAllUpdates ? $updates->paginate(10)->withQueryString() : $updates->limit(3)->get());

        $paymentData = [];
        if ($guardian->role === 'teacher' && $children->isNotEmpty()) {
            $coverage = app(TuitionPaymentCoverage::class);
            $paymentToday = today()->toImmutable();
            $paymentData = [
                'paymentToday' => $paymentToday,
                'paymentCoverage' => $coverage->forChildren($children, $paymentToday),
                'familyFeeTotals' => $coverage->feeTotals($children),
            ];
        }

        $excuseChildren = $search ? $guardian->children()->with('studyClasses')->orderBy('name')->get() : $children;

        return view('school.family.index', compact('guardian', 'children', 'childCount', 'search', 'classes', 'subjects', 'upcoming', 'announcements', 'showAllUpdates', 'excuseChildren') + $paymentData + ['myChildren' => true]);
    }

    public function parentAttendance(Request $request)
    {
        $guardian = $request->user();
        abort_unless($guardian->role === 'parent', 403);
        $filters = $request->validate(['child' => 'nullable|integer|min:1', 'date' => 'nullable|date_format:Y-m-d']);
        $child = $filters['child'] ?? $guardian->children()->orderBy('name')->orderBy('id')->first()?->id;
        if (! $child) {
            return view('school.family.parent-attendance', ['guardian' => $guardian, 'student' => null, 'myChildren' => true]);
        }

        $request->merge(['tab' => 'attendance']);
        if (! $request->filled('month') && isset($filters['date'])) {
            $request->merge(['month' => substr($filters['date'], 0, 7)]);
        }

        return $this->show($request, (int) $child);
    }

    public function show(Request $request, int $child)
    {
        $guardian = $request->user();
        abort_unless(in_array($guardian->role, User::GUARDIAN_ROLES, true), 403);
        $student = $guardian->children()->with(['user', 'schoolClass.teacher', 'studyClasses.teacher'])->findOrFail($child);
        Gate::authorize('view', $student);

        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(self::TABS)],
            'month' => 'nullable|date_format:Y-m',
            'day' => ['nullable', Rule::in(TimetableController::DAYS)],
            'period' => 'nullable|regex:/^\d{4}-[12]$/',
        ]);
        $tab = $filters['tab'] ?? 'overview';
        $parentAttendance = $tab === 'attendance' && $guardian->role === 'parent';
        $weekday = now()->format('l');
        $day = $filters['day'] ?? (in_array($weekday, TimetableController::DAYS, true) ? $weekday : 'Monday');
        $classes = $this->classes($student);
        $owners = $classes->map->timetableOwnerId();
        $siblings = $guardian->children()->orderBy('name')->get(['students.id', 'students.name']);

        $details = match ($tab) {
            'attendance' => $this->attendance($student, $classes, $filters['month'] ?? now()->format('Y-m'), $parentAttendance),
            'timetable' => [
                'days' => TimetableController::DAYS,
                'day' => $day,
                'lessons' => Schedule::with(['subject.teacher', 'teacher', 'classroom', 'schoolClass'])
                    ->forAcademicYear(AcademicYear::current())->whereIn('school_class_id', $owners)
                    ->where('day', $day)->orderBy('starts_at')->get(),
            ],
            'results' => $this->results($student, $filters['period'] ?? ''),
            'fees' => ['fees' => $student->fees()->with(['adjustments', 'payments'])->latest('due_date')->get()],
            default => ['academicYear' => AcademicYear::current()],
        };

        return view($parentAttendance ? 'school.family.parent-attendance' : 'school.family.show',
            compact('guardian', 'student', 'classes', 'siblings', 'tab') + $details + ['myChildren' => true]);
    }

    public function edit(Request $request, int $child)
    {
        $student = $request->user()->children()->with('user')->findOrFail($child);
        Gate::authorize('updateFamilyProfile', $student);

        return view('school.family.edit', ['student' => $student, 'myChildren' => true]);
    }

    public function update(Request $request, int $child)
    {
        $student = $request->user()->children()->findOrFail($child);
        Gate::authorize('updateFamilyProfile', $student);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('students', 'phone')->ignore($student)],
            'address' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        unset($data['photo']);
        $disk = Storage::disk('public');
        $photo = $request->file('photo')?->store('students', 'public');
        abort_if($photo === false, 500, 'Unable to save the profile photo.');

        try {
            $previousPhoto = DB::transaction(function () use ($request, $child, $data, $photo) {
                $student = $request->user()->children()->whereKey($child)->lockForUpdate()->firstOrFail();
                $previousPhoto = $student->photo;
                $student->update($photo ? $data + ['photo' => $photo] : $data);

                return $previousPhoto;
            });
        } catch (\Throwable $exception) {
            if ($photo) $disk->delete($photo);
            throw $exception;
        }

        if ($photo && $previousPhoto) $disk->delete($previousPhoto);

        return redirect()->route('students.family.show', $student)->with('success', __('Child profile updated.'));
    }

    private function classes(Student $student): ModelCollection
    {
        return new ModelCollection(collect([$student->schoolClass])->filter()->concat($student->studyClasses)->unique('id')->all());
    }

    private function attendance(Student $student, ModelCollection $classes, string $month, bool $withTrend): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        $history = $student->attendances()->whereDate('date', '>=', $withTrend ? $start->subMonths(4) : $start)
            ->whereDate('date', '<', $start->addMonth())->latest('date')->get();
        $records = $history->filter(fn ($record) => $record->date->format('Y-m') === $month);
        $counts = $records->countBy('status');
        $recorded = $counts->only(['present', 'late', 'absent', 'excused'])->sum();

        $trend = [];
        if ($withTrend) {
            $months = $history->groupBy(fn ($record) => $record->date->format('Y-m'));
            foreach (range(4, 0) as $offset) {
                $date = $start->subMonths($offset);
                $rows = $months->get($date->format('Y-m'), collect())->whereIn('status', MonthlyStudentAttendance::STATUSES);
                $trend[] = [
                    'month' => $date->translatedFormat('M'),
                    'label' => $date->translatedFormat('F Y'),
                    'rate' => $rows->isEmpty() ? null : round(100 * $rows->whereIn('status', ['present', 'late'])->count() / $rows->count(), 1),
                ];
            }
        }

        return [
            'month' => $start, 'records' => $records->keyBy(fn ($record) => $record->date->toDateString()),
            'counts' => $counts, 'rate' => $recorded ? round(100 * ($counts->get('present', 0) + $counts->get('late', 0)) / $recorded, $withTrend ? 1 : 0) : null,
            'trend' => $trend,
            'classRecords' => $classes->isEmpty() ? collect() : app(ClassAttendanceReportService::class)
                ->forMonth($classes, $student->newCollection([$student]), $start),
        ];
    }

    private function results(Student $student, string $period): array
    {
        $grades = $student->grades()->with('examination.subject')->get()
            ->sortByDesc(fn ($grade) => $grade->examination->date->toDateString());
        $periodKey = fn ($grade) => $grade->examination->date->year.'-'.($grade->examination->date->month <= 6 ? '1' : '2');
        $periods = $grades->map($periodKey)->unique()->values();

        return ['periods' => $periods, 'period' => $period, 'grades' => $period === '' ? $grades : $grades->filter(fn ($grade) => $periodKey($grade) === $period)];
    }
}
