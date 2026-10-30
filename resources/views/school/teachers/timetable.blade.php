@extends('school.layout')
@section('title', $teacher->name.' schedule')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">TEACHER SCHEDULE</div><h1>@include('school.partials.avatar', ['person' => $teacher]){{ $teacher->name }}</h1><p>{{ $teacher->program ?: 'Khmer' }} · View teaching lessons and weekly free periods.</p></div>
    @if(auth()->user()->role === 'administrator')
        <a class="btn btn-outline-secondary" href="{{ route('modules.index', 'teachers') }}">Back to teachers</a>
    @endif
</div>
<div class="d-flex flex-wrap gap-2 mb-3 d-print-none">
@if(auth()->user()->role === 'administrator')
<form method="post" action="{{ route('teacher-management.schedules.preview', $teacher) }}">@csrf<button class="btn btn-primary">{{ $breaks->isEmpty() ? 'Generate Schedule' : 'Regenerate Schedule' }}</button></form>
<a class="btn btn-outline-secondary" href="{{ route('teacher-management.show', $teacher) }}">Edit Schedule</a>
@endif
<button type="button" class="btn btn-outline-secondary" onclick="window.print()">Print Schedule</button>
</div>
<section class="panel p-3 mb-4 d-print-none">
<label for="schedule-teacher" class="form-label">Teacher</label><select id="schedule-teacher" class="form-select mb-3" onchange="window.location.assign(this.value)">@foreach($scheduleTeachers as $t)<option value="{{ route('teachers.schedule', $t) }}" @selected($t->id === $teacher->id)>{{ $t->name }}</option>@endforeach</select>
<form method="get" class="row g-3">
<div class="col-md-3"><label for="schedule-grade" class="form-label">Grade</label><select id="schedule-grade" name="grade" class="form-select"><option value="">All grades</option>@foreach($filterClasses->pluck('name')->unique() as $grade)<option @selected(request('grade') === $grade)>{{ $grade }}</option>@endforeach</select></div>
<div class="col-md-3"><label for="schedule-class" class="form-label">Class</label><select id="schedule-class" name="class" class="form-select"><option value="">All classes</option>@foreach($filterClasses as $c)<option value="{{ $c->id }}" @selected((int)request('class') === $c->id)>{{ $c->label }}</option>@endforeach</select></div>
<div class="col-md-3"><label for="schedule-subject" class="form-label">Subject</label><select id="schedule-subject" name="subject" class="form-select"><option value="">All subjects</option>@foreach($filterSubjects as $s)<option value="{{ $s->id }}" @selected((int)request('subject') === $s->id)>{{ $s->name }} · {{ $s->schoolClass?->label }}</option>@endforeach</select></div>
<div class="col-md-3"><label for="schedule-session" class="form-label">Session</label><select id="schedule-session" name="session" class="form-select"><option value="">All sessions</option>@foreach(['Morning', 'Afternoon', 'Evening'] as $session)<option @selected(request('session') === $session)>{{ $session }}</option>@endforeach</select></div>
<div><button class="btn btn-primary">Filter</button> <a href="{{ route('teachers.schedule', $teacher) }}" class="btn btn-outline-secondary">Reset</a></div>
</form></section>
<section class="panel p-3 mb-3" aria-label="Teacher workload">
<?php $activity = $service->availability($teacher); ?>
<div data-teacher-live data-endpoint="{{ route('teachers.schedule.status', $teacher) }}"><p><strong>Current availability:</strong> <span data-teacher-status>{{ $activity['status'] }}</span> · <span data-teacher-time>{{ $activity['time'] }}</span></p><p>Current: <span data-teacher-current>{{ $activity['current'] }}</span></p><p>Next: <span data-teacher-next>{{ $activity['next'] }}</span></p></div>
<p>Teaching Hours: <strong>{{ $workload['weekly'] }} / {{ $workload['limit'] }}</strong> · Designated Free Periods: <strong>{{ $breaks->count() }} / 3</strong> · {{ $workload['status'] }}</p>
<p>Subjects: {{ $workload['subjects']->join(', ') ?: 'Not assigned' }}</p>
<p class="mb-0">Assigned Classes: {{ $workload['classes']->map(fn ($c) => $c->label)->join(', ') ?: 'Not assigned' }}</p>
</section>
@include('school.teachers.weekly-grid')
<details class="mt-4"><summary>Session lesson details</summary>
@php
    // Teacher breaks are independent of the class relaxation entries.
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
    <p>Teaching: {{ $workload['weekly'] }} / {{ $workload['limit'] }} hours · Breaks: {{ $workload['breaks'] }} / 3 hours · {{ $workload['status'] }}</p>
    @if($teacherBreaks->isNotEmpty())<div class="mb-3"><strong>Scheduled teacher breaks</strong><ul>@foreach($teacherBreaks as $break)<li>{{ $break->weekday }} · {{ substr($break->start_time, 0, 5) }}–{{ substr($break->end_time, 0, 5) }} · BREAK TIME · 1 HOUR</li>@endforeach</ul></div>@endif
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
</details>
@endsection
@push('scripts')<script src="{{ asset('js/teacher-lesson-status.js') }}" defer></script>@endpush
