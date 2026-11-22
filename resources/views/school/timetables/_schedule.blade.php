@unless(auth()->user()->role === 'parent')
<nav class="timetable-view-tabs d-flex gap-2 mb-3 flex-wrap" aria-label="{{ __('Schedule views') }}">
    @if($teacherId)
    <a class="btn {{ $view === 'teachers' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('timetables.index', ['view'=>'teachers','academic_year'=>$academicYear,'subject'=>$subjectSearch]) }}" @if($view === 'teachers') aria-current="page" @endif><i class="bi bi-person-workspace" aria-hidden="true"></i> {{ __('Teacher schedule') }}</a>
    @elseif(!in_array(auth()->user()->role, ['student','parent']))
    <div class="dropdown" data-teacher-program-menu>
        <button type="button" class="btn dropdown-toggle {{ $view === 'teachers' ? 'btn-primary' : 'btn-outline-secondary' }}" id="teacher-program-button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-workspace" aria-hidden="true"></i> {{ $teacherProgram ? __($teacherProgram).' · '.__('Teachers') : __('Teachers') }}</button>
        <ul class="dropdown-menu" aria-labelledby="teacher-program-button">
            @foreach($programs as $program)
            <li><a class="dropdown-item {{ $teacherProgram === $program ? 'active' : '' }}" href="{{ route('timetables.index', ['view'=>'teachers','program'=>$program,'academic_year'=>$academicYear]) }}" @if($teacherProgram === $program) aria-current="page" @endif>{{ __($program) }} · {{ __('Teachers') }}</a></li>
            @endforeach
        </ul>
    </div>
    @endif
    @foreach(['students'=>'Students','classrooms'=>'Classrooms'] as $key=>$label)
    @continue($key === 'students' && auth()->user()->role === 'teacher')
    <a class="btn {{ $view === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('timetables.index', ['view'=>$key,'academic_year'=>$academicYear,'program'=>$timetableProgram,'subject'=>$teacherId ? $subjectSearch : null]) }}" @if($view === $key) aria-current="page" @endif><i class="bi bi-{{ $key === 'students' ? 'people' : 'display' }}" aria-hidden="true"></i> {{ __($label) }}</a>
    @endforeach
</nav>
<form class="panel timetable-filters p-3 mb-3 d-flex gap-3 flex-wrap align-items-end" method="get" action="{{ route('timetables.index') }}"><input type="hidden" name="view" value="{{ $view }}"><label><span class="timetable-filter-label"><i class="bi bi-calendar-event" aria-hidden="true"></i>{{ __('Academic year') }}</span><input class="form-control" name="academic_year" pattern="[0-9]{4}-[0-9]{4}" value="{{ $academicYear }}"></label>
@if($teacherId)
    @if($timetableProgram)
    <div class="timetable-filter-field"><span class="timetable-filter-label"><i class="bi bi-layers" aria-hidden="true"></i>{{ __('Program') }}</span><span class="form-control d-flex align-items-center">{{ __($timetableProgram) }}</span></div>
    @endif
