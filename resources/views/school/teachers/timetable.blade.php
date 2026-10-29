@extends('school.layout')
@section('title', $teacher->name.' schedule')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">TEACHER SCHEDULE</div><h1>@include('school.partials.avatar', ['person' => $teacher]){{ $teacher->name }}</h1><p>{{ $teacher->program ?: 'Khmer' }} · Weekly teaching schedule</p></div>
    @if(auth()->user()->role === 'administrator')
        <a class="btn btn-outline-secondary" href="{{ route('modules.index', 'teachers') }}">Back to teachers</a>
    @endif
</div>
@php
    $sessions = collect(\App\Models\SchoolClass::SESSION_HOURS)
        ->filter(fn ($hours, $session) => match ($teacher->program ?: 'Khmer') {
            'Chinese' => $session === 'Evening',
            'Khmer' => $session !== 'Evening',
            default => true,
        });
    $sessionStyles = [
        'Morning' => ['warning', 'sunrise'],
        'Afternoon' => ['info', 'sun'],
        'Evening' => ['primary', 'moon-stars'],
    ];
@endphp
<section class="panel p-3 mb-4">
    <strong>View a session</strong>
    <nav class="d-flex flex-wrap gap-2 mt-2" aria-label="Schedule sessions">
        @foreach($sessions as $session => [$start, $end])
            <a class="btn btn-outline-secondary" href="#schedule-{{ strtolower($session) }}">
                <i class="bi bi-{{ $sessionStyles[$session][1] }} me-1" aria-hidden="true"></i>{{ $session }}
            </a>
        @endforeach
    </nav>
    <p class="small text-secondary mt-3 mb-0">Scheduled subjects: {{ $slots->flatten()->pluck('subject.name')->unique()->join(', ') ?: 'Not assigned' }}</p>
</section>
@foreach($sessions as $session => [$start, $end])
    @php
        [$color, $icon] = $sessionStyles[$session];
        // Use lesson times: legacy classes can contain more than one session.
        $sessionSlots = $slots->map(fn ($daily) => $daily->filter(fn ($slot) =>
            substr($slot->starts_at, 0, 5) >= $start && substr($slot->starts_at, 0, 5) < $end
        )->sortBy('starts_at'));
    @endphp
    <section id="schedule-{{ strtolower($session) }}" class="panel mb-4 border-top border-4 border-{{ $color }}" aria-labelledby="heading-{{ strtolower($session) }}" style="scroll-margin-top: 1rem">
        <div class="panel-header flex-wrap gap-2">
            <h2 id="heading-{{ strtolower($session) }}"><i class="bi bi-{{ $icon }} me-2" aria-hidden="true"></i>{{ $session }}</h2>
            <span class="small text-secondary">{{ \Carbon\Carbon::parse($start)->format('g:i A') }} – {{ \Carbon\Carbon::parse($end)->format('g:i A') }} · {{ $sessionSlots->flatten()->count() }} lessons / week</span>
        </div>
        <div class="p-3">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 row-cols-xxl-5 g-3">
                @foreach(\App\Support\TeacherTimetable::DAYS as $day)
                    <div class="col">
                        <div class="h-100 border rounded p-3">
                            <h3 class="h6 border-bottom pb-2 mb-3">{{ $day }}</h3>
                            @forelse($sessionSlots->get($day, collect()) as $slot)
                                <article class="mb-3">
                                    <div class="small fw-semibold mb-2"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ \Carbon\Carbon::parse($slot->starts_at)->format('g:i A') }} – {{ \Carbon\Carbon::parse($slot->ends_at)->format('g:i A') }}</div>
                                    <h4 class="h6 mb-1">{{ $slot->subject->name }}</h4>
                                    <a class="small d-block" href="{{ route('classes.timetable', $slot->subject->school_class_id) }}">{{ $slot->subject->schoolClass->label }}</a>
                                    <div class="small text-secondary mt-1"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Room: {{ $slot->room ?: 'Not assigned' }}</div>
                                </article>
                            @empty
                                <p class="small text-secondary mb-0">No {{ strtolower($session) }} lessons.</p>
                            @endforelse
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endforeach
@endsection
