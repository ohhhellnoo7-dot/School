@extends('school.layout')
@section('title', __('Students'))
@section('content')
<x-scholar-hero tag="section" class="student-hero school-hero" aria-labelledby="student-page-title">
    <img src="{{ asset('images/student-community.png') }}" alt="" width="2048" height="768" fetchpriority="high">
    <i class="bi bi-people-fill student-hero-icon" aria-hidden="true"></i>
    <div class="student-hero-copy">
        <div class="eyebrow">{{ __('OUR SCHOOL COMMUNITY') }}</div>
        <h1 id="student-page-title">{{ __('Students') }}</h1>
        <p>{{ __('Manage student information, enrollment, and attendance.') }}</p>
    </div>
    @can('create', \App\Models\Student::class)<a href="{{ route('students.create') }}" class="btn btn-primary hero-enroll"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>{{ __('Enroll student') }}</a>@endcan
</x-scholar-hero>
@php
    $statuses = [
        'present' => ['Present', 'patch-check-fill'],
        'absent' => ['Absent', 'person-x-fill'],
        'late' => ['Late', 'clock-fill'],
        'excused' => ['Excused', 'shield-fill-check'],
        'unrecorded' => ['Not Marked', 'dash-circle'],
    ];
    $formatPercent = fn ($key) => $attendanceCounts['total']
        ? round($attendanceCounts[$key] / $attendanceCounts['total'] * 100, 1)
        : 0;
    $displayDate = \Illuminate\Support\Carbon::parse($attendanceDate)->translatedFormat('d M Y');
    $isToday = $attendanceDate === today()->toDateString();
    $advancedActive = request()->filled('class') || request()->filled('program') || request()->filled('session')
        || request()->filled('status') || request('sort', 'name') !== 'name' || request('direction', 'asc') !== 'asc';
