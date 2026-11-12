@extends('school.layout')
@section('title', ucfirst($status).' '.$kind)
@section('content')
<div data-attendance-people>
<div class="page-heading"><div><h1>{{ ucfirst($status) }} {{ $kind }}</h1><p>{{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }} &middot; {{ $session ?: 'All sessions' }} &middot; {{ $records->total() }} recorded</p></div><a class="btn btn-outline-secondary" href="{{ route('dashboard', array_filter(['date' => $date, 'session' => $session])) }}">Back to overview</a></div>
<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Filter {{ $kind }} by program">
    @php($selectedProgram = request('program'))
    @foreach(['' => 'All '.$kind] + array_combine(\App\Models\Program::names(), \App\Models\Program::names()) as $program => $label)
        <a class="btn {{ (string) $selectedProgram === (string) $program ? 'btn-primary' : 'btn-outline-secondary' }}"
           href="{{ route('attendance.people', array_filter(['kind' => $kind, 'status' => $status, 'date' => $date, 'session' => $session, 'program' => $program, 'search' => request('search'), 'name_part' => request('name_part')])) }}"
           @if((string) $selectedProgram === (string) $program) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Filter attendance status">
    @foreach(['present', 'absent', 'late', 'excused'] as $attendanceStatus)
        <a class="btn {{ $status === $attendanceStatus ? 'btn-primary' : 'btn-outline-secondary' }}" @if($status === $attendanceStatus) aria-current="page" @endif href="{{ route('attendance.people', array_filter(['kind' => $kind, 'status' => $attendanceStatus, 'date' => $date, 'session' => $session, 'program' => request('program'), 'search' => request('search'), 'name_part' => request('name_part')])) }}">{{ ucfirst($attendanceStatus) }}</a>
    @endforeach
</nav>
<section class="panel p-3 mb-3" aria-label="Find {{ $kind }}">
    <form method="get" action="{{ route('attendance.people', ['kind' => $kind, 'status' => $status]) }}" data-attendance-search class="d-flex flex-wrap align-items-end gap-2">
        <input type="hidden" name="date" value="{{ $date }}">
        @foreach(['session', 'program'] as $filter)
            @if(request($filter))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif
        @endforeach
        <div><label for="attendance-name-part" class="form-label">Search by</label><select id="attendance-name-part" name="name_part" class="form-select"><option value="first" @selected(request('name_part', 'first') === 'first')>First name</option><option value="last" @selected(request('name_part') === 'last')>Last name</option></select></div>
        <div class="flex-grow-1"><label for="attendance-name-search" class="form-label">Search {{ $kind === 'teachers' ? 'teacher' : 'student' }} name</label><input id="attendance-name-search" type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Enter a {{ $kind === 'teachers' ? 'teacher' : 'student' }} name" maxlength="100"></div>
        <noscript><button class="btn btn-primary" type="submit">Search</button></noscript>
        <a class="btn btn-outline-secondary" href="{{ route('attendance.people', ['kind' => $kind, 'status' => $status, 'date' => $date, 'session' => $session]) }}">Reset filters</a>
    </form>

</section>
<p class="small text-secondary" data-live-search-status role="status" aria-live="polite"></p>
<section class="panel"><div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>{{ $kind === 'teachers' ? 'Program' : 'Class' }}</th><th>Status</th><th>Notes</th></tr></thead><tbody>
@forelse($records as $record)
@php($person = $kind === 'teachers' ? $record->teacher : $record->student)
<tr><td>{{ $person?->name ?? 'Unavailable' }}</td><td>{{ $kind === 'teachers' ? ($record->program ?: 'Unassigned') : ($person?->schoolClass?->label ?: 'Unassigned') }}</td><td>{{ ucfirst($record->status) }}</td><td>{{ $record->notes ?: '?' }}</td></tr>
@empty
<tr><td colspan="4" class="text-center p-4">No {{ $status }} {{ $kind }} match the selected date, session and filters.</td></tr>
@endforelse
</tbody></table></div></section><div class="mt-3">{{ $records->links() }}</div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/attendance-people.js') }}?v={{ filemtime(public_path('js/attendance-people.js')) }}" defer></script>
@endpush