@else
<label><span class="timetable-filter-label"><i class="bi bi-layers" aria-hidden="true"></i>{{ __('Program') }}</span><select class="form-select" name="program"><option value="">{{ __('All programs') }}</option>@foreach($programs as $program)<option value="{{ $program }}" @selected($timetableProgram === $program)>{{ __($program) }}</option>@endforeach</select></label>
@endif
@if($teacherId || $timetableProgram === 'Khmer')
<label><span class="timetable-filter-label"><i class="bi bi-book" aria-hidden="true"></i>{{ __('Subject') }}</span><select class="form-select" name="subject"><option value="">{{ __('All subjects') }}</option>@foreach($subjectChoices as $subjectName)<option value="{{ $subjectName }}" @selected($subjectSearch === $subjectName)>{{ $subjectName }}</option>@endforeach</select></label>
@endif
@if($view === 'teachers')
<label><span class="timetable-filter-label"><i class="bi bi-person-workspace" aria-hidden="true"></i>{{ $teacherProgram ? $teacherProgram.' teacher' : 'Teacher' }}</span><select class="form-select" name="teacher">@forelse($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($selected === $teacher->id)>{{ $teacher->name }} · {{ $teacher->program ?: 'Program not set' }}{{ $teacher->requiresTeacherConfirmation() ? ' (unverified)' : '' }}</option>@empty<option value="" disabled selected>{{ $teacherSession ? 'No teachers in this session' : 'No teachers in this program' }}</option>@endforelse</select></label>
@if($teacherProgram)
@if($teacherSession)<input type="hidden" name="session" value="{{ $teacherSession }}">@endif
<div class="dropdown timetable-filter-field" data-teacher-session-menu>
    <span id="teacher-session-label" class="timetable-filter-label"><i class="bi bi-clock" aria-hidden="true"></i>{{ __('Session') }}</span>
    <button type="button" class="form-select text-start" id="teacher-session-button" data-bs-toggle="dropdown" aria-expanded="false" aria-labelledby="teacher-session-label teacher-session-button">{{ __($teacherSession ?: 'All sessions') }}</button>
    <ul class="dropdown-menu" aria-labelledby="teacher-session-button">
        @foreach(['' => 'All sessions'] + array_combine($availableTeacherSessions, $availableTeacherSessions) as $sessionValue => $sessionLabel)
        <li><a class="dropdown-item {{ ($teacherSession ?? '') === $sessionValue ? 'active' : '' }}" href="{{ route('timetables.index', ['view'=>'teachers', 'program'=>$teacherProgram, 'academic_year'=>$academicYear, 'teacher'=>$selected, 'session'=>$sessionValue ?: null, 'subject'=>$subjectSearch]) }}" @if(($teacherSession ?? '') === $sessionValue) aria-current="page" @endif>{{ __($sessionLabel) }}</a></li>
        @endforeach
    </ul>
</div>
@endif
@elseif($view === 'classrooms')<label><span class="timetable-filter-label"><i class="bi bi-building" aria-hidden="true"></i>{{ __('Physical classroom') }}</span><select class="form-select" name="classroom">@forelse($rooms as $room)<option value="{{ $room->id }}" @selected($selected === $room->id)>{{ $room->room_number }} — {{ $room->name }}</option>@empty<option value="">{{ __('No classrooms in this program') }}</option>@endforelse</select></label>
@else<label><span class="timetable-filter-label"><i class="bi bi-mortarboard" aria-hidden="true"></i>{{ __('Grade') }}</span><select class="form-select" name="grade"><option value="">{{ __('All grades') }}</option>@foreach(range(1,12) as $grade)<option @selected(request('grade') == $grade)>{{ $grade }}</option>@endforeach</select></label><label><span class="timetable-filter-label"><i class="bi bi-people" aria-hidden="true"></i>{{ __('Class and cohort') }}</span><select class="form-select" name="class">@foreach($studentClasses ?? $classes as $class)<option value="{{ $class->id }}" @selected($selected === $class->id)>{{ $class->label }}</option>@endforeach</select></label><label><span class="timetable-filter-label"><i class="bi bi-clock" aria-hidden="true"></i>{{ __('Session') }}</span><select class="form-select" name="session"><option value="">{{ __('All sessions') }}</option>@foreach(['Morning','Afternoon','Evening'] as $session)<option @selected(request('session') === $session)>{{ $session }}</option>@endforeach</select></label>@endif</form>
@endunless
@if(auth()->user()->role === 'parent')
    @include('school.timetables._parent-metrics')
    <nav class="parent-timetable-actions" aria-label="{{ __('Schedule actions') }}">
        <a class="btn btn-primary" href="#parent-timetable-today"><i class="bi bi-calendar2-check" aria-hidden="true"></i> {{ __('Today’s Schedule') }}</a>
        <button class="btn" type="button" data-copy-parent-schedule><i class="bi bi-copy" aria-hidden="true"></i> {{ __('Copy Schedule') }}</button>
        <button class="btn" type="button" data-download-parent-schedule><i class="bi bi-download" aria-hidden="true"></i> {{ __('Export CSV') }}</button>
        <button class="btn" type="button" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> {{ __('Print Schedule') }}</button>
        <a class="btn" href="{{ route('students.index') }}"><i class="bi bi-people" aria-hidden="true"></i> {{ __('My Children') }}</a>
        <a class="btn" href="#parent-timetable-summary"><i class="bi bi-bar-chart" aria-hidden="true"></i> {{ __('Quick Summary') }}</a>
    </nav>
@endif
@if($view === 'teachers' && ($selectedTeacher = $teachers->firstWhere('id', $selected))?->program === 'English')
@php($workingMinutes = app(\App\Services\TeacherManagementService::class)->periods($selectedTeacher)->where('unavailable', false)->sum(fn ($p) => \App\Services\TeacherManagementService::duration($p->start_time, $p->end_time)))
<div class="panel p-3 mb-3 d-flex gap-3 align-items-center flex-wrap">
    <strong>{{ $workingMinutes === 600 ? 'Part-time: 2 hours per day / 10 hours per week' : ($workingMinutes === 2400 ? 'Full-time: 8 hours per day / 40 hours per week' : 'Custom working hours') }}</strong>
    <span>One class and classroom per session. Teachers may change classes between sessions.</span>
    @if($selectedTeacher->employee_confirmed_at)<span class="badge text-bg-success">Administrator verified</span>@endif
    @if(auth()->user()->role === 'administrator')<a href="{{ route('teacher-management.edit', $selectedTeacher) }}">Edit working hours</a>@endif
</div>
@endif
@if($parentSchedule)
    <div class="parent-timetable-board">
@endif
<section class="timetable-calendar" aria-label="{{ __('Weekly timetable') }}">
    <header class="timetable-calendar-toolbar"><div><span class="timetable-calendar-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span><div><h2>{{ __('Weekly schedule') }}</h2><p>{{ __('Monday–Friday') }} <span>·</span> {{ $academicYear }}</p></div></div>@include('school.timetables._schedule-details')<div class="timetable-calendar-tools">@if($parentSchedule)
            <span class="timetable-refresh-note" data-parent-timetable-updated><span aria-hidden="true"></span>{{ __('Updated') }} <time datetime="{{ now()->toIso8601String() }}">{{ now('Asia/Phnom_Penh')->format('H:i') }}</time></span>
        @else
            <span class="timetable-refresh-note"><span></span>{{ __('Updates every 30 seconds') }}</span>
        @endif @unless($parentSchedule)<button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> {{ __('Print Schedule') }}</button>@endunless</div>
        @if(auth()->user()->role === 'administrator')
            <div class="timetable-reset-row w-100 d-print-none">
                @include('school.timetables._reset-controls')
            </div>
        @endif
    </header>
    @if(auth()->user()->role === 'parent')
        <div class="parent-timetable-controls">
            <div class="parent-timetable-display" role="group" aria-label="{{ __('Timetable display') }}">
                <button type="button" class="btn" data-schedule-display-option="weekly" aria-pressed="true"><i class="bi bi-calendar-week" aria-hidden="true"></i> {{ __('Weekly View') }}</button>
                <button type="button" class="btn" data-schedule-display-option="list" aria-pressed="false"><i class="bi bi-list-ul" aria-hidden="true"></i> {{ __('List View') }}</button>
            </div>
            <ul class="parent-timetable-legend" data-parent-timetable-legend aria-label="{{ __('Subjects with the most scheduled time') }}">
                @foreach($parentSchedule['legend'] as $subject => $minutes)
                    <li data-tone="{{ \App\Support\TimetableGrid::subjectStyle($subject)[0] }}" title="{{ $subject }}">
                        <span>{{ $subject }}</span><small>{{ __(':count min', ['count' => $minutes]) }}</small>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
    <div id="shared-timetable">
        @if(auth()->user()->role === 'parent')
            <div class="parent-timetable-weekly">@include('school.timetables.grid')</div>
            @include('school.timetables._parent-list')
        @else
            @include('school.timetables.grid')
        @endif
    </div>
</section>
@if($parentSchedule)
        @include('school.timetables._parent-side')
    </div>
@endif