@endphp
<div class="attendance-summary">
    <div class="attendance-cards">
        @foreach(['total' => ['Total Students', 'people-fill'], ...array_slice($statuses, 0, 4)] as $key => [$label, $icon])
            <article class="attendance-summary-card attendance-tone-{{ $key }}" @if($key === 'excused') id="student-excuses" @endif>
                <span class="attendance-summary-icon" aria-hidden="true"><i class="bi bi-{{ $icon }}"></i></span>
                <h2>{{ $isToday ? __(['present' => 'Present Today', 'absent' => 'Absent Today', 'late' => 'Late Today', 'excused' => 'Excused Today'][$key] ?? $label) : __($label) }}</h2>
                <strong data-attendance-count="{{ $key }}" data-count="{{ $attendanceCounts[$key] }}">{{ number_format($attendanceCounts[$key]) }}</strong>
                @if($key === 'total')
                    <small class="attendance-summary-note">{{ __('Matching students') }}</small>
                @else
                    <small class="attendance-summary-note"><b data-attendance-percent="{{ $key }}">{{ $formatPercent($key) }}%</b></small>
                @endif
                @if($key === 'excused' && auth()->user()->role === 'administrator')
                    <button type="button" class="btn btn-link stretched-link p-0" data-open-excuse-report="{{ $attendanceDate }}" aria-haspopup="dialog" aria-controls="student-excuse-report"><i class="bi bi-list-ul me-1" aria-hidden="true"></i>{{ __('View requests') }} <span class="student-excuse-badge" data-excuse-count title="{{ __('Unread excuse requests submitted today') }}" hidden></span></button>
                @endif
            </article>
        @endforeach
    </div>
    <div class="student-overview-grid">
    <section class="attendance-overview" aria-labelledby="attendance-overview-title">
        <div class="attendance-overview-heading">
            <h2 id="attendance-overview-title"><i class="bi bi-people-fill" aria-hidden="true"></i> {{ __('Attendance overview') }}</h2>
            <time class="directory-date" datetime="{{ $attendanceDate }}"><i class="bi bi-calendar3" aria-hidden="true"></i> {{ $displayDate }}</time>
        </div>
        <div class="student-overview-body">
        <div class="student-attendance-ring">
            <svg viewBox="0 0 120 120" aria-hidden="true">
                <circle class="student-ring-track" cx="60" cy="60" r="53" />
                @php($offset = 0)
                @foreach($statuses as $key => [$label, $icon])
                    @php($length = $attendanceCounts['total'] ? 100 * $attendanceCounts[$key] / $attendanceCounts['total'] : 0)
                    <circle class="attendance-tone-{{ $key }}" data-attendance-segment="{{ $key }}" cx="60" cy="60" r="53" pathLength="100" stroke-dasharray="{{ $length }} 100" stroke-dashoffset="{{ -$offset }}" />
                    @php($offset += $length)
                @endforeach
            </svg>
            @php($recorded = $attendanceCounts['total'] - $attendanceCounts['unrecorded'])
            <div><i class="bi bi-people-fill" aria-hidden="true"></i><strong data-attendance-rate>{{ $recorded ? round(100 * ($attendanceCounts['present'] + $attendanceCounts['late']) / $recorded, 1).'%' : '—' }}</strong><small>{{ __('Attendance rate') }}</small></div>
        </div>
        <ul class="attendance-distribution-legend">
            @foreach($statuses as $key => [$label, $icon])
                <li class="attendance-tone-{{ $key }}" @if($key === 'unrecorded' && !$attendanceCounts[$key]) hidden @endif>
                    <i class="bi bi-circle-fill" aria-hidden="true"></i>
                    <span>{{ __($label) }}</span>
                    <strong data-attendance-count="{{ $key }}" data-count="{{ $attendanceCounts[$key] }}">{{ number_format($attendanceCounts[$key]) }}</strong>
                    <small data-attendance-percent="{{ $key }}">{{ $formatPercent($key) }}%</small>
                </li>
            @endforeach
        </ul>
        </div>
    </section>
    @include('school.students.weekly-attendance')
    <section class="attendance-overview student-quick-panel">
        <div class="attendance-overview-heading"><h2><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> {{ __('Quick Actions') }}</h2></div>
        <div class="student-quick-links">
            @can('create', \App\Models\Student::class)
                <a href="{{ route('students.create') }}"><i class="bi bi-person-plus-fill" aria-hidden="true"></i>{{ __('Add Student') }}</a>
            @endcan
            <a href="{{ route('students.index', ['download' => 'csv'] + request()->except('page')) }}"><i class="bi bi-download" aria-hidden="true"></i>{{ __('Export CSV') }}</a>
            <a href="#student-directory"><i class="bi bi-list-ul" aria-hidden="true"></i>{{ __('Student List') }}</a>
        </div>
    </section>
    </div>
