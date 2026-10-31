<?php

namespace App\Http\Controllers;

use App\Models\{Classroom, Schedule, SchoolClass, Student, Subject, User};
use App\Services\{LessonManagementService, RotationTimetableGenerator, TeacherManagementService, TimetableDiagnostics, UnifiedTimetableGenerator};
use App\Support\TimetableGrid;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UnifiedTimetableController extends Controller
{
    private function context(Request $request): array
    {
        $request->validate(['view' => 'nullable|in:teachers,students,classrooms', 'teacher' => 'nullable|integer', 'class' => 'nullable|integer', 'classroom' => 'nullable|integer', 'grade' => 'nullable|integer|between:1,12', 'session' => 'nullable|in:Morning,Afternoon,Evening','academic_year'=>['nullable','regex:/^\d{4}-\d{4}$/']]);
        $restricted = in_array($request->user()->role, ['student', 'parent'], true);
        $classes = SchoolClass::query()->when($restricted, fn ($q) => $q->whereIn('id', Student::visibleTo($request->user())->select('school_class_id')))->orderBy('name')->orderBy('section')->orderBy('session')->get();
        $view = $request->input('view', 'students');
        $studentClasses = null;
        $request->validate(['program' => 'nullable|in:Khmer,English,Chinese']);
        $teacherProgram = $view === 'teachers' ? $request->input('program') : null;
        $teachers = User::where('role', 'teacher')->when($teacherProgram, fn ($q) => $q->where('program', $teacherProgram))->orderBy('name')->get();
        $rooms = Classroom::orderBy('room_number')->get();
        $query = Schedule::with(['subject', 'teacher', 'classroom', 'schoolClass.classGroup']);
        $academicYear = $request->input('academic_year') ?: now('Asia/Phnom_Penh')->year.'-'.(now('Asia/Phnom_Penh')->year+1);
        $query->where(fn ($q)=>$q->where('academic_year',$academicYear)->orWhereNull('academic_year'));
        if ($restricted) $query->whereIn('school_class_id', $classes->map->timetableOwnerId()->unique()->all());
        if ($view === 'teachers') {
            abort_if($restricted, 404);
            $scheduledTeacherIds = (clone $query)->whereIn('teacher_id', $teachers->modelKeys())->pluck('teacher_id');
            $defaultTeacher = $teachers->first(fn ($teacher) => $scheduledTeacherIds->contains($teacher->id)) ?? $teachers->first();
            $requestedTeacher = $request->integer('teacher');
            if ($requestedTeacher && User::withoutGlobalScope('archived')->whereKey($requestedTeacher)->where('role','teacher')->whereNotNull('archived_at')->exists()) $requestedTeacher = null;
            $selected = $requestedTeacher ?: ($request->user()->role === 'teacher' && $teachers->contains('id', $request->user()->id) ? $request->user()->id : $defaultTeacher?->id);
            abort_if($selected && ! $teachers->contains('id', $selected), 404);
            if ($selected) $query->where('teacher_id', $selected); else $query->whereRaw('1 = 0');
        } elseif ($view === 'classrooms') {
            $selected = $request->integer('classroom') ?: $rooms->first()?->id;
            abort_if($selected && ! $rooms->contains('id', $selected), 404);
            if ($restricted) {
                $allowed = \App\Models\ClassroomBooking::whereIn('class_group_id', $classes->pluck('class_group_id'))->pluck('classroom_id')->merge(Schedule::whereIn('school_class_id', $classes->modelKeys())->pluck('classroom_id'));
                abort_unless($allowed->contains($selected), 404);
                $rooms = $rooms->whereIn('id', $allowed);
            }
            $query->where('classroom_id', $selected);
        } else {
            $filtered = $classes->when($request->filled('grade'), fn ($c) => $c->filter(fn ($class) => (int) ($class->classGroup?->grade) === $request->integer('grade')))->when($request->filled('session'), fn ($c) => $c->where('session', $request->input('session')));
            $requestedClass = $request->integer('class');
            abort_if($requestedClass && ! $classes->contains('id', $requestedClass), 404);
            $selected = $requestedClass && $filtered->contains('id',$requestedClass) ? $requestedClass : $filtered->first()?->id;
            $studentClasses = $filtered;
            if ($selected) $query->where('school_class_id',$classes->firstWhere('id',$selected)->timetableOwnerId()); else $query->whereRaw('1 = 0');
        }
        $lessons = $query->orderBy('starts_at')->get();
        $periods = [['07:00','08:00'], ['08:00','09:00'], ['09:00','10:00'], ['10:00','11:00'], ['13:00','14:00'], ['14:00','15:00'], ['15:00','16:00'], ['16:00','17:00'], ['17:30','18:30'], ['18:30','19:30']];
        if ($view === 'teachers' && ($teachers->firstWhere('id', $selected)?->program ?? $teacherProgram) === 'Khmer') {
            $periods = array_slice($periods, 0, 8);
        }
        if ($view === 'students' && ($class = $classes->firstWhere('id', $selected))) {
            [$start,$end] = $class->sessionHours(); $periods = [];
            for ($minute = TeacherManagementService::minute($start); $minute + 60 <= TeacherManagementService::minute($end); $minute += 60) $periods[] = [sprintf('%02d:%02d',intdiv($minute,60),$minute%60),sprintf('%02d:%02d',intdiv($minute+60,60),($minute+60)%60)];
        }
        $breaks = $view === 'teachers' && $selected ? app(TeacherManagementService::class)->breaks(User::findOrFail($selected)) : collect();
        $repair = app(\App\Services\SchoolRoomAssignmentRepair::class);
        $roomIssues = $lessons->mapWithKeys(fn ($entry)=>[$entry->id=>$repair->describe($entry)])->all();
        $curriculum = null;
        if ($view === 'students' && ($cohort = $classes->firstWhere('id',$selected))) {
            $subjects = $cohort->timetableOwner()->subjects;
            $unknown = $subjects->isEmpty() || $subjects->contains(fn ($s)=>$s->required_weekly_minutes === null);
            $missing = $subjects->filter(fn ($s)=>$s->required_weekly_minutes !== null && $lessons->where('subject_id',$s->id)->sum(fn ($entry)=>TeacherManagementService::duration($entry->starts_at,$entry->ends_at)) < $s->required_weekly_minutes)->pluck('name')->all();
            $excess = $subjects->filter(fn ($s)=>$s->required_weekly_minutes !== null && $lessons->where('subject_id',$s->id)->sum(fn ($entry)=>TeacherManagementService::duration($entry->starts_at,$entry->ends_at)) > $s->required_weekly_minutes)->pluck('name')->all();
            $curriculum = ['unknown'=>$unknown,'missing'=>array_values(array_unique($missing)), 'excess'=>array_values(array_unique($excess))];
        }
        return compact('view', 'classes', 'teachers', 'rooms', 'selected', 'lessons', 'periods', 'breaks','roomIssues','curriculum','academicYear','studentClasses','teacherProgram') + TimetableGrid::build($lessons, $periods);
    }

    public function index(Request $request)
    {
        return view('school.timetables.index', $this->context($request));
    }

    public function data(Request $request)
    {
        return response()->json(['html' => view('school.timetables.grid', $this->context($request))->render()])->header('Cache-Control', 'no-store');
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
        return view('school.timetables.lesson-form', ['lesson' => $lesson, 'classes' => SchoolClass::orderBy('name')->get(), 'subjects' => Subject::orderBy('name')->get(), 'teachers' => User::where('role', 'teacher')->where('status', 'active')->get(), 'rooms' => Classroom::where('status', 'active')->get()]);
    }

    public function store(Request $request, LessonManagementService $service)
    {
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
        $teachers = User::where('role', 'teacher')->where('status', 'active')->get()->filter(function ($teacher) use ($rules, $subject, $data, $lesson) {
            return $rules->qualifications($teacher)->contains($subject->name)
                && Subject::where('teacher_id', $teacher->id)->pluck('name')->concat($rules->lessons($teacher)->pluck('subject.name'))->push($subject->name)->filter()->unique()->count() <= $rules->subjectLimit($teacher)
                && $rules->lessons($teacher)->where('id', '!=', $lesson->id)->filter(fn ($s)=>\App\Support\AcademicYear::overlaps($s->academic_year,$data['academic_year']??null))->sum(fn ($s) => $rules::duration($s->starts_at, $s->ends_at)) + $rules::duration($data['starts_at'], $data['ends_at']) <= $rules->limit($teacher)
                && $rules->onDuty($teacher, $data['day'], $data['starts_at'], $data['ends_at'])
                && ! $rules->breaks($teacher)->contains(fn ($b) => $b->weekday === $data['day'] && $rules::overlaps($b->start_time, $b->end_time, $data['starts_at'], $data['ends_at']))
                && ! Schedule::where('teacher_id', $teacher->id)->where('day', $data['day'])->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->when(!empty($data['academic_year']),fn ($q)=>$q->where(fn ($year)=>$year->where('academic_year',$data['academic_year'])->orWhereNull('academic_year')))->when($lesson->id, fn ($q) => $q->where('id', '!=', $lesson->id))->exists();
        });
        return response()->json(['teachers' => $teachers->map->only(['id','name'])->values(), 'rooms' => $rooms->map->only(['id','room_number'])->values()]);
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
        return redirect()->route('timetables.index')->with('success', 'Approved Khmer timetable published. Backup: '.$backup);
    }

    public function conflicts(Schedule $lesson)
    {
        $all = Schedule::with(['teacher','subject','schoolClass','classroom'])->where('day', $lesson->day)->where('starts_at', '<', $lesson->ends_at)->where('ends_at', '>', $lesson->starts_at)->get();
        return view('school.timetables.conflicts', compact('lesson', 'all'));
    }
}
