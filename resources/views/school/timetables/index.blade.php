@extends('school.layout')
@section('title', 'School Timetables')
@section('content')
<link href="{{ asset('css/unified-timetable.css') }}?v={{ filemtime(public_path('css/unified-timetable.css')) }}" rel="stylesheet">
<section class="panel p-4 mb-4 unified-timetable-hero"><h1>School Timetables</h1><p>One lesson, three connected schedules. Asia/Phnom_Penh.</p>
@if(auth()->user()->role === 'administrator')<div class="d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="{{ route('timetables.lessons.create') }}">Add lesson</a><a class="btn btn-outline-secondary" href="{{ route('timetables.diagnostics') }}">Diagnostics &amp; Khmer replacement</a><a class="btn btn-outline-secondary" href="{{ route('timetables.rooms.review') }}">Review physical room assignments</a></div>@endif</section>
<nav class="d-flex gap-2 mb-3 flex-wrap" aria-label="Schedule views">
    @if(!in_array(auth()->user()->role, ['student','parent']))
    <div class="dropdown" data-teacher-program-menu>
        <button type="button" class="btn dropdown-toggle {{ $view === 'teachers' ? 'btn-primary' : 'btn-outline-secondary' }}" id="teacher-program-button" data-bs-toggle="dropdown" aria-expanded="false">{{ $teacherProgram ? strtoupper($teacherProgram).' TEACHERS' : 'TEACHERS' }}</button>
        <ul class="dropdown-menu" aria-labelledby="teacher-program-button">
            @foreach(['Khmer', 'English', 'Chinese'] as $program)
            <li><a class="dropdown-item {{ $teacherProgram === $program ? 'active' : '' }}" href="{{ route('timetables.index', ['view'=>'teachers','program'=>$program,'academic_year'=>$academicYear]) }}" @if($teacherProgram === $program) aria-current="page" @endif>{{ $program }} teachers</a></li>
            @endforeach
        </ul>
    </div>
    @endif
    @foreach(['students'=>'STUDENTS','classrooms'=>'CLASSROOMS'] as $key=>$label)
    <a class="btn {{ $view === $key ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('timetables.index', ['view'=>$key,'academic_year'=>$academicYear]) }}" @if($view === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
<form class="panel p-3 mb-3 d-flex gap-3 flex-wrap align-items-end" method="get"><input type="hidden" name="view" value="{{ $view }}"><label>Academic year<input class="form-control" name="academic_year" pattern="[0-9]{4}-[0-9]{4}" value="{{ $academicYear }}"></label>
@if($view === 'teachers')
@if($teacherProgram)<input type="hidden" name="program" value="{{ $teacherProgram }}">@endif
<label>{{ $teacherProgram ? $teacherProgram.' teacher' : 'Teacher' }}<select class="form-select" name="teacher">@forelse($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($selected === $teacher->id)>{{ $teacher->name }} · {{ $teacher->program ?: 'Program not set' }}{{ $teacher->requiresTeacherConfirmation() ? ' (unverified)' : '' }}</option>@empty<option value="" disabled selected>No teachers in this program</option>@endforelse</select></label>
@elseif($view === 'classrooms')<label>Physical classroom<select class="form-select" name="classroom">@foreach($rooms as $room)<option value="{{ $room->id }}" @selected($selected === $room->id)>{{ $room->room_number }} — {{ $room->name }}</option>@endforeach</select></label>
@else<label>Grade<select class="form-select" name="grade"><option value="">All grades</option>@foreach(range(1,12) as $grade)<option @selected(request('grade') == $grade)>{{ $grade }}</option>@endforeach</select></label><label>Class and cohort<select class="form-select" name="class">@foreach($studentClasses ?? $classes as $class)<option value="{{ $class->id }}" @selected($selected === $class->id)>{{ $class->label }}</option>@endforeach</select></label><label>Session<select class="form-select" name="session"><option value="">All sessions</option>@foreach(['Morning','Afternoon','Evening'] as $session)<option @selected(request('session') === $session)>{{ $session }}</option>@endforeach</select></label>@endif<button class="btn btn-primary">View schedule</button></form>
<div id="shared-timetable">@include('school.timetables.grid')</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-teacher-program-menu]').forEach(menu => {
    const dropdown = bootstrap.Dropdown.getOrCreateInstance(menu.querySelector('[data-bs-toggle="dropdown"]'));
    let closeTimer;
    menu.addEventListener('mouseenter', () => {
        clearTimeout(closeTimer);
        if (window.matchMedia('(hover: hover)').matches) dropdown.show();
    });
    menu.addEventListener('mouseleave', () => {
        if (window.matchMedia('(hover: hover)').matches) closeTimer = setTimeout(() => dropdown.hide(), 150);
    });
});
</script>
<script>setInterval(async () => { if(document.hidden) return; try { const url = new URL(@json(route('timetables.data'))); url.search = location.search; const response = await fetch(url, {headers:{Accept:'application/json'}}); if(response.ok) document.getElementById('shared-timetable').innerHTML = (await response.json()).html; } catch {} }, 30000);</script>
@endpush