</div>
<section class="panel directory-panel student-directory" id="student-directory">
    <div class="panel-header directory-heading">
        <div class="directory-heading-copy">
            <h2><i class="bi bi-person-vcard" aria-hidden="true"></i> {{ __('Student directory') }} <span class="directory-count">{{ number_format($students->total()) }}</span></h2>
        </div>
    </div>
    <form method="get" class="student-filters directory-workspace" data-live-filters>
        <div class="directory-search-row">
            <div class="directory-search-field"><label for="search" class="form-label">{{ __('Search students') }}</label><div class="directory-search-input"><i class="bi bi-search" aria-hidden="true"></i><input id="search" type="search" name="search" class="form-control" placeholder="{{ __('Search name, student ID, or email...') }}" value="{{ request('search') }}"></div></div>
            <div><label for="grade" class="form-label">{{ __('Grade') }}</label><select class="form-select" id="grade" name="grade"><option value="">{{ __('All grades') }}</option>@foreach($classes->pluck('classGroup.grade')->filter()->unique()->sort() as $grade)<option value="{{ $grade }}" @selected((string) request('grade') === (string) $grade)>{{ $grade }}</option>@endforeach</select></div>
            <div><label for="class" class="form-label">{{ __('Class') }}</label><select id="class" name="class" class="form-select"><option value="">{{ __('All classes') }}</option>@foreach($classes as $class)<option value="{{ $class->id }}" data-program="{{ $class->program }}" data-session="{{ $class->session }}" @selected(request('class') == $class->id)>{{ $class->label }}</option>@endforeach</select></div>
            <div><label for="status" class="form-label">{{ __('Enrollment') }}</label><select id="status" name="status" class="form-select"><option value="">{{ __('All statuses') }}</option>@foreach(['active', 'inactive', 'graduated'] as $status)<option @selected(request('status') === $status) value="{{ $status }}">{{ __(ucfirst($status)) }}</option>@endforeach</select></div>
            <div><label for="attendance-date" class="form-label">{{ __('Attendance date') }}</label><input id="attendance-date" name="attendance_date" type="date" class="form-control" value="{{ $attendanceDate }}" max="{{ today()->toDateString() }}" required></div>
            <div class="directory-filter-actions"><a class="btn btn-outline-secondary" href="{{ route('students.index') }}" aria-label="{{ __('Reset filters') }}"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> {{ __('Reset') }}</a></div>
        </div>
        <fieldset class="attendance-filter-group">
            <legend>{{ __('Attendance status') }}</legend>
            <div class="attendance-filter-tabs">
            @foreach(['' => ['All students', 'people'], ...$statuses] as $value => [$label, $icon])
                <label class="attendance-tab attendance-{{ $value ?: 'all' }}"><input type="radio" name="attendance_status" value="{{ $value }}" @checked(request('attendance_status', '') === $value)><span><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ __($label) }}<b class="attendance-tab-count" data-attendance-count="{{ $value ?: 'total' }}" data-count="{{ $attendanceCounts[$value ?: 'total'] }}">{{ number_format($attendanceCounts[$value ?: 'total']) }}</b></span></label>
            @endforeach
            </div>
        </fieldset>
        <details class="directory-advanced" @if($advancedActive) open @endif>
            <summary><i class="bi bi-sliders" aria-hidden="true"></i> {{ __('Advanced filters') }} <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
            <div class="directory-refine-grid">
            <div><label class="form-label" for="program">{{ __('Program') }}</label><select class="form-select" id="program" name="program"><option value="">{{ __('All programs') }}</option>@foreach(\App\Models\Program::names() as $value)<option value="{{ $value }}" @selected(request('program') === $value)>{{ __($value) }}</option>@endforeach</select></div>
            <div><label class="form-label" for="session">{{ __('Session') }}</label><select class="form-select" id="session" name="session"><option value="">{{ __('All sessions') }}</option>@foreach(['Morning', 'Afternoon', 'Evening'] as $value)<option value="{{ $value }}" @selected(request('session') === $value)>{{ __($value) }}</option>@endforeach</select></div>
            <div><label class="form-label" for="sort">{{ __('Sort by') }}</label><select class="form-select" id="sort" name="sort">@foreach(['name' => 'Name', 'student_number' => 'Student ID', 'gender' => 'Gender', 'date_of_birth' => 'Date of birth', 'school_class_id' => 'Class', 'email' => 'Email', 'phone' => 'Phone', 'status' => 'Status'] as $key => $label)<option value="{{ $key }}" @selected(request('sort', 'name') === $key)>{{ __($label) }}</option>@endforeach</select></div>
            <div><label class="form-label" for="direction">{{ __('Order') }}</label><select class="form-select" id="direction" name="direction"><option value="asc" @selected(request('direction', 'asc') === 'asc')>{{ __('Ascending') }}</option><option value="desc" @selected(request('direction') === 'desc')>{{ __('Descending') }}</option></select></div>
            </div>
        </details>
    </form>
