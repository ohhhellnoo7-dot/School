<?php

namespace App\Http\Controllers;

use App\Models\{Classroom, Schedule, SchoolClass, Student, Subject, User};
use App\Services\{LessonManagementService, RotationTimetableGenerator, TeacherManagementService, TimetableDiagnostics, UnifiedTimetableGenerator};
use App\Support\{AcademicYear, TimetableGrid};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UnifiedTimetableController extends Controller
{
    private function context(Request $request): array
    {
        $request->validate(['view' => 'nullable|in:teachers,students,classrooms', 'teacher' => 'nullable|integer', 'class' => 'nullable|integer', 'classroom' => 'nullable|integer', 'grade' => 'nullable|integer|between:1,12', 'session' => 'nullable|in:Morning,Afternoon,Evening','academic_year'=>['nullable','regex:/^\d{4}-\d{4}$/']]);
        $user = $request->user();
        $restricted = in_array($user->role, ['student', 'parent'], true);
        $teacherId = $user->role === 'teacher' ? $user->id : null;
        $academicYear = $request->input('academic_year') ?: AcademicYear::current();
        $query = Schedule::with(['subject', 'teacher', 'classroom', 'schoolClass.classGroup'])
            ->forAcademicYear($academicYear);
        $classQuery = SchoolClass::query()->when($restricted, fn ($q) => $q->whereIn('id', SchoolClass::enrolledFor($user)->select('school_classes.id')));
        if ($teacherId) {
            $query->forTeachingTimetable($user);
            $scheduledClasses = (clone $query)->select('school_class_id');
            $classQuery->where(function ($q) use ($scheduledClasses) {
                $q->whereIn('id', $scheduledClasses)
                    ->orWhereIn('shared_timetable_class_id', $scheduledClasses);
            });
        }
        $classes = $classQuery->orderBy('name')->orderBy('section')->orderBy('session')->get();
        $view = $request->input('view', $teacherId ? 'teachers' : 'students');
        $legacyTeacherView = $teacherId && $view === 'students';
        if ($legacyTeacherView) {
            $view = 'teachers';
        }
        $studentClasses = null;
        $programs = \App\Models\Program::names();
        $request->validate(['program' => ['nullable', \Illuminate\Validation\Rule::in($programs)]]);
        if ($user->role === 'student') {
            $programs = $classes->pluck('program')->filter()->unique()->values()->all();
        }
        $timetableProgram = $teacherId ? $user->program : $request->input('program');
        $teacherProgram = $view === 'teachers' ? $timetableProgram : null;
        $request->validate(['subject' => 'nullable|string|max:255']);
        $subjectSearch = $teacherId || $timetableProgram === 'Khmer' ? $request->input('subject') : null;
        $subjectChoices = match (true) {
            (bool) $teacherId => Subject::whereIn('id', (clone $query)->select('subject_id'))->distinct()->orderBy('name')->pluck('name'),
            $timetableProgram === 'Khmer' => Subject::whereHas('schoolClass', fn ($q) => $q->where('program', 'Khmer'))
                ->when($user->role === 'student', fn ($q) => $q->whereIn('school_class_id', $classes->map->timetableOwnerId()->unique()))
                ->distinct()->orderBy('name')->pluck('name'),
            default => collect(),
        };
        if ($teacherId && $subjectSearch) {
            $query->whereHas('subject', fn ($q) => $q->where('name', $subjectSearch));
        }
        $availableTeacherSessions = \App\Support\ProgramSessions::for($teacherProgram);
        $teacherSession = $teacherProgram && ! $legacyTeacherView ? $request->input('session') : null;
        if ($teacherSession) $request->validate(['session' => \Illuminate\Validation\Rule::in($availableTeacherSessions)]);
        $teachers = User::where('role', 'teacher')->when($teacherId, fn ($q) => $q->whereKey($teacherId))->when($teacherProgram, fn ($q) => $q->where('program', $teacherProgram))->orderBy('name')->get();
        if (! $teacherId && $timetableProgram && $view !== 'teachers') $query->whereHas('schoolClass', fn ($q) => $q->where('program', $timetableProgram));
        $rooms = Classroom::when($teacherId, fn ($q) => $q->whereIn('id', (clone $query)->select('classroom_id')))
            ->when(! $teacherId && $timetableProgram, fn ($q) => $q->where(function ($q) use ($timetableProgram) {
            $q->whereHas('bookings.classGroup', fn ($q) => $q->where('program', $timetableProgram))
                ->orWhereHas('lessons.schoolClass', fn ($q) => $q->where('program', $timetableProgram));
        }))->orderBy('room_number')->get();
        if ($restricted) $query->whereIn('school_class_id', $classes->map->timetableOwnerId()->unique()->all());
        if ($view === 'teachers') {
            abort_if($restricted, 404);
            $programTeacherIds = $teachers->modelKeys();
            if ($teacherId && $teacherSession) {
                $query->whereHas('schoolClass', fn ($q) => $q->where('session', $teacherSession));
            }
            if (! $teacherId && ($teacherSession || $subjectSearch)) {
                if ($teacherSession) $query->whereHas('schoolClass', fn ($q) => $q->where('session', $teacherSession));
                $matchingLessons = clone $query;
                if ($subjectSearch) $matchingLessons->whereHas('subject', fn ($q) => $q->where('name', $subjectSearch));
                $sessionTeacherIds = $matchingLessons->pluck('teacher_id');
                if ($subjectSearch) {
                    // Include assigned subject teachers even before their lessons
                    // have been scheduled for the selected academic year.
                    $assignedTeacherIds = Subject::where('name', $subjectSearch)
                        ->whereHas('schoolClass', function ($q) use ($teacherProgram, $teacherSession) {
                            $q->where('program', $teacherProgram);
                            if ($teacherSession) $q->where('session', $teacherSession);
                        })->whereNotNull('teacher_id')->pluck('teacher_id');
                    $sessionTeacherIds = $sessionTeacherIds->merge($assignedTeacherIds)->unique();
                }
                $teachers = $teachers->whereIn('id', $sessionTeacherIds)->values();
            }
            $scheduledTeacherIds = (clone $query)->whereIn('teacher_id', $teachers->modelKeys())->pluck('teacher_id');
            $defaultTeacher = $teachers->first(fn ($teacher) => $scheduledTeacherIds->contains($teacher->id)) ?? $teachers->first();
            $requestedTeacher = $request->integer('teacher');
            abort_if($teacherId && $requestedTeacher && $requestedTeacher !== $teacherId, 404);
            if ($requestedTeacher && User::withoutGlobalScope('archived')->whereKey($requestedTeacher)->where('role','teacher')->whereNotNull('archived_at')->exists()) $requestedTeacher = null;
            if (($teacherSession || $subjectSearch) && $requestedTeacher) {
                abort_unless(in_array($requestedTeacher, $programTeacherIds, true), 404);
                if (! $teachers->contains('id', $requestedTeacher)) $requestedTeacher = null;
            }
            $selected = $requestedTeacher ?: ($request->user()->role === 'teacher' && $teachers->contains('id', $request->user()->id) ? $request->user()->id : $defaultTeacher?->id);
            abort_if($selected && ! $teachers->contains('id', $selected), 404);
            if ($selected) $query->where('teacher_id', $selected); else $query->whereRaw('1 = 0');
        } elseif ($view === 'classrooms') {
            $requestedRoom = $request->integer('classroom');
            if ($restricted) {
                $allowed = \App\Models\ClassroomBooking::whereIn('class_group_id', $classes->pluck('class_group_id'))->pluck('classroom_id')->merge(Schedule::whereIn('school_class_id', $classes->modelKeys())->pluck('classroom_id'));
                abort_if($requestedRoom && ! $allowed->contains($requestedRoom), 404);
                $rooms = $rooms->whereIn('id', $allowed);
            }
            $selected = $rooms->contains('id', $requestedRoom) ? $requestedRoom : $rooms->first()?->id;
            if ($selected) $query->where('classroom_id', $selected); else $query->whereRaw('1 = 0');
        } else {
            $filtered = $classes->when($timetableProgram, fn ($c) => $c->where('program', $timetableProgram))->when($request->filled('grade'), fn ($c) => $c->filter(fn ($class) => (int) ($class->classGroup?->grade) === $request->integer('grade')))->when($request->filled('session'), fn ($c) => $c->where('session', $request->input('session')));
            $requestedClass = $request->integer('class');
            abort_if($requestedClass && ! $classes->contains('id', $requestedClass), 404);
            $selected = $requestedClass && $filtered->contains('id', $requestedClass) ? $requestedClass : $filtered->first()?->id;
            $studentClasses = $filtered;
            if ($selected) $query->where('school_class_id',$classes->firstWhere('id',$selected)->timetableOwnerId()); else $query->whereRaw('1 = 0');
        }
        $lessons = $query->orderBy('starts_at')->get();
        $periods = [['07:00','08:00'], ['08:00','09:00'], ['09:00','10:00'], ['10:00','11:00'], ['13:00','14:00'], ['14:00','15:00'], ['15:00','16:00'], ['16:00','17:00'], ['17:30','18:30'], ['18:30','19:30']];
        $scheduleProgram = $view === 'teachers'
            ? ($teachers->firstWhere('id', $selected)?->program ?? $teacherProgram)
            : $timetableProgram;
        if ($scheduleProgram) {
            $configuredSessions = \App\Support\ProgramSessions::for($scheduleProgram);
            $periods = array_values(array_filter($periods, function ($period) use ($configuredSessions) {
                $session = $period[0] < '12:00' ? 'Morning' : ($period[0] < '17:00' ? 'Afternoon' : 'Evening');
                return in_array($session, $configuredSessions, true);
            }));
        }
        if ($view === 'teachers') {
            if (($selectedTeacher = $teachers->firstWhere('id', $selected))?->program === 'English') {
                $workingPeriods = app(TeacherManagementService::class)->periods($selectedTeacher);
                if ($workingPeriods->isNotEmpty()) {
                    $periods = array_values(array_filter($periods, function ($period) use ($workingPeriods, $lessons) {
                        [$from, $to] = $period;
                        // Keep existing lessons visible even when a profile needs correction.
                        if ($lessons->contains(fn ($lesson) => TeacherManagementService::overlaps($from, $to, $lesson->starts_at, $lesson->ends_at))) return true;
                        return $workingPeriods->contains(fn ($p) => ! $p->unavailable
                            && TeacherManagementService::minute($p->start_time) <= TeacherManagementService::minute($from)
                            && TeacherManagementService::minute($p->end_time) >= TeacherManagementService::minute($to)
                            && ! $workingPeriods->contains(fn ($blocked) => $blocked->unavailable && $blocked->weekday === $p->weekday && TeacherManagementService::overlaps($from, $to, $blocked->start_time, $blocked->end_time)));
                    }));
                }
            }
        }
        if ($view === 'students' && ($class = $classes->firstWhere('id', $selected))) {
            [$start,$end] = $class->sessionHours(); $periods = [];
            for ($minute = TeacherManagementService::minute($start); $minute + 60 <= TeacherManagementService::minute($end); $minute += 60) $periods[] = [sprintf('%02d:%02d',intdiv($minute,60),$minute%60),sprintf('%02d:%02d',intdiv($minute+60,60),($minute+60)%60)];
        }
        if ($teacherSession) {
            $periods = array_values(array_filter($periods, fn ($p) => match ($teacherSession) {
                'Morning' => $p[0] < '12:00',
                'Afternoon' => $p[0] >= '12:00' && $p[0] < '17:00',
                'Evening' => $p[0] >= '17:00',
            }));
        }
        $breaks = $view === 'teachers' && $selected ? app(TeacherManagementService::class)->breaks(User::findOrFail($selected)) : collect();
        $repair = app(\App\Services\SchoolRoomAssignmentRepair::class);
        $roomIssues = $user->role === 'student' ? [] : $lessons->mapWithKeys(fn ($entry)=>[$entry->id=>$repair->describe($entry)])->all();
        $curriculum = null;
        if ($user->role !== 'student' && ! $teacherId && $view === 'students' && ($cohort = $classes->firstWhere('id',$selected))) {
            $subjects = $cohort->timetableOwner()->subjects;
            $unknown = $subjects->isEmpty() || $subjects->contains(fn ($s)=>$s->required_weekly_minutes === null);
            $missing = $subjects->filter(fn ($s)=>$s->required_weekly_minutes !== null && $lessons->where('subject_id',$s->id)->sum(fn ($entry)=>TeacherManagementService::duration($entry->starts_at,$entry->ends_at)) < $s->required_weekly_minutes)->pluck('name')->all();
            $excess = $subjects->filter(fn ($s)=>$s->required_weekly_minutes !== null && $lessons->where('subject_id',$s->id)->sum(fn ($entry)=>TeacherManagementService::duration($entry->starts_at,$entry->ends_at)) > $s->required_weekly_minutes)->pluck('name')->all();
            $curriculum = ['unknown'=>$unknown,'missing'=>array_values(array_unique($missing)), 'excess'=>array_values(array_unique($excess))];
        }
        return compact('subjectSearch', 'subjectChoices', 'programs', 'timetableProgram', 'view', 'classes', 'teachers', 'rooms', 'selected', 'lessons', 'periods', 'breaks','roomIssues','curriculum','academicYear','studentClasses','teacherProgram','teacherSession','availableTeacherSessions','teacherId') + TimetableGrid::build($lessons, $periods);
    }

    public function index(Request $request)
    {
        return view('school.timetables.index', $this->context($request));
    }

    public function data(Request $request)
    {
        $context = $this->context($request);
        $template = match (true) {
            $request->boolean('fragment') => 'school.timetables._schedule',
            $request->boolean('compact') && $context['view'] === 'teachers' => 'school.timetables.compact-teacher',
            default => 'school.timetables.grid',
        };
        return response()->json(['html' => view($template, $context)->render()])->header('Cache-Control', 'no-store');
    }

    public function diagnostics()
    {
        return view('school.timetables.diagnostics', ['report' => app(TimetableDiagnostics::class)->report()]);
    }

    public function setupCurriculum(Request $request)
    {
        $request->validate(['use_school_defaults' => 'required|accepted']);
        $count = app(TeacherManagementService::class)->locked(function () {
            $classes = SchoolClass::whereDoesntHave('subjects')->orWhereHas('subjects', fn ($q) => $q->whereNull('required_weekly_minutes'))->get();
            if ($classes->isEmpty()) return 0;
            $backup = app(\App\Services\TimetableBackup::class)->create();
            $changes = $classes->map(fn ($class) => app(\App\Services\CurriculumSetupService::class)->configure($class));
            $path = 'timetable-drafts/curriculum-setup-'.Str::uuid().'.json';
            if (! \Illuminate\Support\Facades\Storage::disk('local')->put($path, json_encode(['backup'=>$backup,'classes'=>$changes,'source'=>'administrator_school_defaults'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) throw new \RuntimeException('Could not save curriculum setup report.');
            return $classes->count();
        });
        return back()->with('success', $count ? 'Weekly hours configured for '.$count.' class sessions using school defaults. Review Scheduling Settings before generating remaining lessons.' : 'Weekly hours are already configured for every class.');
    }

    public function schoolAudit(Request $request)
    {
        $report = app(\App\Services\SchoolTimetableAudit::class)->report();
        if ($request->input('format') === 'json') return response()->json($report)->header('Cache-Control','no-store');
        return view('school.timetables.school-audit',compact('report'));
    }

    public function form(Request $request, ?Schedule $lesson = null)
    {
        if (! $lesson) {
            return view('school.timetables.week-planner', ['classes' => SchoolClass::with('classGroup.sessions')->orderBy('name')->get(), 'subjects' => Subject::where(fn ($q) => $q->whereNull('required_weekly_minutes')->orWhere('required_weekly_minutes', '>', 0))->orderBy('name')->get()]);
        }
        return view('school.timetables.lesson-form', ['lesson' => $lesson, 'classes' => SchoolClass::with('classGroup.sessions')->orderBy('name')->get(), 'subjects' => Subject::where(fn ($q) => $q->whereNull('required_weekly_minutes')->orWhere('required_weekly_minutes', '>', 0))->orderBy('name')->get(), 'teachers' => User::where('role', 'teacher')->where('status', 'active')->get(), 'rooms' => Classroom::where('status', 'active')->get()]);
    }

    public function autoFill(Request $request, \App\Services\KhmerWeekPlanner $planner)
    {
        $data = $request->validate(['school_class_id' => 'required|integer|exists:school_classes,id', 'academic_year' => ['required', 'regex:/^\d{4}-\d{4}$/']]);
        return response()->json($planner->preview(SchoolClass::findOrFail($data['school_class_id']), $data['academic_year']))->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, LessonManagementService $service)
    {
        if ($request->has('slots')) {
            $data = $request->validate(['slots' => 'required|array|min:1|max:100', 'slots.*' => 'required|array', 'school_class_id' => 'required|integer|exists:school_classes,id', 'academic_year' => ['required','regex:/^\d{4}-\d{4}$/'], 'room_change_reason' => 'nullable|string|max:500']);
            app(TeacherManagementService::class)->locked(function () use ($data, $service) {
                foreach ($data['slots'] as $slot) {
                    $service->save(array_merge($slot, \Illuminate\Support\Arr::only($data, ['school_class_id', 'academic_year', 'room_change_reason'])));
                }
            });
            return redirect()->route('timetables.index', ['class' => $data['school_class_id']])->with('success', 'Planned lessons saved in all three schedules.');
        }
        $service->save($request->all());
        return redirect()->route('timetables.index', ['class' => $request->input('school_class_id')])->with('success', 'Lesson saved in all three schedules.');
    }

    public function update(Request $request, Schedule $lesson, LessonManagementService $service)
    {
        $request->validate(['confirm' => 'required|accepted', 'version' => 'required|string']);
        $service->save($request->all(), $lesson);
        return redirect()->route('timetables.index', ['class' => $lesson->school_class_id])->with('success', 'Shared lesson updated.');
    }

    public function cancel(Request $request, Schedule $lesson, LessonManagementService $service)
    {
        $request->validate(['confirm' => 'required|accepted', 'version' => 'required|string']);
        $service->cancel($lesson, $request->input('version'));
        return back()->with('success', 'Lesson cancelled in all views. History and room reservations retained.');
    }

    public function options(Request $request, LessonManagementService $service)
    {
        $request->validate(['school_class_id' => 'required|integer|exists:school_classes,id', 'subject_id' => 'required|integer|exists:subjects,id', 'day' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday', 'starts_at' => 'required|date_format:H:i', 'ends_at' => 'required|date_format:H:i', 'ignore_lesson' => 'nullable|integer|exists:schedules,id','academic_year'=>['nullable','regex:/^\d{4}-\d{4}$/']]);
        $data = $request->all();
        $lesson = $request->filled('ignore_lesson') ? Schedule::findOrFail($request->integer('ignore_lesson')) : new Schedule;
        $subject = Subject::findOrFail($request->integer('subject_id'));
        $selectedClass = SchoolClass::findOrFail($data['school_class_id']);
        if ($subject->school_class_id !== $selectedClass->id) throw ValidationException::withMessages(['subject_id' => 'Choose a subject belonging to the selected class.']);
        if ($selectedClass->shared_timetable_class_id && $subject->school_class_id === $selectedClass->id) {
            $owner = $selectedClass->timetableOwner();
            $data['school_class_id'] = $owner->id;
            $subject = $owner->subjects()->where('name', $subject->name)->firstOrFail();
        }
        $rooms = Classroom::where('status', 'active')->get()->filter(function ($room) use ($service, $data, $lesson, $subject) {
            $slot = clone $lesson;
            $slot->fill(array_intersect_key($data, array_flip(['school_class_id','day','starts_at','ends_at','academic_year'])) + ['classroom_id' => $room->id, 'room' => $room->room_number, 'entry_type' => 'academic', 'subject_id' => $subject->id, 'teacher_id' => null]);
            try { $service->validate($slot, false); return true; } catch (ValidationException $e) { return false; }
        });
        $rules = app(TeacherManagementService::class);
        $teacherStatuses = User::where('role', 'teacher')->where('status', 'active')->where('program', $selectedClass->program)->orderBy('name')->get()->map(function ($teacher) use ($rules, $service, $subject, $data, $lesson, $rooms) {
            if (! $rules->qualifications($teacher)->contains($subject->name)) return null;
            $roomIds = [];
            $reason = 'No available classroom fits this class and period.';
            // Room/cohort constraints were checked above. Changing the teacher
            // adds only teacher constraints, which do not depend on the room.
            if ($room = $rooms->first()) {
                $slot = clone $lesson;
                $slot->fill(array_intersect_key($data, array_flip(['school_class_id','day','starts_at','ends_at','academic_year'])) + ['classroom_id' => $room->id, 'room' => $room->room_number, 'entry_type' => 'academic', 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
                try { $service->validate($slot); $roomIds = $rooms->pluck('id')->all(); } catch (ValidationException $e) { $reason = $e->getMessage(); }
            }
            return ['id' => $teacher->id, 'name' => $teacher->name, 'program' => $teacher->program, 'room_ids' => $roomIds, 'available' => count($roomIds) > 0, 'reason' => $roomIds ? null : $reason];
        })->filter()->values();
        $teachers = $teacherStatuses->where('available', true)->values();
        return response()->json(['teachers' => $teachers, 'teacher_statuses' => $teacherStatuses, 'rooms' => $rooms->map->only(['id','room_number'])->values()])->header('Cache-Control', 'no-store');
    }

    public function preview(Request $request, UnifiedTimetableGenerator $generator)
    {
        $data = $request->validate(['classes' => 'nullable|array', 'classes.*' => 'integer|exists:school_classes,id', 'academic_year' => ['nullable','regex:/^\d{4}-\d{4}$/']]);
        if (empty($data['classes'])) {
            $draft = app(\App\Services\KhmerTimetableDraft::class)->prepare($data['academic_year'] ?? null);
            if ($draft['status'] === 'blocked') {
                $request->session()->forget('unified_timetable_preview');
                return view('school.timetables.blocked', compact('draft'));
            }
            $plan = $draft['plan'];
        } else $plan = $generator->preview($data['classes'], $data['academic_year'] ?? null);
        $token = (string) Str::uuid();
        $request->session()->put('unified_timetable_preview', ['plan' => $plan, 'token' => $token, 'created' => time()]);
        return view('school.timetables.preview', compact('plan', 'token'));
    }

    public function confirm(Request $request, UnifiedTimetableGenerator $generator)
    {
        $request->validate(['token' => 'required|string', 'confirm' => 'required|accepted']);
        $preview = $request->session()->get('unified_timetable_preview');
        if (! $preview || time() - $preview['created'] > 1800 || ! hash_equals($preview['token'], $request->input('token'))) app(TeacherManagementService::class)->fail('Preview expired or already used. Generate another.');
        if ($preview['plan']['replace']) $request->validate(['replace' => 'required|accepted']);
        $backup = $generator->confirm($preview['plan']);
        $request->session()->forget('unified_timetable_preview');
        return redirect()->route('timetables.index')->with('success', 'Khmer timetable published. Curriculum approval remains separate. Backup: '.$backup);
    }

    public function conflicts(Schedule $lesson)
    {
        $all = Schedule::with(['teacher','subject','schoolClass','classroom'])->where('day', $lesson->day)->where('starts_at', '<', $lesson->ends_at)->where('ends_at', '>', $lesson->starts_at)->get();
        return view('school.timetables.conflicts', compact('lesson', 'all'));
    }
}
