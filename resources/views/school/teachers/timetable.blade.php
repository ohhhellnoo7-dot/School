@extends('school.layout')
@section('title', $teacher->name.' · '.__('Teacher schedule'))
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">{{ __('Teacher schedule') }}</div><h1>@include('school.partials.avatar', ['person' => $teacher]){{ $teacher->name }}</h1><p>{{ __($teacher->program ?: 'Khmer') }} · {{ __('View teaching lessons and weekly free periods.') }}</p></div>
    @if(auth()->user()->role === 'administrator')
        <a class="btn btn-outline-secondary" href="{{ route('modules.index', 'teachers') }}">{{ __('Back to teachers') }}</a>
    @endif
</div>
<div class="d-flex flex-wrap gap-2 mb-3 d-print-none">
@if(auth()->user()->role === 'administrator')
<form method="post" action="{{ route('teacher-management.schedules.preview', $teacher) }}">@csrf<button class="btn btn-primary">{{ __($breaks->isEmpty() ? 'Generate Schedule' : 'Regenerate Schedule') }}</button></form>
<a class="btn btn-outline-secondary" href="{{ route('teacher-management.show', $teacher) }}">{{ __('Edit Schedule') }}</a>
@endif
<button type="button" class="btn btn-outline-secondary" onclick="window.print()">{{ __('Print Schedule') }}</button>
</div>
<section class="panel p-3 mb-4 d-print-none">
<label for="schedule-teacher" class="form-label">{{ __('Teacher') }}</label><select id="schedule-teacher" class="form-select mb-3" onchange="window.location.assign(this.value)">@foreach($scheduleTeachers as $t)<option value="{{ route('teachers.schedule', $t) }}" @selected($t->id === $teacher->id)>{{ $t->name }}</option>@endforeach</select>
<form method="get" class="row g-3">
<div class="col-md-3"><label for="schedule-grade" class="form-label">{{ __('Grade') }}</label><select id="schedule-grade" name="grade" class="form-select"><option value="">{{ __('All grades') }}</option>@foreach($filterClasses->pluck('name')->unique() as $grade)<option @selected(request('grade') === $grade)>{{ $grade }}</option>@endforeach</select></div>
<div class="col-md-3"><label for="schedule-class" class="form-label">{{ __('Class') }}</label><select id="schedule-class" name="class" class="form-select"><option value="">{{ __('All classes') }}</option>@foreach($filterClasses as $c)<option value="{{ $c->id }}" @selected((int)request('class') === $c->id)>{{ $c->label }}</option>@endforeach</select></div>
<div class="col-md-3"><label for="schedule-subject" class="form-label">{{ __('Subject') }}</label><select id="schedule-subject" name="subject" class="form-select"><option value="">{{ __('All subjects') }}</option>@foreach($filterSubjects as $s)<option value="{{ $s->id }}" @selected((int)request('subject') === $s->id)>{{ $s->name }} · {{ $s->schoolClass?->label }}</option>@endforeach</select></div>
<div class="col-md-3"><label for="schedule-session" class="form-label">{{ __('Session') }}</label><select id="schedule-session" name="session" class="form-select"><option value="">{{ __('All sessions') }}</option>@foreach(['Morning', 'Afternoon', 'Evening'] as $session)<option @selected(request('session') === $session)>{{ __($session) }}</option>@endforeach</select></div>
<div><button class="btn btn-primary">{{ __('Filter') }}</button> <a href="{{ route('teachers.schedule', $teacher) }}" class="btn btn-outline-secondary">{{ __('Reset') }}</a></div>
</form></section>
<section class="panel p-3 mb-3" aria-label="{{ __('Teacher workload') }}">
<?php $activity = $service->availability($teacher); ?>
<div data-teacher-live data-refresh-error="{{ __('Refresh unavailable') }}" data-endpoint="{{ route('teachers.schedule.status', $teacher) }}"><p><strong>{{ __('Current availability:') }}</strong> <span data-teacher-status>{{ __($activity['status']) }}</span> · <span data-teacher-time>{{ $activity['time'] }}</span></p><p>{{ __('Current:') }} <span data-teacher-current>{{ $activity['current'] }}</span></p><p>{{ __('Next:') }} <span data-teacher-next>{{ $activity['next'] }}</span></p></div>
<p>{{ __('Teaching Hours:') }} <strong>{{ $workload['weekly'] }} / {{ $workload['limit'] }}</strong> · {{ __('Designated Free Periods:') }} <strong>{{ $breaks->count() }} / 3</strong> · {{ __($workload['status']) }}</p>
<p>{{ __('Subjects:') }} {{ $workload['subjects']->join(', ') ?: __('Not assigned') }}</p>
<p class="mb-0">{{ __('Assigned Classes:') }} {{ $workload['classes']->map(fn ($c) => $c->label)->join(', ') ?: __('Not assigned') }}</p>
</section>
@include('school.teachers.weekly-grid')
<details class="mt-4"><summary>{{ __('Session lesson details') }}</summary>
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
    <p>{{ __('Teaching:') }} {{ $workload['weekly'] }} / {{ $workload['limit'] }} {{ __('hours') }} · {{ __('Breaks:') }} {{ $workload['breaks'] }} / 3 {{ __('hours') }} · {{ __($workload['status']) }}</p>
    @if($teacherBreaks->isNotEmpty())<div class="mb-3"><strong>{{ __('Scheduled teacher breaks') }}</strong><ul>@foreach($teacherBreaks as $break)<li>{{ __($break->weekday) }} · {{ substr($break->start_time, 0, 5) }}–{{ substr($break->end_time, 0, 5) }} · {{ __('BREAK TIME') }} · {{ __('1 HOUR') }}</li>@endforeach</ul></div>@endif
    <strong>{{ __('View a session') }}</strong>
    <nav class="d-flex flex-wrap gap-2 mt-2" aria-label="{{ __('Schedule sessions') }}">
        @foreach($sessions as $session => [$start, $end])
            <a class="btn btn-outline-secondary" href="#schedule-{{ strtolower($session) }}">
                <i class="bi bi-{{ $sessionStyles[$session][1] }} me-1" aria-hidden="true"></i>{{ __($session) }}
            </a>
        @endforeach
    </nav>
    <p class="small text-secondary mt-3 mb-0">{{ __('Scheduled subjects:') }} {{ $slots->flatten()->pluck('subject.name')->unique()->join(', ') ?: __('Not assigned') }}</p>
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
            <h2 id="heading-{{ strtolower($session) }}"><i class="bi bi-{{ $icon }} me-2" aria-hidden="true"></i>{{ __($session) }}</h2>
            <span class="small text-secondary">{{ \Carbon\Carbon::parse($start)->format('g:i A') }} – {{ \Carbon\Carbon::parse($end)->format('g:i A') }} · {{ $sessionSlots->flatten()->count() }} {{ __('lessons / week') }}</span>
        </div>
        <div class="p-3">
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 row-cols-xxl-5 g-3">
                @foreach(\App\Support\TeacherTimetable::DAYS as $day)
                    <div class="col">
                        <div class="h-100 border rounded p-3">
                            <h3 class="h6 border-bottom pb-2 mb-3">{{ __($day) }}</h3>
                            @forelse($sessionSlots->get($day, collect()) as $slot)
                                <article class="mb-3">
                                    <div class="small fw-semibold mb-2"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ \Carbon\Carbon::parse($slot->starts_at)->format('g:i A') }} – {{ \Carbon\Carbon::parse($slot->ends_at)->format('g:i A') }}</div>
                                    <h4 class="h6 mb-1">{{ $slot->subject->name }}</h4>
                                    <a class="small d-block" href="{{ route('classes.timetable', $slot->subject->school_class_id) }}">{{ $slot->subject->schoolClass->label }}</a>
                                    <div class="small text-secondary mt-1"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ __('Room:') }} {{ $slot->room ?: __('Not assigned') }}</div>
                                </article>
                            @empty
                                <p class="small text-secondary mb-0">{{ __('No :session lessons.', ['session' => __($session)]) }}</p>
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
