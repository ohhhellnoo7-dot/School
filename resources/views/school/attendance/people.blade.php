@extends('school.layout')
@section('title', ucfirst($status).' '.$kind)
@section('content')
<div class="page-heading"><div><h1>{{ ucfirst($status) }} {{ $kind }}</h1><p>{{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }} &middot; {{ $session ?: 'All sessions' }} &middot; {{ $records->total() }} recorded</p></div><a class="btn btn-outline-secondary" href="{{ route('dashboard', array_filter(['date' => $date, 'session' => $session])) }}">Back to overview</a></div>
<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Filter {{ $kind }} by program">
    @php($selectedProgram = request('program'))
    @foreach(['' => 'All '.$kind] + array_combine(\App\Models\Program::names(), \App\Models\Program::names()) as $program => $label)
        <a class="btn {{ (string) $selectedProgram === (string) $program ? 'btn-primary' : 'btn-outline-secondary' }}"
           href="{{ route('attendance.people', array_filter(['kind' => $kind, 'status' => $status, 'date' => $date, 'session' => $session, 'program' => $program, 'search' => request('search')])) }}"
           @if((string) $selectedProgram === (string) $program) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@if($kind === 'students')
<section class="panel p-3 mb-3" aria-label="Find students">
    <form method="get" action="{{ route('attendance.people', ['kind' => $kind, 'status' => $status]) }}" class="d-flex flex-wrap align-items-end gap-2 mb-3">
        <input type="hidden" name="date" value="{{ $date }}">
        @foreach(['session', 'program'] as $filter)
            @if(request($filter))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif
        @endforeach
        <div class="flex-grow-1"><label for="attendance-name-search" class="form-label">Search student name</label><input id="attendance-name-search" type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Enter a student name" maxlength="100"></div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Search</button>
        <a class="btn btn-outline-secondary" href="{{ route('attendance.people', ['kind' => $kind, 'status' => $status, 'date' => $date, 'session' => $session]) }}">Reset filters</a>
    </form>

</section>
@endif
<section class="panel"><div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>{{ $kind === 'teachers' ? 'Program' : 'Class' }}</th><th>Status</th><th>Notes</th></tr></thead><tbody>
@forelse($records as $record)
@php($person = $kind === 'teachers' ? $record->teacher : $record->student)
<tr><td>{{ $person?->name ?? 'Unavailable' }}</td><td>{{ $kind === 'teachers' ? ($record->program ?: 'Unassigned') : ($person?->schoolClass?->label ?: 'Unassigned') }}</td><td>{{ ucfirst($record->status) }}</td><td>{{ $record->notes ?: '?' }}</td></tr>
@empty
<tr><td colspan="4" class="text-center p-4">No {{ $status }} {{ $kind }} match the selected date, session and filters.</td></tr>
@endforelse
</tbody></table></div></section><div class="mt-3">{{ $records->links() }}</div>
@endsection
