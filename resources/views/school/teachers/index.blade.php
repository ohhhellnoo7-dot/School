@extends('school.layout')
@section('title', 'Teachers')
@section('content')
<header class="teacher-hero-heading" aria-labelledby="teacher-heading">
    <div class="teacher-heading-title">
        <span class="teacher-heading-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span>
        <div><h1 id="teacher-heading">{{ __('Teachers') }}</h1><p>{{ __('Manage teachers, their subjects, classes, and schedules.') }}</p></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('teacher-management.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>{{ __('Add Teacher') }}</a>
        <a href="{{ route('teacher-management.export', request()->only('search', 'qualified_subject', 'program', 'workload', 'class', 'session', 'filter')) }}" class="btn btn-light"><i class="bi bi-download me-2" aria-hidden="true"></i>{{ __('Export') }}</a>
    </div>
</header>
<div class="teacher-summary" aria-label="Teacher overview">
@foreach([[number_format($total), 'Total Teachers', 'person-fill'], [number_format($subjectTotal), 'Total Subjects', 'book'], [number_format($classTotal), 'Total Classes', 'mortarboard'], [rtrim(rtrim(number_format($weeklyTotal, 2), '0'), '.').' h', 'Total Weekly Hours', 'clock']] as [$value, $label, $icon])
<article class="teacher-stat"><span class="teacher-stat-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><div><h2>{{ __($label) }}</h2><strong>{{ $value }}</strong></div></article>
@endforeach
</div>
<section id="teacher-attendance-records" class="panel directory-panel">
<div class="panel-header"><h2>{{ __('Teacher List') }} <span class="badge teacher-count">{{ $rows->count() }}</span></h2><a class="teacher-directory-caption" href="#teacher-attendance"><i class="bi bi-calendar3 me-1" aria-hidden="true"></i>{{ \Carbon\Carbon::parse($attendance['date'])->format('d M Y') }}</a></div>
<form method="get" class="teacher-simple-filters p-4 pt-2">
    @if(request()->filled('qualified_subject'))<input type="hidden" name="qualified_subject" value="{{ request('qualified_subject') }}"><p class="small text-secondary">Available teachers qualified in {{ request('qualified_subject') }}. Reset to view everyone.</p>@endif
    <div class="teacher-filter-row"><div><label for="search" class="form-label">Search Teacher</label><input type="search" class="form-control" id="search" name="search" placeholder="Name, teacher ID or email" value="{{ request('search') }}"></div>
    <div><label for="program" class="form-label">{{ __('Program') }}</label><select id="program" name="program" class="form-select"><option value="">All</option>@foreach(\App\Models\Program::names() as $program)<option @selected(request('program') === $program)>{{ $program }}</option>@endforeach</select></div>
    <div><label for="workload" class="form-label">{{ __('Status') }}</label><select id="workload" name="workload" class="form-select"><option value="">All</option>@foreach(['AVAILABLE', 'FULL', 'OVERLOADED', 'UNASSIGNED'] as $status)<option value="{{ $status }}" @selected(request('workload') === $status)>{{ ucfirst(strtolower($status)) }}</option>@endforeach</select></div>
    <div class="d-flex gap-2 align-items-end"><button class="btn btn-primary"><i class="bi bi-funnel me-2" aria-hidden="true"></i>{{ __('Filter') }}</button><a class="btn btn-outline-secondary" href="{{ route('modules.index', 'teachers') }}"><i class="bi bi-arrow-repeat me-2" aria-hidden="true"></i>{{ __('Reset') }}</a></div></div>
    <details class="mt-3" @if(request()->filled('class') || request()->filled('session') || request()->filled('filter')) open @endif><summary>Advanced filters</summary><div class="teacher-filter-row mt-3">
    <div><label for="teacher-session" class="form-label">{{ __('Session') }}</label><select id="teacher-session" name="session" class="form-select"><option value="">All</option>@foreach(['Morning', 'Afternoon', 'Evening'] as $session)<option @selected(request('session') === $session)>{{ $session }}</option>@endforeach</select></div>
    <div><label for="teacher-class" class="form-label">{{ __('Class') }}</label><select id="teacher-class" name="class" class="form-select"><option value="">All</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected((int)request('class') === $class->id)>{{ $class->label }}</option>@endforeach</select></div>
    <div><label for="filter" class="form-label">Teacher account</label><select id="filter" name="filter" class="form-select"><option value="">All</option>@foreach(['active', 'inactive'] as $status)<option @selected(request('filter') === $status)>{{ $status }}</option>@endforeach</select></div></div></details>
</form>
<div class="table-responsive"><table class="table teacher-simple-table"><thead><tr><th>{{ __('Teacher') }}</th><th>{{ __('Subjects') }}</th><th>{{ __('Classes') }}</th><th>Weekly Hours</th><th>Attendance &amp; notes</th><th>{{ __('Actions') }}</th></tr></thead><tbody>
@forelse($rows as $row) @php($t = $row['teacher']) @php($w = $row['workload'])
<tr>
<td data-label="Teacher">
    <div class="teacher-row-identity">
        @include('school.partials.avatar', ['person' => $t, 'teacherIcon' => true])
        <div class="teacher-row-details">
            <a href="{{ route('teacher-management.show', $t) }}">{{ $t->name }}</a>
            <small class="teacher-row-meta">{{ $t->profile_number ?: '#'.$t->id }} · {{ $t->program ?: 'Khmer' }}
                @if($t->status)<span @class(['teacher-account-pill', 'is-active' => $t->status === 'active'])>{{ ucfirst($t->status) }}</span>@endif
            </small>
            @if($t->requiresTeacherConfirmation())<small class="d-block text-secondary">Unverified record</small>@elseif($t->employee_confirmed_at)<small class="d-block text-success"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Employee confirmed</small>@endif
        </div>
    </div>
</td>
<td data-label="Subjects"><div class="teacher-row-context"><i class="bi bi-book" aria-hidden="true"></i><div>{{ $w['subjects']->join(', ') ?: 'Not assigned' }}@if($t->program === 'Khmer' && $w['subjects']->count() > 2)<small class="d-block text-danger">{{ $w['subjects']->count() - 2 }} additional subjects · review</small>@endif</div></div></td>
<td data-label="Classes"><div class="teacher-row-context"><i class="bi bi-mortarboard" aria-hidden="true"></i><div>{{ $w['classes']->take(3)->map(fn ($c) => $c->name.$c->section)->join(', ') ?: 'Not assigned' }}@if($w['classes']->count() > 3)<small class="teacher-count-pill">+{{ $w['classes']->count() - 3 }} more</small>@endif</div></div></td>
<td data-label="Weekly Hours"><span class="teacher-hours-pill"><i class="bi bi-clock" aria-hidden="true"></i>{{ $w['weekly'] }} / {{ $w['limit'] }} h</span></td><td data-label="Attendance &amp; notes" class="teacher-inline-attendance">
@php($dailyRecord = $rowAttendance->get($t->id))
@php($attendanceState = $dailyRecord?->status ?? 'unmarked')
@if($attendance['date'] <= today()->toDateString() && (in_array($t->status, [null, 'active'], true) || $dailyRecord))
<details class="teacher-row-attendance">
    <summary>
        <span class="teacher-attendance-status" data-state="{{ $attendanceState }}">{{ ucfirst($attendanceState) }}</span>
        @if(filled($dailyRecord?->notes))
            <i class="bi bi-chat-left-text teacher-attendance-note-icon" title="Has an attendance note" aria-label="Has an attendance note" role="img"></i>
        @endif
        <span class="teacher-attendance-edit"><span class="visually-hidden">Edit attendance for {{ $t->name }}</span><span aria-hidden="true">Edit <i class="bi bi-chevron-down"></i></span></span>
    </summary>
<form method="post" action="{{ route('teacher-attendance.store') }}" class="teacher-attendance-row-form">
    @csrf
    <input type="hidden" name="date" value="{{ $attendance['date'] }}">
    <input type="hidden" name="return_to" value="teachers">
    <input type="hidden" name="records[0][teacher_id]" value="{{ $t->id }}">
    <label class="visually-hidden" for="attendance-status-{{ $t->id }}">Attendance for {{ $t->name }}</label>
    <select id="attendance-status-{{ $t->id }}" class="form-select form-select-sm" name="records[0][status]">
        <option value="">Unmarked</option>
        @foreach(['present','absent','late','excused'] as $attendanceStatus)
        <option value="{{ $attendanceStatus }}" @selected($dailyRecord?->status === $attendanceStatus)>{{ ucfirst($attendanceStatus) }}</option>
        @endforeach
    </select>
    <label class="visually-hidden" for="attendance-notes-{{ $t->id }}">Attendance notes for {{ $t->name }}</label>
    <input id="attendance-notes-{{ $t->id }}" class="form-control form-control-sm" name="records[0][notes]" maxlength="1000" value="{{ $dailyRecord?->notes }}" placeholder="Add a note (optional)">
    <button class="btn btn-sm btn-primary" type="submit" aria-label="Save teacher attendance for {{ $t->name }}"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Save</button>
</form>
</details>
@else
<span class="teacher-attendance-status" data-state="{{ $attendanceState }}">{{ ucfirst($attendanceState) }}</span>
@if($dailyRecord?->notes)<small class="d-block text-secondary">{{ $dailyRecord->notes }}</small>@endif
@endif
</td><td data-label="Actions"><div class="teacher-row-actions">
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('teacher-management.edit', $t) }}" aria-label="Edit {{ $t->name }}" title="Edit teacher"><i class="bi bi-pencil-fill" aria-hidden="true"></i></a>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('teachers.schedule', $t) }}" aria-label="View schedule for {{ $t->name }}" title="View schedule"><i class="bi bi-calendar3" aria-hidden="true"></i></a>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('teacher-management.show', $t) }}" aria-label="Manage {{ $t->name }}" title="Manage teacher"><i class="bi bi-three-dots" aria-hidden="true"></i></a>
</div></td></tr>
@empty<tr><td colspan="6">No teachers match these filters.</td></tr>@endforelse
</tbody></table></div><div class="p-3 small text-secondary">{{ $rows->count() }} teachers shown</div></section>

