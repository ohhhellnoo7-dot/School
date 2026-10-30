@extends('school.layout')
@section('title', 'Teachers')
@section('content')
<section class="page-heading school-hero teacher-hero" aria-labelledby="teacher-heading">
    <div><div class="eyebrow">{{ __('School workspace') }}</div><h1 id="teacher-heading">{{ __('Teachers') }}</h1><p>{{ __('Meet the educators shaping tomorrow.') }}</p></div>
    <a href="{{ route('teacher-management.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>{{ __('Add Teacher') }}</a>
</section>
<div class="teacher-summary" aria-label="Teacher overview">
@foreach([[$total, 'Total Teachers', 'people-fill', 'Your school teaching team'], [$available, 'Available Teachers', 'person-check', 'Active teachers with teaching capacity'], [$full, 'Fully Booked Teachers', 'calendar-check', 'At their weekly teaching limit'], [$demand['needed'], 'Teachers Needed', 'person-plus', 'Estimated Khmer staffing gap; see reports']] as [$value, $label, $icon, $note])
<article class="teacher-stat"><span class="teacher-stat-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><div><h2>{{ $label }}</h2><strong>{{ $value }}</strong></div><small>{{ $note }}</small></article>
@endforeach
</div>
<section class="panel directory-panel">
<div class="panel-header"><h2>{{ __('Teacher List') }} <span class="badge text-bg-light">{{ $rows->count() }}</span></h2></div>
<form method="get" class="teacher-simple-filters p-4 pt-2">
    @if(request()->filled('qualified_subject'))<input type="hidden" name="qualified_subject" value="{{ request('qualified_subject') }}"><p class="small text-secondary">Available teachers qualified in {{ request('qualified_subject') }}. Reset to view everyone.</p>@endif
    <div class="teacher-filter-row"><div><label for="search" class="form-label">Search Teacher</label><input type="search" class="form-control" id="search" name="search" placeholder="Name, teacher ID or email" value="{{ request('search') }}"></div>
    <div><label for="program" class="form-label">Program</label><select id="program" name="program" class="form-select"><option value="">All</option>@foreach(['Khmer', 'English', 'Chinese'] as $program)<option @selected(request('program') === $program)>{{ $program }}</option>@endforeach</select></div>
    <div><label for="workload" class="form-label">Status</label><select id="workload" name="workload" class="form-select"><option value="">All</option>@foreach(['AVAILABLE', 'FULL', 'OVERLOADED', 'UNASSIGNED'] as $status)<option value="{{ $status }}" @selected(request('workload') === $status)>{{ ucfirst(strtolower($status)) }}</option>@endforeach</select></div>
    <div class="d-flex gap-2 align-items-end"><button class="btn btn-primary">Filter</button><a class="btn btn-outline-secondary" href="{{ route('modules.index', 'teachers') }}">Reset</a></div></div>
    <details class="mt-3" @if(request()->filled('class') || request()->filled('session') || request()->filled('filter')) open @endif><summary>Advanced filters</summary><div class="teacher-filter-row mt-3">
    <div><label for="teacher-session" class="form-label">Session</label><select id="teacher-session" name="session" class="form-select"><option value="">All</option>@foreach(['Morning', 'Afternoon', 'Evening'] as $session)<option @selected(request('session') === $session)>{{ $session }}</option>@endforeach</select></div>
    <div><label for="teacher-class" class="form-label">Class</label><select id="teacher-class" name="class" class="form-select"><option value="">All</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected((int)request('class') === $class->id)>{{ $class->label }}</option>@endforeach</select></div>
    <div><label for="filter" class="form-label">Teacher account</label><select id="filter" name="filter" class="form-select"><option value="">All</option>@foreach(['active', 'inactive'] as $status)<option @selected(request('filter') === $status)>{{ $status }}</option>@endforeach</select></div></div></details>
</form>
<div class="table-responsive"><table class="table teacher-simple-table"><thead><tr><th>Teacher</th><th>Subjects</th><th>Classes</th><th>Weekly Hours</th><th>Break Hours</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($rows as $row) @php($t = $row['teacher']) @php($w = $row['workload'])
<tr><td data-label="Teacher">@include('school.partials.avatar', ['person' => $t])<a href="{{ route('teacher-management.show', $t) }}">{{ $t->name }}</a><small class="d-block text-secondary">{{ $t->profile_number ?: '#'.$t->id }} · {{ $t->program ?: 'Khmer' }} · {{ $t->status }}</small></td>
<td data-label="Subjects">{{ $w['subjects']->take(2)->join(', ') ?: 'Not assigned' }}@if($w['subjects']->count() > 2)<small class="d-block text-danger">{{ $w['subjects']->count() - 2 }} additional subjects · review</small>@endif</td>
<td data-label="Classes">{{ $w['classes']->take(3)->map(fn ($c) => $c->name.$c->section)->join(', ') ?: 'Not assigned' }}@if($w['classes']->count() > 3)<small class="d-block text-secondary">+{{ $w['classes']->count() - 3 }} more</small>@endif</td>
<td data-label="Weekly Hours">{{ $w['weekly'] }} / {{ $w['limit'] }} h</td><td data-label="Break Hours">{{ $w['breaks'] }} / 3 h</td><td data-label="Status"><span class="teacher-status status-{{ strtolower($w['status']) }}">{{ $w['status'] }}</span></td><td data-label="Actions"><a class="btn btn-sm btn-outline-secondary" href="{{ route('teacher-management.show', $t) }}">Manage</a> <a class="btn btn-sm btn-outline-secondary" href="{{ route('teachers.schedule', $t) }}">View Schedule</a></td></tr>
@empty<tr><td colspan="7">No teachers match these filters.</td></tr>@endforelse
</tbody></table></div><div class="p-3 small text-secondary">{{ $rows->count() }} teachers shown</div></section>
<section class="panel mt-4 teacher-quick-actions"><div class="panel-header"><h2>Quick Actions</h2></div><div class="d-flex flex-wrap gap-2 p-4 pt-2">
<a class="btn btn-primary" href="{{ route('teacher-management.create') }}">Add Teacher</a>
<form method="post" action="{{ route('teacher-management.breaks.preview-all') }}">@csrf<button class="btn btn-outline-secondary">Generate Weekly Teacher Breaks</button></form>
<form method="post" action="{{ route('teacher-management.assignments.preview') }}">@csrf<button class="btn btn-outline-secondary">Assign Teachers Automatically</button></form>
<a class="btn btn-outline-secondary" href="{{ route('teacher-management.reports') }}">Teacher Reports</a>
</div></section>
<details class="panel p-3 mt-3"><summary>Optional sample data</summary><p class="small text-secondary mt-2">Preview clearly fictional Cambodian teacher records. No records are created until you confirm.</p><form method="post" action="{{ route('teacher-management.samples.preview') }}" class="d-flex flex-wrap gap-2">@csrf<label for="sample-count" class="visually-hidden">Number of sample teachers</label><input id="sample-count" name="count" type="number" min="1" max="100" value="{{ min(100, max(1, $demand['target'] - $demand['active'])) }}" class="form-control w-auto" required><button class="btn btn-outline-secondary">Generate Sample Teachers</button></form></details>
@endsection
@push('scripts')<script src="{{ asset('js/directory-search.js') }}?v={{ filemtime(public_path('js/directory-search.js')) }}"></script>@endpush
