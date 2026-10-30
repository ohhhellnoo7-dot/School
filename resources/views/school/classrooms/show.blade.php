@extends('school.layout')
@section('title', 'Room '.$classroom->room_number)

@section('content')
@php
    $status = data_get($snapshot, 'status', $classroom->status === 'out_of_service' ? 'out_of_service' : 'available');
    $current = data_get($snapshot, 'current');
    $next = data_get($snapshot, 'next');
@endphp
<div class="page-heading">
    <div><div class="eyebrow">Physical classroom</div><h1>Room {{ $classroom->room_number }}</h1><p>{{ $classroom->name }} · Live status for {{ $moment->format('D, M j, Y · H:i') }}</p></div>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="{{ route('classrooms.index') }}">Back to availability</a>@if(auth()->user()->role === 'administrator')<a class="btn btn-outline-secondary" href="{{ route('classrooms.edit', $classroom) }}"><i class="bi bi-pencil me-1" aria-hidden="true"></i> Edit room</a><a class="btn btn-primary" href="{{ route('classroom-bookings.create', ['classroom_id' => $classroom->id]) }}"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Add booking</a>@endif</div>
</div>

<section class="room-detail-hero"><div class="room-detail-icon"><i class="bi bi-door-open" aria-hidden="true"></i></div><div><h2>{{ $classroom->name }}</h2><p>Lesson activity and room occupancy are shown separately below.</p></div><dl><div><dt>Room number</dt><dd>{{ $classroom->room_number }}</dd></div><div><dt>Capacity</dt><dd>{{ $classroom->capacity }} students</dd></div><div><dt>Maintenance</dt><dd>{{ str_replace('_', ' ', ucfirst($classroom->status)) }}</dd></div></dl></section>

<section class="panel p-4 my-4" data-room-live data-endpoint="{{ route('classrooms.lesson-status', $classroom) }}" data-live="{{ (!request()->filled('date') && !request()->filled('time')) || request()->boolean('live') ? '1' : '0' }}" data-date="{{ $moment->format('Y-m-d') }}" data-time="{{ $moment->format('H:i') }}">
<div class="d-flex justify-content-between gap-3"><h2 class="h5">Current and next lesson</h2><a class="btn btn-outline-secondary" href="{{ route('classrooms.show', ['classroom' => $classroom, 'live' => 1]) }}">Follow current time</a></div>
<p class="small text-secondary" data-room-live-label aria-live="polite">{{ $moment->format('D, M j, Y H:i') }}</p><div data-room-live-content>@include('school.classrooms._lesson-status')</div>
</section>
<section class="panel p-4 mb-4"><h2 class="h5">Daily lessons &middot; {{ $moment->englishDayOfWeek }}</h2>
@forelse($lessons->where('day', $moment->englishDayOfWeek) as $lesson)
<article class="schedule-cell"><strong>{{ substr($lesson->starts_at, 0, 5) }}&ndash;{{ substr($lesson->ends_at, 0, 5) }} &middot; {{ $lesson->isRelaxation() ? 'Scheduled relaxation' : $lesson->subject?->name }}</strong><span>{{ $lesson->schoolClass?->label }}</span><small>{{ $lesson->isRelaxation() ? 'No teacher' : $lesson->teacher?->name }}</small></article>
@empty<p>No lessons scheduled today. Reservations can still occupy the room.</p>@endforelse
</section>
<section class="panel p-4 mb-4"><h2 class="h5">Weekly lesson rotation</h2><div class="table-responsive"><table class="table"><thead><tr>@foreach($days as $day)<th>{{ $day }}</th>@endforeach</tr></thead><tbody><tr>@foreach($days as $day)<td>@forelse($lessons->where('day', $day) as $lesson)<article class="schedule-cell"><strong>{{ $lesson->isRelaxation() ? 'Scheduled relaxation' : $lesson->subject?->name }}</strong><span>{{ $lesson->schoolClass?->label }}</span><small>{{ substr($lesson->starts_at, 0, 5) }}&ndash;{{ substr($lesson->ends_at, 0, 5) }} &middot; {{ $lesson->isRelaxation() ? 'No teacher' : $lesson->teacher?->name }}</small></article>@empty<span>No Class</span>@endforelse</td>@endforeach</tr></tbody></table></div></section>
<section class="panel weekly-room-panel" id="weekly-lessons">
    <div class="panel-header"><div><h2>Room reservations</h2><p>Recurring classroom bookings from Monday to Friday.</p></div><form class="detail-moment-form" method="get"><label class="visually-hidden" for="detail-date">Date</label><input class="form-control" id="detail-date" type="date" name="date" value="{{ $moment->format('Y-m-d') }}"><label class="visually-hidden" for="detail-time">Time</label><input class="form-control" id="detail-time" type="time" name="time" value="{{ $moment->format('H:i') }}"><button class="btn btn-outline-secondary">Check time</button></form></div>
    <div class="table-responsive"><table class="table weekly-room-table"><thead><tr><th>Session</th>@foreach($days as $day)<th>{{ $day }}</th>@endforeach</tr></thead><tbody>
        @foreach($sessions as $session)
            <tr><th><span class="session-row-title">{{ $session }}</span></th>
                @foreach($days as $day)
                    <td>
                        @forelse($weekly[$session][$day] as $booking)
                            <div class="schedule-cell"><strong>{{ $booking->classGroup->display_name }}</strong><span>{{ $booking->classGroup->program }} · {{ substr((string) $booking->starts_at, 0, 5) }}-{{ substr((string) $booking->ends_at, 0, 5) }}</span><small>Session reservation</small>@include('school.classrooms.reservation-lessons')@if(auth()->user()->role === 'administrator')<a href="{{ route('classroom-bookings.edit', $booking) }}" aria-label="Edit {{ $booking->classGroup->display_name }} booking"><i class="bi bi-pencil" aria-hidden="true"></i></a>@endif</div>
                        @empty<span class="open-slot"><i class="bi bi-check-circle" aria-hidden="true"></i> Available</span>@endforelse
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody></table></div>
</section>

