@extends('school.layout')
@section('title', ucfirst($status).' '.$kind)
@section('content')
<div class="page-heading"><div><h1>{{ ucfirst($status) }} {{ $kind }}</h1><p>{{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }} &middot; {{ $session ?: 'All sessions' }} &middot; {{ $records->total() }} recorded</p></div><a class="btn btn-outline-secondary" href="{{ route('dashboard', array_filter(['date' => $date, 'session' => $session])) }}">Back to overview</a></div>
@if($kind === 'teachers')
<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Filter teachers by program">
    @php($selectedProgram = request('program'))
    @foreach(['' => 'All teachers'] + array_combine(\App\Models\Program::names(), \App\Models\Program::names()) as $program => $label)
        <a class="btn {{ (string) $selectedProgram === (string) $program ? 'btn-primary' : 'btn-outline-secondary' }}"
           href="{{ route('attendance.people', array_filter(['kind' => $kind, 'status' => $status, 'date' => $date, 'session' => $session, 'program' => $program, 'search' => request('search')])) }}"
           @if((string) $selectedProgram === (string) $program) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
@endif
<section class="panel"><div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>{{ $kind === 'teachers' ? 'Program' : 'Class' }}</th><th>Status</th><th>Notes</th></tr></thead><tbody>
@forelse($records as $record)
@php($person = $kind === 'teachers' ? $record->teacher : $record->student)
<tr><td>{{ $person?->name ?? 'Unavailable' }}</td><td>{{ $kind === 'teachers' ? ($record->program ?: 'Unassigned') : ($person?->schoolClass?->label ?: 'Unassigned') }}</td><td>{{ ucfirst($record->status) }}</td><td>{{ $record->notes ?: '?' }}</td></tr>
@empty
<tr><td colspan="4" class="text-center p-4">No {{ $status }} {{ $kind }} recorded for this date and session.</td></tr>
@endforelse
</tbody></table></div></section><div class="mt-3">{{ $records->links() }}</div>
@endsection
