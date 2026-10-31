@extends('school.layout')
@section('title', $classGroup->display_name)

@section('content')
<div class="page-heading">
    <div><div class="eyebrow">{{ $classGroup->program }} program · Grade {{ $classGroup->grade }}</div><h1>{{ $classGroup->display_name }}</h1><p>One academic class identity across {{ $classGroup->sessions->count() }} study {{ \Illuminate\Support\Str::plural('session', $classGroup->sessions->count()) }}.</p></div>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="{{ route('class-groups.index') }}">Back to classes</a>@if(auth()->user()->role === 'administrator')<a class="btn btn-primary" href="{{ route('class-groups.edit', $classGroup) }}"><i class="bi bi-pencil me-1" aria-hidden="true"></i> Edit</a>@endif</div>
</div>

<section class="detail-stat-grid" aria-label="Class group summary">
    <div class="detail-stat"><i class="bi bi-mortarboard" aria-hidden="true"></i><span><small>{{ __('Program') }}</small><strong>{{ $classGroup->program }}</strong></span></div>
    <div class="detail-stat"><i class="bi bi-people" aria-hidden="true"></i><span><small>{{ __('Students') }}</small><strong>{{ $classGroup->students_count }} / {{ $classGroup->capacity }}</strong></span></div>
    <div class="detail-stat"><i class="bi bi-person-workspace" aria-hidden="true"></i><span><small>Class teacher</small><strong>{{ $classGroup->teacher?->name ?? 'Unassigned' }}</strong></span></div>
    <div class="detail-stat"><i class="bi bi-activity" aria-hidden="true"></i><span><small>{{ __('Status') }}</small><strong>{{ ucfirst($classGroup->status) }}</strong></span></div>
</section>

<div class="row g-4">
    <div class="col-xl-4">
        <section class="panel h-100">
            <div class="panel-header"><div><h2>Study sessions</h2><p>Standard hours for this program</p></div></div>
            <div class="panel-body session-list">
                @foreach($classGroup->sessions as $session)
                    <div><span class="session-symbol"><i class="bi bi-clock" aria-hidden="true"></i></span><span><strong>{{ $session->session }}</strong><small>{{ substr((string) $session->starts_at, 0, 5) }}–{{ substr((string) $session->ends_at, 0, 5) }} · Monday–Friday</small></span></div>
                @endforeach
            </div>
        </section>
    </div>
    <div class="col-xl-8">
        <section class="panel h-100">
            <div class="panel-header"><div><h2>Classroom assignments</h2><p>Rooms can change by session and weekday.</p></div>@if(auth()->user()->role === 'administrator')<a class="btn btn-sm btn-primary" href="{{ route('classroom-bookings.create', ['class_group_id' => $classGroup->id]) }}">Assign classroom</a>@endif</div>
            <div class="table-responsive"><table class="table"><thead><tr><th>Day</th><th>{{ __('Session') }}</th><th>{{ __('Room') }}</th><th>{{ __('Time') }}</th><th>{{ __('Teacher') }}</th><th>Action</th></tr></thead><tbody>
                @forelse($classGroup->bookings as $booking)
                    <tr><td>{{ $booking->day_of_week }}</td><td>{{ $booking->classSession?->session }}</td><td><a href="{{ route('classrooms.show', $booking->classroom) }}">{{ $booking->classroom->room_number }}</a></td><td>{{ substr((string) $booking->starts_at, 0, 5) }}–{{ substr((string) $booking->ends_at, 0, 5) }}</td><td>{{ $booking->teacher?->name ?? $classGroup->teacher?->name ?? 'Unassigned' }}</td><td><a class="btn btn-sm btn-outline-secondary" href="{{ route('classrooms.show', $booking->classroom) }}#reservation-{{ $booking->id }}">View Lessons</a>@if(auth()->user()->role === 'administrator')<a class="btn btn-sm btn-light" href="{{ route('classroom-bookings.edit', $booking) }}" aria-label="Edit booking"><i class="bi bi-pencil" aria-hidden="true"></i></a>@else<span class="text-secondary">View only</span>@endif</td></tr>
                @empty<tr><td colspan="6"><div class="empty-state py-4">No classrooms assigned yet.</div></td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>
</div>
@endsection
