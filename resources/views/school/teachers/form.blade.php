@extends('school.layout')
@section('title', $teacher->exists ? 'Edit Teacher' : 'Add Teacher')
@section('content')
@php
    $qualified = $teacher->exists ? $service->qualifications($teacher) : collect();
    $periods = $teacher->exists ? $service->periods($teacher) : collect();
@endphp
<div class="page-heading"><div><div class="eyebrow">School workspace</div><h1>@if($teacher->exists)@include('school.partials.avatar', ['person' => $teacher, 'teacherIcon' => true])@endif{{ $teacher->exists ? 'Edit Teacher' : 'Add Teacher' }}</h1><p>Confirm the teacher’s subjects and working hours.</p></div><a href="{{ route('modules.index', 'teachers') }}" class="btn btn-outline-secondary">Back to Teachers</a></div>
@if($teacher->exists)
    @php
        $sessionStops = app(\App\Services\TeacherSessionStops::class);
        $pendingStops = $sessionStops->pending($teacher);
        $assignedSessions = $sessionStops->sessions($teacher)->merge($pendingStops->keys())->unique();
    @endphp
    <section class="panel p-4 mb-3" aria-labelledby="assigned-sessions-heading">
        <h2 id="assigned-sessions-heading" class="h5">{{ __('Assigned sessions') }}</h2>
        <p class="text-secondary">{{ __('A scheduled stop takes effect on the first day of next month.') }}</p>
        @forelse($assignedSessions as $session)
            @php
                $pendingStop = $pendingStops->get($session);
            @endphp
            <form class="d-flex align-items-center flex-wrap gap-3 border-top py-2" method="post" action="{{ route($pendingStop ? 'teacher-management.sessions.cancel' : 'teacher-management.sessions.stop', $teacher) }}">
                @csrf
                @if($pendingStop) @method('delete') @endif
                <input type="hidden" name="session" value="{{ $session }}">
                <strong>{{ __($session) }}</strong>
                @if($pendingStop)
                    <span class="text-secondary">{{ __('Stops on :date', ['date' => $pendingStop->effective_on->translatedFormat('d M Y')]) }}</span>
                    <button class="btn btn-sm btn-outline-secondary ms-auto" type="submit">{{ __('Cancel stop') }}</button>
                @else
                    <button class="btn btn-sm btn-outline-danger ms-auto" type="submit">{{ __('Stop next month') }}</button>
                @endif
            </form>
        @empty
            <p class="mb-0 text-secondary">{{ __('No assigned sessions.') }}</p>
        @endforelse
    </section>