<section class="panel booking-list-panel">
    <div class="panel-header"><div><h2>All recurring bookings</h2><p>{{ $classroom->bookings->count() }} scheduled {{ \Illuminate\Support\Str::plural('entry', $classroom->bookings->count()) }}</p></div></div>
    <div class="table-responsive"><table class="table"><thead><tr><th>Day</th><th>Class</th><th>Program</th><th>Session</th><th>Time</th><th>Teacher</th>@if(auth()->user()->role === 'administrator')<th>Actions</th>@endif</tr></thead><tbody>
        @forelse($classroom->bookings as $booking)
            <tr><td>{{ $booking->day_of_week }}</td><td><a href="{{ route('class-groups.show', $booking->classGroup) }}">{{ $booking->classGroup->display_name }}</a></td><td><span class="program-badge program-{{ strtolower($booking->classGroup->program) }}">{{ $booking->classGroup->program }}</span></td><td>{{ $booking->classSession?->session }}</td><td>{{ substr((string) $booking->starts_at, 0, 5) }}-{{ substr((string) $booking->ends_at, 0, 5) }}</td><td>See lesson rotation</td>@if(auth()->user()->role === 'administrator')<td><div class="table-actions"><a class="btn btn-sm btn-light" href="{{ route('classroom-bookings.edit', $booking) }}" aria-label="Edit booking"><i class="bi bi-pencil" aria-hidden="true"></i></a><form method="post" action="{{ route('classroom-bookings.destroy', $booking) }}" data-confirm="Remove this classroom booking?">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" aria-label="Remove booking"><i class="bi bi-trash" aria-hidden="true"></i></button></form></div></td>@endif</tr>
        @empty<tr><td colspan="7"><div class="empty-state py-4">No classroom bookings have been added.</div></td></tr>@endforelse
    </tbody></table></div>
</section>

@if(auth()->user()->role === 'administrator')
<div class="danger-zone"><div><strong>Delete physical classroom</strong><span>Rooms with timetable bookings are protected.</span></div><form method="post" action="{{ route('classrooms.destroy', $classroom) }}" data-confirm="Delete this physical classroom?">@csrf @method('DELETE')<button class="btn btn-outline-danger">Delete room</button></form></div>
@endif
@endsection

@push('scripts')<script src="{{ asset('js/room-lesson-status.js') }}" defer></script><script>function showReservationLessons(){ const target = document.getElementById(location.hash.slice(1)); if(target && target.matches('details.reservation-lessons')) target.open = true; } showReservationLessons(); addEventListener('hashchange', showReservationLessons);</script>@endpush
