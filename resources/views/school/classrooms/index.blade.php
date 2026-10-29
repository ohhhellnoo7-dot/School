@extends('school.layout')
@section('title', 'Classroom Availability')

@section('content')
<div class="page-heading classroom-heading">
    <div>
        <div class="eyebrow"><span class="live-dot" aria-hidden="true"></span> Live room monitor</div>
        <h1>Classroom Availability</h1>
        <p>Monitor classroom usage across all academic programs.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-outline-secondary" type="button" data-availability-refresh><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i> Refresh</button>
        @if(auth()->user()->role === 'administrator')
            <form method="post" action="{{ route('classrooms.auto-assign') }}" data-confirm="Automatically assign suitable rooms to every unassigned class session? Existing bookings will be preserved.">@csrf<button class="btn btn-outline-secondary"><i class="bi bi-magic me-1" aria-hidden="true"></i> Auto-assign</button></form>
            <a class="btn btn-primary" href="{{ route('classrooms.create') }}"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Add room</a>
        @endif
    </div>
</div>

<section class="panel availability-filter-panel">
    <form class="availability-filters" method="get" action="{{ route('classrooms.index') }}" data-availability-filters>
        <div>
            <label class="form-label" for="availability-program">Program</label>
            <select class="form-select" id="availability-program" name="program"><option value="">All programs</option>@foreach(['Khmer', 'English', 'Chinese'] as $program)<option value="{{ $program }}" @selected(($filters['program'] ?? '') === $program)>{{ $program }}</option>@endforeach</select>
        </div>
        <div>
            <label class="form-label" for="availability-grade">Grade</label>
            <select class="form-select" id="availability-grade" name="grade"><option value="">All grades</option>@for($grade = 1; $grade <= 12; $grade++)<option value="{{ $grade }}" @selected((string) ($filters['grade'] ?? '') === (string) $grade)>Grade {{ $grade }}</option>@endfor</select>
        </div>
        <div>
            <label class="form-label" for="availability-session">Session</label>
            <select class="form-select" id="availability-session" name="session"><option value="">All sessions</option>@foreach(['Morning', 'Afternoon', 'Evening'] as $session)<option value="{{ $session }}" @selected(($filters['session'] ?? '') === $session)>{{ $session }}</option>@endforeach</select>
        </div>
        <div>
            <label class="form-label" for="availability-status">Room status</label>
            <select class="form-select" id="availability-status" name="status"><option value="">All statuses</option>@foreach(['available' => 'Available', 'busy' => 'Busy', 'upcoming' => 'Upcoming', 'out_of_service' => 'Out of service'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        </div>
        <div>
            <label class="form-label" for="availability-date">Date</label>
            <input class="form-control" id="availability-date" type="date" name="date" value="{{ $moment->format('Y-m-d') }}">
        </div>
        <div>
            <label class="form-label" for="availability-time">Time</label>
            <input class="form-control" id="availability-time" type="time" name="time" value="{{ $moment->format('H:i') }}">
        </div>
        <div class="availability-room-filter">
            <label class="form-label" for="availability-room">Room name</label>
            <select class="form-select" id="availability-room" name="room"><option value="">All rooms</option>@foreach($roomNames as $number => $name)<option value="{{ $number }}" @selected(($filters['room'] ?? '') === (string) $number)>{{ $number }}{{ $name && $name !== $number ? ' · '.$name : '' }}</option>@endforeach</select>
        </div>
        <div class="filter-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1" aria-hidden="true"></i> Apply</button><a class="btn btn-light" href="{{ route('classrooms.index') }}">Reset</a></div>
    </form>
</section>

<div class="availability-live-region" aria-live="polite" aria-atomic="true" data-refresh-status>Showing room status for {{ $moment->format('D, M j, Y · H:i') }}. Refreshes every 30 seconds.</div>

<div data-classroom-dashboard data-endpoint="{{ route('classrooms.data') }}" data-interval="30000" data-follow-now="{{ request()->filled('date') || request()->filled('time') ? '0' : '1' }}">
    <div id="availability-results" data-availability-results>
        @include('school.classrooms._availability-results')
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/classroom-availability.js') }}?v={{ filemtime(public_path('js/classroom-availability.js')) }}"></script>
@endpush