@endif
<form method="post" action="{{ $teacher->exists ? route('teacher-management.update', $teacher) : route('teacher-management.store') }}" class="panel p-4">@csrf @if($teacher->exists)@method('put')@endif
<div class="row g-3">
<script type="application/json" data-teacher-program-options>@json($programOptions)</script>
@foreach(['name' => 'Teacher Name', 'phone' => 'Phone', 'email' => 'Email'] as $field => $label)<div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" class="form-control" value="{{ old($field, $teacher->$field) }}" @required($field !== 'phone')></div>@endforeach
<div class="col-md-6">
    <label for="gender" class="form-label">{{ __('Gender') }}</label>
    <select id="gender" name="gender" class="form-select" required>
        <option value="">{{ __('Select gender') }}</option>
        @foreach(['male', 'female', 'other'] as $gender)
            <option value="{{ $gender }}" @selected(old('gender', $teacher->gender) === $gender)>{{ __(ucfirst($gender)) }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-6"><label for="program" class="form-label">Academic Program</label><select id="program" name="program" class="form-select" required>@foreach(\App\Models\Program::names() as $program)<option @selected(old('program', $teacher->program) === $program)>{{ $program }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label" for="weekly_hours">Maximum Weekly Teaching Hours</label><input id="weekly_hours" name="weekly_hours" type="number" step="0.25" min="1" max="40" class="form-control" value="{{ old('weekly_hours', $teacher->exists ? $service->limit($teacher) / 60 : $service->defaultLimit(old('program', $teacher->program ?: 'Khmer')) / 60) }}"><small class="text-secondary">Classroom teaching only. Preparation, marking, and other duties are additional. Clear to follow the program teaching limit.</small></div>
<div class="col-md-6" data-teacher-subject-options><div class="row g-3">
@foreach([1, 2] as $number)<div class="col-md-6 w-100"><label class="form-label" for="subject{{ $number }}">Subject {{ $number }} {{ $number === 2 ? '(Optional)' : '' }}</label><select id="subject{{ $number }}" name="subject{{ $number }}" class="form-select" @required($number === 1)><option value="">Choose a verified subject</option>@foreach($catalog as $name)<option @selected(old('subject'.$number, $qualified[$number - 1] ?? null) === $name)>{{ $name }}</option>@endforeach</select></div>@endforeach
</div></div>
<div class="col-md-6"><label class="form-label" for="additional-subjects">Program subjects</label><select id="additional-subjects" name="additional_subjects[]" class="form-select" multiple>@foreach($catalog as $name)<option @selected(in_array($name, old('additional_subjects', $qualified->slice(2)->values()->all()), true))>{{ $name }}</option>@endforeach</select><small class="text-secondary">Subjects are listed for the selected program.</small></div>
<div class="col-md-6"><label class="form-label" for="working_sessions">Working Sessions</label><select id="working_sessions" name="working_sessions" class="form-select">@foreach(['Morning' => 'Morning · 07:00–11:00', 'Afternoon' => 'Afternoon · 13:00–17:00', 'Both' => 'Morning and Afternoon', 'Evening' => 'Evening · 16:30–18:30', 'EnglishFullTime' => 'English full-time - 8 hours/day (40/week)', 'EnglishPartTime' => 'English part-time - 2 hours/day (10/week)', 'Custom' => 'Custom weekly hours'] as $value => $label)<option value="{{ $value }}" @selected(old('working_sessions', $periods->isNotEmpty() ? 'Custom' : ($teacher->program === 'Chinese' ? 'Evening' : 'Morning')) === $value)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label" for="part_time_start">English part-time start time</label><input type="time" id="part_time_start" name="part_time_start" class="form-control" value="{{ old('part_time_start', '07:00') }}"><small class="text-secondary">Two hours each weekday within morning, afternoon, or evening hours. Full-time hours are 07:00-11:00 and 13:00-17:00.</small></div>
<div class="col-md-6"><label for="status" class="form-label">{{ __('Status') }}</label><select id="status" name="status" class="form-select">@foreach(['active', 'inactive'] as $status)<option @selected(old('status', $teacher->status) === $status)>{{ $status }}</option>@endforeach</select></div>
</div>
<details class="my-4" @if($periods->isNotEmpty()) open @endif><summary>Custom working hours and unavailable periods</summary><p class="small text-secondary mt-2">Select Custom weekly hours above to use these windows. Each checked row records duty hours or an unavailable period.</p>
@php
    $custom = collect(old('periods', $periods->whereIn('weekday', \App\Services\TeacherManagementService::DAYS)->map(fn ($p) => (array) $p)->all()))->values();
@endphp
@foreach(range(0, max(9, $custom->count() - 1)) as $index)
@php
    $p = $custom[$index] ?? null;
@endphp
<div class="teacher-period-row"><label><input type="checkbox" class="form-check-input" data-period="period-{{ $index }}" @checked($p)> Use</label><fieldset id="period-{{ $index }}" class="teacher-period-fields" @disabled(!$p)><legend class="visually-hidden">Working period {{ $index + 1 }}</legend><select name="periods[{{ $index }}][weekday]" class="form-select" aria-label="Weekday">@foreach(\App\Services\TeacherManagementService::DAYS as $day)<option @selected(($p['weekday'] ?? '') === $day)>{{ $day }}</option>@endforeach</select><input class="form-control" type="time" aria-label="Start time" name="periods[{{ $index }}][start_time]" value="{{ substr($p['start_time'] ?? '07:00', 0, 5) }}"><input class="form-control" type="time" aria-label="End time" name="periods[{{ $index }}][end_time]" value="{{ substr($p['end_time'] ?? '11:00', 0, 5) }}"><select name="periods[{{ $index }}][unavailable]" class="form-select" aria-label="Working or unavailable"><option value="0" @selected(!($p['unavailable'] ?? false))>On duty</option><option value="1" @selected($p['unavailable'] ?? false)>Unavailable</option></select></fieldset></div>
@endforeach</details>
@php
    $weekend = collect(old('weekend_periods', old('update_weekend_periods') ? [] : $periods->whereIn('weekday', ['Saturday', 'Sunday'])->map(fn ($p) => (array) $p)->all()));
@endphp
<fieldset id="teacher-weekend-hours" data-teacher-weekend class="my-4">
    <legend class="h5">Weekend working hours</legend>
    <input type="hidden" name="update_weekend_periods" value="1">
    <div class="row g-3">
        @foreach(['Saturday', 'Sunday'] as $day)
            @php
                $saved = $weekend->where('weekday', $day)->values();
            @endphp
            <div class="col-lg-6">
                <h3 class="h6">{{ $day }}</h3>
                @for($index = 0; $index < max(3, $saved->count()); $index++)
                    @php
                        $p = $saved[$index] ?? null;
                        $preset = array_values(config('classrooms.sessions'))[$index % 3];
                        $key = strtolower($day).$index;
                    @endphp
                    <div class="border rounded p-2 mb-2" data-weekend-work-row>
                        <label for="enable-{{ $key }}" class="form-check-label mb-2"><input id="enable-{{ $key }}" type="checkbox" class="form-check-input" data-period="{{ $key }}" @checked($p)> {{ $p ? 'Work block '.($index + 1) : ['Morning', 'Afternoon', 'Evening'][$index % 3] }}</label>
                        <fieldset id="{{ $key }}" data-weekend-work-fields @disabled(!$p)>
                            <input type="hidden" name="weekend_periods[{{ $key }}][weekday]" value="{{ $day }}">
                            <div class="row g-2">
                                <div class="col"><label class="form-label" for="start-{{ $key }}">Start</label><input class="form-control" id="start-{{ $key }}" type="time" name="weekend_periods[{{ $key }}][start_time]" value="{{ substr($p['start_time'] ?? $preset['starts_at'], 0, 5) }}" required></div>
                                <div class="col"><label class="form-label" for="hours-{{ $key }}">Hours</label><input class="form-control" id="hours-{{ $key }}" type="number" min="0.01" max="24" step="any" data-work-duration required></div>
                                <div class="col"><label class="form-label" for="end-{{ $key }}">End</label><input class="form-control" id="end-{{ $key }}" type="time" name="weekend_periods[{{ $key }}][end_time]" value="{{ substr($p['end_time'] ?? $preset['ends_at'], 0, 5) }}" required></div>
                            </div>
                            <label class="visually-hidden" for="status-{{ $key }}">Availability</label>
                            <select id="status-{{ $key }}" class="form-select mt-2" name="weekend_periods[{{ $key }}][unavailable]">
                                <option value="0" @selected(!($p['unavailable'] ?? false))>On duty</option>
                                <option value="1" @selected($p['unavailable'] ?? false)>Unavailable</option>
                            </select>
                        </fieldset>
                    </div>
                @endfor
            </div>
        @endforeach
    </div>
    <output class="fw-semibold" data-teacher-work-total aria-live="polite"></output>
</fieldset>
<p class="small text-secondary">Saving verified qualifications does not remove historical teaching assignments. Review any existing subject-limit or timetable warnings in the profile.</p>
<button class="btn btn-primary">Save Teacher</button></form>
@endsection
@push('scripts')<script src="{{ asset('js/teacher-management.js') }}?v={{ filemtime(public_path('js/teacher-management.js')) }}" defer></script>@endpush
@unless($teacher->exists)
@push('scripts')
<script src="{{ asset('js/enrollment-emails.js') }}?v={{ filemtime(public_path('js/enrollment-emails.js')) }}"></script>
@endpush
@endunless
