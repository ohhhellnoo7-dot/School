@extends('school.layout')
@section('title', 'School Timetables')
@section('content')
<link href="{{ asset('css/unified-timetable.css') }}?v={{ filemtime(public_path('css/unified-timetable.css')) }}" rel="stylesheet">
<div class="timetable-workspace">
<section class="panel p-4 mb-4 unified-timetable-hero school-hero">
    <div class="timetable-hero-copy">
        <div class="timetable-hero-heading"><span class="timetable-hero-icon"><i class="bi bi-calendar-week" aria-hidden="true"></i></span><div><span class="timetable-eyebrow">{{ __('Plan the school week') }}</span><h1>{{ __('School Timetables') }}</h1></div></div>
        <p>{{ __('One lesson, three connected schedules.') }} <span class="timetable-timezone"><i class="bi bi-globe2" aria-hidden="true"></i> Asia/Phnom_Penh</span></p>
        @if(auth()->user()->role === 'administrator')<div class="timetable-hero-actions d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="{{ route('timetables.lessons.create') }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Add lesson') }}</a><a class="btn btn-outline-secondary" href="{{ route('timetables.diagnostics') }}"><i class="bi bi-tools" aria-hidden="true"></i> {{ __('Diagnostics & Khmer replacement') }}</a><a class="btn btn-outline-secondary" href="{{ route('timetables.rooms.review') }}"><i class="bi bi-layers" aria-hidden="true"></i> {{ __('Review physical room assignments') }}</a></div>@endif
    </div>
</section>
<nav class="timetable-view-tabs d-flex gap-2 mb-3 flex-wrap" aria-label="{{ __('Schedule views') }}">
    @if(!in_array(auth()->user()->role, ['student','parent']))
    <div class="dropdown" data-teacher-program-menu>
        <button type="button" class="btn dropdown-toggle {{ $view === 'teachers' ? 'btn-primary' : 'btn-outline-secondary' }}" id="teacher-program-button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-workspace" aria-hidden="true"></i> {{ $teacherProgram ? __($teacherProgram).' · '.__('Teachers') : __('Teachers') }}</button>
        <ul class="dropdown-menu" aria-labelledby="teacher-program-button">
            @foreach(['Khmer', 'English', 'Chinese'] as $program)
            <li><a class="dropdown-item {{ $teacherProgram === $program ? 'active' : '' }}" href="{{ route('timetables.index', ['view'=>'teachers','program'=>$program,'academic_year'=>$academicYear]) }}" @if($teacherProgram === $program) aria-current="page" @endif>{{ __($program) }} · {{ __('Teachers') }}</a></li>
            @endforeach
        </ul>
    </div>
    @endif
    @foreach(['students'=>'Students','classrooms'=>'Classrooms'] as $key=>$label)
    <a class="btn {{ $view === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('timetables.index', ['view'=>$key,'academic_year'=>$academicYear]) }}" @if($view === $key) aria-current="page" @endif><i class="bi bi-{{ $key === 'students' ? 'people' : 'display' }}" aria-hidden="true"></i> {{ __($label) }}</a>
    @endforeach
</nav>
<form class="panel timetable-filters p-3 mb-3 d-flex gap-3 flex-wrap align-items-end" method="get"><input type="hidden" name="view" value="{{ $view }}"><label>{{ __('Academic year') }}<input class="form-control" name="academic_year" pattern="[0-9]{4}-[0-9]{4}" value="{{ $academicYear }}"></label>
@if($view === 'teachers')
@if($teacherProgram)<input type="hidden" name="program" value="{{ $teacherProgram }}">@endif
<label>{{ $teacherProgram ? $teacherProgram.' teacher' : 'Teacher' }}<select class="form-select" name="teacher">@forelse($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($selected === $teacher->id)>{{ $teacher->name }} · {{ $teacher->program ?: 'Program not set' }}{{ $teacher->requiresTeacherConfirmation() ? ' (unverified)' : '' }}</option>@empty<option value="" disabled selected>{{ $teacherSession ? 'No teachers in this session' : 'No teachers in this program' }}</option>@endforelse</select></label>
@if($teacherProgram)
@if($teacherSession)<input type="hidden" name="session" value="{{ $teacherSession }}">@endif
<div class="dropdown" data-teacher-session-menu>
    <button type="button" class="btn btn-outline-primary dropdown-toggle" id="teacher-session-button" data-bs-toggle="dropdown" aria-expanded="false">Session{{ $teacherSession ? ': '.$teacherSession : '' }}</button>
    <ul class="dropdown-menu" aria-labelledby="teacher-session-button">
        @foreach(['' => 'All sessions'] + array_combine($availableTeacherSessions, $availableTeacherSessions) as $sessionValue => $sessionLabel)
        <li><a class="dropdown-item {{ ($teacherSession ?? '') === $sessionValue ? 'active' : '' }}" href="{{ route('timetables.index', ['view'=>'teachers', 'program'=>$teacherProgram, 'academic_year'=>$academicYear, 'teacher'=>$selected, 'session'=>$sessionValue ?: null]) }}" @if(($teacherSession ?? '') === $sessionValue) aria-current="page" @endif>{{ __($sessionLabel) }}</a></li>
        @endforeach
    </ul>
</div>
@endif
@elseif($view === 'classrooms')<label>{{ __('Physical classroom') }}<select class="form-select" name="classroom">@foreach($rooms as $room)<option value="{{ $room->id }}" @selected($selected === $room->id)>{{ $room->room_number }} — {{ $room->name }}</option>@endforeach</select></label>
@else<label>{{ __('Grade') }}<select class="form-select" name="grade"><option value="">{{ __('All grades') }}</option>@foreach(range(1,12) as $grade)<option @selected(request('grade') == $grade)>{{ $grade }}</option>@endforeach</select></label><label>{{ __('Class and cohort') }}<select class="form-select" name="class">@foreach($studentClasses ?? $classes as $class)<option value="{{ $class->id }}" @selected($selected === $class->id)>{{ $class->label }}</option>@endforeach</select></label><label>{{ __('Session') }}<select class="form-select" name="session"><option value="">{{ __('All sessions') }}</option>@foreach(['Morning','Afternoon','Evening'] as $session)<option @selected(request('session') === $session)>{{ $session }}</option>@endforeach</select></label>@endif<button class="btn btn-primary">{{ __('View schedule') }}</button></form>
@if($view === 'teachers' && ($selectedTeacher = $teachers->firstWhere('id', $selected))?->program === 'English')
@php($workingMinutes = app(\App\Services\TeacherManagementService::class)->periods($selectedTeacher)->where('unavailable', false)->sum(fn ($p) => \App\Services\TeacherManagementService::duration($p->start_time, $p->end_time)))
<div class="panel p-3 mb-3 d-flex gap-3 align-items-center flex-wrap">
    <strong>{{ $workingMinutes === 600 ? 'Part-time: 2 hours per day / 10 hours per week' : ($workingMinutes === 2400 ? 'Full-time: 8 hours per day / 40 hours per week' : 'Custom working hours') }}</strong>
    <span>One class and classroom per session. Teachers may change classes between sessions.</span>
    @if($selectedTeacher->employee_confirmed_at)<span class="badge text-bg-success">Administrator verified</span>@endif
    @if(auth()->user()->role === 'administrator')<a href="{{ route('teacher-management.edit', $selectedTeacher) }}">Edit working hours</a>@endif
</div>
@endif
<section class="timetable-calendar" aria-label="{{ __('Weekly timetable') }}">
    <header class="timetable-calendar-toolbar"><div><span class="timetable-calendar-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span><div><h2>{{ __('Weekly schedule') }}</h2><p>{{ __('Monday–Friday') }} <span>·</span> {{ $academicYear }}</p></div></div><div class="timetable-calendar-tools"><span class="timetable-refresh-note"><span></span>{{ __('Updates every 30 seconds') }}</span><button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> {{ __('Print Schedule') }}</button></div></header>
    <div id="shared-timetable">@include('school.timetables.grid')</div>
</section>
</div>
@endsection
@push('scripts')
<script>setInterval(async () => { if(document.hidden) return; try { const url = new URL(@json(route('timetables.data'))); url.search = location.search; const response = await fetch(url, {headers:{Accept:'application/json'}}); if(response.ok) document.getElementById('shared-timetable').innerHTML = (await response.json()).html; } catch {} }, 30000);</script>
@endpush
