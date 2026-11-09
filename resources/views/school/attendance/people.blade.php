@extends('school.layout')
@section('title', ucfirst($status).' '.$kind)
@section('content')
<div class="page-heading"><div><h1>{{ ucfirst($status) }} {{ $kind }}</h1><p>{{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }} &middot; {{ $session ?: 'All sessions' }} &middot; {{ $records->total() }} recorded</p></div><a class="btn btn-outline-secondary" href="{{ route('dashboard', array_filter(['date' => $date, 'session' => $session])) }}">Back to overview</a></div>
<section class="panel"><div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>{{ $kind === 'teachers' ? 'Program' : 'Class' }}</th><th>Status</th><th>Notes</th></tr></thead><tbody>
@forelse($records as $record)
@php($person = $kind === 'teachers' ? $record->teacher : $record->student)
<tr><td>{{ $person?->name ?? 'Unavailable' }}</td><td>{{ $kind === 'teachers' ? ($record->program ?: 'Unassigned') : ($person?->schoolClass?->label ?: 'Unassigned') }}</td><td>{{ ucfirst($record->status) }}</td><td>{{ $record->notes ?: '?' }}</td></tr>
@empty
<tr><td colspan="4" class="text-center p-4">No {{ $status }} {{ $kind }} recorded for this date and session.</td></tr>
@endforelse
</tbody></table></div></section><div class="mt-3">{{ $records->links() }}</div>
@endsection