<div class="table-responsive"><table class="table table-hover directory-roster"><thead><tr><th>#</th><th>{{ __('Student') }}</th><th>{{ __('Student ID') }}</th><th>{{ __('Grade / Class') }}</th><th>{{ __('Gender') }}</th><th>{{ __('Contact') }}</th><th>{{ __('Guardian') }}</th><th>{{ __('Enrollment') }}</th><th>{{ __('Attendance') }}<small class="d-block">{{ $attendanceDate }}</small></th><th>{{ __('Actions') }}</th></tr></thead><tbody>@forelse($students as $student)
@php($attendanceStatus = $student->attendances->first()?->status)
<tr data-attendance-row="{{ $attendanceStatus ?: 'unrecorded' }}">
<td>{{ $students->firstItem() + $loop->index }}</td><td><div class="directory-student">@include('school.partials.avatar', ['person' => $student, 'size' => 40])<div><a href="{{ route('students.show', $student) }}"><strong>{{ $student->name }}</strong></a><small class="student-class-label">{{ $student->age }} {{ __('years') }}</small></div></div></td>
<td>{{ $student->student_number }}</td><td>@foreach($student->studyClasses as $studyClass)<a class="directory-class-link" href="{{ route('classes.timetable', $studyClass) }}" title="{{ __('View weekly schedule') }}">{{ $studyClass->label }}</a>@endforeach</td>
<td>{{ __(ucfirst($student->gender)) }}</td><td class="directory-contact"><span>{{ $student->email }}</span><small><i class="bi bi-telephone" aria-hidden="true"></i> {{ $student->phone }}</small></td>
<td class="directory-contact"><span>{{ $student->guardian_name }}</span><small>{{ $student->guardian_phone }}</small></td>
<td><span class="status-badge {{ $student->status }}">{{ __(ucfirst($student->status)) }}</span></td><td>
    @can('update', $student)
    <form method="post" action="{{ route('students.attendance.store', $student) }}" data-student-attendance data-attendance-state="{{ $attendanceStatus ?: 'unrecorded' }}" data-saved-state="{{ $attendanceStatus ?: 'unrecorded' }}">
        @csrf
        <input type="hidden" name="date" value="{{ $attendanceDate }}">
        <label class="visually-hidden" for="attendance-{{ $student->id }}">{{ __('Attendance for') }} {{ $student->name }}</label>
        <select id="attendance-{{ $student->id }}" name="status" class="form-select form-select-sm" required>
            <option value="" @selected(! $attendanceStatus) disabled>{{ __('Not Marked') }}</option>
            @foreach(['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $value => $label)
            <option value="{{ $value }}" @selected($attendanceStatus === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
        <span class="attendance-status-pill" data-attendance-badge data-tone="{{ $attendanceStatus ?: 'unrecorded' }}"><i class="bi bi-circle-fill" aria-hidden="true"></i><span data-attendance-label>{{ $attendanceStatus ? __(ucfirst($attendanceStatus)) : __('Not Marked') }}</span></span>
        <button class="btn btn-sm btn-outline-primary mt-1 attendance-save" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> {{ __('Save') }}</button>
        <span class="small d-block mt-1" data-attendance-feedback role="status" aria-live="polite"></span>
    </form>
    @else
    <span class="attendance-status-pill" data-tone="{{ $attendanceStatus ?: 'unrecorded' }}"><i class="bi bi-circle-fill" aria-hidden="true"></i>{{ $attendanceStatus ? __(ucfirst($attendanceStatus)) : __('Not Marked') }}</span>
    @endcan
</td><td><div class="d-flex align-items-center gap-2"><a class="btn btn-sm btn-light" href="{{ route('students.show', $student) }}" title="{{ __('View student') }}" aria-label="View {{ $student->name }}"><i class="bi bi-eye"></i></a>@can('update', $student)<a class="btn btn-sm btn-light" href="{{ route('students.edit', $student) }}" title="{{ __('Edit student') }}" aria-label="Edit {{ $student->name }}"><i class="bi bi-pencil"></i></a><form method="post" action="{{ route('students.destroy', $student) }}" data-confirm="Delete this student? Students with attendance, grades or fees are protected.">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" title="{{ __('Delete student') }}" aria-label="Delete {{ $student->name }}"><i class="bi bi-trash"></i></button></form>@endcan</div></td></tr>@empty<tr><td colspan="10"><div class="empty-state"><i class="bi bi-people"></i><strong>{{ __('No students found') }}</strong><p class="mt-2 mb-0">{{ __('Try a different filter, or contact your school office to link your records.') }}</p></div></td></tr>@endforelse</tbody></table></div><div class="p-3">{{ $students->links() }}</div></section>
@if(auth()->user()->role === 'administrator')
    @include('school.students.excuse-report-dialog')
@endif
@endsection
@push('scripts')
<script src="{{ asset('js/directory-search.js') }}?v={{ filemtime(public_path('js/directory-search.js')) }}"></script>
<script src="{{ asset('js/student-filters.js') }}"></script>
<script src="{{ asset('js/student-attendance.js') }}?v={{ filemtime(public_path('js/student-attendance.js')) }}" defer></script>
@endpush