<section id="teacher-attendance" class="mt-4 mb-4" aria-label="Teacher attendance overview">
    @include('school.attendance._teacher-overview', $attendance)
</section>

<section class="panel mt-4 teacher-quick-actions"><div class="panel-header"><h2>Quick Actions</h2></div><div class="d-flex flex-wrap gap-2 p-4 pt-2">
<a class="btn btn-primary" href="{{ route('teacher-management.create') }}">Add Teacher</a>
<form method="post" action="{{ route('teacher-management.breaks.preview-all') }}">@csrf<button class="btn btn-outline-secondary">Generate Weekly Teacher Breaks</button></form>
<form method="post" action="{{ route('teacher-management.assignments.preview') }}">@csrf<button class="btn btn-outline-secondary">Assign Teachers Automatically</button></form>
<a class="btn btn-outline-secondary" href="{{ route('teacher-management.reports') }}">Teacher Reports</a>
</div></section>
<details class="panel p-3 mt-3"><summary>Optional sample data</summary><p class="small text-secondary mt-2">Preview clearly fictional Cambodian teacher records. No records are created until you confirm.</p><form method="post" action="{{ route('teacher-management.samples.preview') }}" class="d-flex flex-wrap gap-2">@csrf<label for="sample-count" class="visually-hidden">Number of sample teachers</label><input id="sample-count" name="count" type="number" min="1" max="100" value="{{ min(100, max(1, $demand['target'] - $demand['active'])) }}" class="form-control w-auto" required><button class="btn btn-outline-secondary">Generate Sample Teachers</button></form></details>
@endsection
@push('scripts')<script src="{{ asset('js/directory-search.js') }}?v={{ filemtime(public_path('js/directory-search.js')) }}"></script><script src="{{ asset('vendor/chart/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/teacher-attendance-chart.js') }}?v={{ filemtime(public_path('js/teacher-attendance-chart.js')) }}" defer></script>
@endpush
