@extends('school.layout')
@section('title', 'Monthly teacher attendance')
@section('content')
<link href="{{ asset('css/teacher-monthly.css') }}?v={{ filemtime(public_path('css/teacher-monthly.css')) }}" rel="stylesheet">
@php
    $canViewPayrollReports = !$printing && auth()->user()->role === 'administrator';
@endphp
<div class="teacher-attendance-page">
<header class="teacher-attendance-hero">
    <img class="teacher-attendance-campus-art" src="{{ asset('images/teacher-attendance-campus.png') }}" width="980" height="327" alt="" decoding="async">
    <svg class="teacher-attendance-banner-wave" viewBox="0 0 1000 150" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 77C198 42 241-24 384 0c141 24 150 113 338 60L1000 0v150H0Z" fill="currentColor"/>
    </svg>
    <span class="teacher-attendance-hero-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
    <div>
        <h1>{{ __('Teacher Attendance') }}</h1>
        <p>{{ __('Track and manage teacher attendance, leaves, and holiday schedules.') }}</p>
    </div>
</header>
<div class="teacher-attendance-summary" aria-label="Teacher totals for {{ $summaryDate->format('d M Y') }}" title="Teacher totals for {{ $summaryDate->format('d M Y') }}">
    @foreach(['present' => ['check-lg', 'Present', 'people-fill'], 'late' => ['clock', 'Late', 'clock'], 'absent' => ['x-lg', 'Absent', 'calendar-x'], 'excused' => ['file-earmark-text', 'Excused', 'file-earmark-check']] as $status => [$icon, $label, $art])
        <div class="teacher-summary-card teacher-summary-{{ $status }}">
            <span class="teacher-summary-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
            <div><h2>{{ __($label) }}</h2><strong>{{ $summaryMarks->where('status', $status)->count() }}</strong></div>
            <i class="bi bi-{{ $art }} teacher-summary-art" aria-hidden="true"></i>
            <svg class="teacher-summary-wave" viewBox="0 0 360 46" preserveAspectRatio="none" aria-hidden="true"><path d="M0 12C53-5 81 51 153 33S234 7 280 9s47-8 80-1v38H0Z" fill="currentColor"/></svg>
        </div>
    @endforeach
</div>
<section class="panel teacher-monthly-filter-panel" aria-labelledby="teacher-monthly-filter-heading">
    <header class="teacher-monthly-filter-title">
        <i class="bi bi-funnel" aria-hidden="true"></i>
        <div>
            <h2 id="teacher-monthly-filter-heading">{{ __('Filter Attendance') }}</h2>
            <p>{{ __('Find teacher attendance by month, program, session, and status.') }}</p>
        </div>
    </header>
    <form method="get" class="teacher-monthly-filter-form" id="teacher-monthly-filters" data-status="{{ $filters['status'] ?? '' }}">
        <div class="teacher-monthly-filter-main">
            <div class="teacher-monthly-selects">
                <div>
                    <label class="form-label" for="grid-month"><i class="bi bi-calendar3" aria-hidden="true"></i> {{ __('Month') }}</label>
                    <input id="grid-month" class="form-control" type="month" name="month" value="{{ $month->format('Y-m') }}" required>
                </div>
                <div>
                    <label class="form-label" for="grid-program"><i class="bi bi-stack" aria-hidden="true"></i> {{ __('Program') }}</label>
                    <select id="grid-program" name="program" class="form-select">
                        <option value="">{{ __('All programs') }}</option>
                        @foreach(\App\Models\Program::names() as $program)<option @selected(($filters['program'] ?? '') === $program)>{{ $program }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="grid-session"><i class="bi bi-clock" aria-hidden="true"></i> {{ __('Session') }}</label>
                    <select id="grid-session" name="session" class="form-select">
                        <option value="">{{ __('All sessions') }}</option>
                        @foreach(\App\Support\AttendanceSession::OPTIONS as $session)<option @selected(($filters['session'] ?? '') === $session)>{{ $session }}</option>@endforeach
                    </select>
                </div>
            </div>
            <fieldset class="teacher-monthly-status-filter">
                <legend><i class="bi bi-people" aria-hidden="true"></i> {{ __('Attendance Status') }}</legend>
                <div class="d-flex flex-wrap gap-2">
                    @foreach(['' => ['grid', 'All statuses'], 'present' => ['check-lg', 'Present'], 'late' => ['clock', 'Late'], 'absent' => ['x-lg', 'Absent'], 'excused' => ['file-earmark-text', 'Excused']] as $value => [$icon, $label])
                        <button type="submit" name="status" value="{{ $value }}" data-filter-status="{{ $value }}" @class(['btn', 'btn-primary' => ($filters['status'] ?? '') === $value, 'btn-outline-primary' => ($filters['status'] ?? '') !== $value]) aria-pressed="{{ ($filters['status'] ?? '') === $value ? 'true' : 'false' }}">
                            <i class="bi bi-{{ $icon }}" aria-hidden="true"></i> {{ __($label) }}
                        </button>
                    @endforeach
                    @if($canViewPayrollReports)
                        <a class="btn btn-outline-primary teacher-monthly-reports-trigger" href="{{ route('employee-salaries.teacher-reports', array_filter(['month' => $month->format('Y-m'), 'program' => $filters['program'] ?? null, 'session' => $filters['session'] ?? null])) }}">
                            <i class="bi bi-file-earmark-text" aria-hidden="true"></i> {{ __('Teacher reports') }}
                        </a>
                    @endif
                </div>
            </fieldset>
        </div>
        <div class="teacher-monthly-filter-day">
            <div>
                <label class="form-label" for="grid-date"><i class="bi bi-calendar-event" aria-hidden="true"></i> {{ __('Filter by Day (Optional)') }}</label>
                <input id="grid-date" class="form-control" type="date" name="date" value="{{ $filters['date'] ?? '' }}">
            </div>
            <div class="teacher-monthly-date-actions">
                <a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['date' => today()->toDateString(), 'month' => today()->format('Y-m')]) }}"><i class="bi bi-calendar-check" aria-hidden="true"></i> {{ __('Today') }}</a>
                <a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['date' => null, 'month' => $month->format('Y-m')]) }}"><i class="bi bi-calendar-week" aria-hidden="true"></i> {{ __('Whole month') }}</a>
            </div>
        </div>
    </form>
</section>
@if($activeHoliday)
    <section class="panel teacher-monthly-holiday-panel" aria-labelledby="teacher-monthly-holidays">
        <div class="teacher-monthly-holiday-heading">
            <i class="bi bi-calendar-event" aria-hidden="true"></i>
            <div>
                <h2 id="teacher-monthly-holidays">{{ $activeHoliday }}</h2>
                <p>{{ __('Khmer holiday. School is closed. No classes, work, or attendance scans are required.') }}</p>
            </div>
        </div>
        <ul>
            <li><strong>{{ $activeHolidayDays->join(', ') }} {{ $month->translatedFormat('M Y') }}</strong></li>
        </ul>
        <svg class="teacher-monthly-holiday-art" viewBox="0 0 340 100" aria-hidden="true">
            <path fill="currentColor" d="M0 98q16-12 30-12l7-7 15 1 9-12 13 3 9-6 14 3V58h12V41h5v-9l5-7 6 7v10h5v15h12V43h8V30h5V19l8-9 6-9 6 9 8 9v11h5v13h8v14h14V42h5V32l6-7 5 7v9h5v17h11v20h14l10-7 11 7 9-4 13 6 12 2 8 7 14 9Z"/>
            <path fill="currentColor" d="M279 91q9-20 9-45-15-1-23 8 4-14 23-14-12-9-21-7 11-9 23 1-2-15-12-20 14-3 17 17 5-19 17-18-10 8-11 22 12-12 25-7-12 3-22 12 17-4 24 9-11-5-24-4 2 23 12 46ZM41 24q6-4 11 3 6-6 12-4-6 2-12 8-5-5-11-7Z"/>
        </svg>
    </section>
@endif
@forelse($groups as $program => $roster)
@php
    $teacherIds = $roster->pluck('teacher.id')->unique()->values();
@endphp
<section class="panel mb-4 teacher-attendance-panel" id="roster-{{ \Illuminate\Support\Str::slug($program) }}">
    <div class="panel-header">
        <h2><i class="bi bi-people-fill" aria-hidden="true"></i> {{ $program }} <small class="text-secondary">&middot; {{ $teacherIds->count() }} teachers</small></h2>
        @if($printing)
            <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> Print</button>
        @else
            <a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['print' => 1]) }}" target="_blank"><i class="bi bi-printer" aria-hidden="true"></i> Print full report</a>
        @endif
    </div>
<div class="table-responsive" tabindex="0" role="region" aria-label="{{ __('Monthly attendance for :program', ['program' => $program]) }}"><table class="teacher-monthly-grid" style="--attendance-days: {{ $month->daysInMonth }}; --attendance-report-width: {{ $canViewPayrollReports ? '94px' : '0px' }}">
<colgroup>
    <col class="attendance-number"><col class="attendance-name"><col class="attendance-sex">
    <col class="attendance-assigned"><col class="attendance-session">
    <col class="attendance-date" span="{{ $month->daysInMonth }}">
    @if($canViewPayrollReports)<col class="attendance-report">@endif
</colgroup>
<thead><tr><th>No.</th><th>Teacher's name</th><th>Sex</th><th>Assigned sessions</th><th>Session</th>@for($day = 1; $day <= $month->daysInMonth; $day++)<th scope="col" @class(['is-weekend' => $month->copy()->day($day)->isWeekend(), 'is-school-holiday' => $holidayDays->has($day)]) title="{{ $holidayDays->get($day) }}">{{ $day }}@if($holidayDays->has($day))<small class="d-block">{{ __('Holiday') }}<span class="visually-hidden"> {{ $holidayDays->get($day) }}</span></small>@endif</th>@endfor @if($canViewPayrollReports)<th scope="col" class="attendance-report-cell">{{ __('Reports') }}</th>@endif</tr></thead><tbody>
@php
$previousTeacherId = null;
@endphp
@foreach($roster as $row)
@php
$teacherIndex = $teacherIds->search($row['teacher']->id);
$teacherBand = $teacherIndex % 2;
$teacherNumber = $teacherIndex + 1;
$teacherGroupStart = $previousTeacherId !== $row['teacher']->id;
$previousTeacherId = $row['teacher']->id;
@endphp
<tr class="teacher-attendance-band-{{ $teacherBand }}{{ $teacherGroupStart ? ' teacher-group-start' : '' }}"><td>{{ $teacherNumber }}</td><th scope="row"><div class="teacher-grid-person">@include('school.partials.avatar', ['person' => $row['teacher'], 'avatarClass' => 'teacher-grid-avatar', 'teacherIcon' => true, 'size' => 38])<span>{{ $row['teacher']->name }}</span></div></th><td><span class="teacher-gender teacher-gender-{{ strtolower($row['teacher']->gender ?? 'unspecified') }}" title="{{ ucfirst($row['teacher']->gender ?: 'Not specified') }}"><i class="bi bi-{{ match (strtolower($row['teacher']->gender ?? '')) { 'female' => 'gender-female', 'male' => 'gender-male', default => 'person' } }}" aria-hidden="true"></i><span class="visually-hidden">{{ ucfirst($row['teacher']->gender ?: 'Not specified') }}</span></span></td><td>{{ $row['assigned']->count() }}</td><td>{{ $row['session'] ?: 'Unassigned' }}</td>
@for($day = 1; $day <= $month->daysInMonth; $day++)
@if($holiday = $holidayDays->get($day))
    <td class="attendance-mark mark-school-holiday" title="{{ $holiday }}" aria-label="{{ $holiday }}. {{ __('No attendance required') }}">
        <i class="bi bi-calendar-event d-block" aria-hidden="true"></i><span class="attendance-holiday-label">{{ __('No scans') }}</span>
    </td>
    @continue
@endif
@php
$record = $row['marks']->get($day);
$status = $record?->status;
$scan = ($row['scan_marks'] ?? null)?->get($day);
$dateLabel = $month->copy()->day($day)->format('d M Y').' · '.($row['session'] ?: 'Unassigned');
@endphp
@if(!$status && !$scan?->scanned_at)
    <td class="attendance-mark mark-empty" title="{{ $dateLabel }} {{ '·' }} {{ __('Not recorded') }}" aria-label="{{ $dateLabel }} {{ '·' }} {{ __('Not recorded') }}">&mdash;</td>
    @continue
@endif
@php
$arrival = $scan?->scanned_at;
$departure = $arrival ? $scan->checked_out_at : null;
$missingCheckout = $arrival && !$departure && now()->gt($scan->scheduled_end ?? $month->copy()->day($day)->endOfDay());
$manualCompletion = !$arrival && in_array($status, ['present', 'late'], true);
$outComplete = (bool) $departure || $manualCompletion;
$outState = $missingCheckout ? 'missing' : ($outComplete ? 'complete' : 'pending');
$outLabel = ['missing' => 'Missing scan-out', 'complete' => 'Scanned out', 'pending' => 'No scan-out yet'][$outState];
if ($manualCompletion) $outLabel = 'Administrator-marked attendance: departure treated as complete; no scan time recorded';
$lateMinutes = $scan?->status === 'late' && $arrival && $scan->scheduled_start
    ? max(0, (int) floor($scan->scheduled_start->diffInMinutes($arrival, false))) : null;
$timingDescription = $arrival ? ' | In '.$arrival->format('H:i').($lateMinutes !== null ? ' | '.$lateMinutes.' min late' : '') : '';
$symbol = ['present' => '✓', 'late' => 'L', 'absent' => 'X', 'excused' => 'E'][$status] ?? '—';
$checkoutDescription = $departure ? ' | Out '.$departure->format('H:i') : ($arrival ? ' | Out: Not scanned' : '');
$adminMark = $record instanceof \App\Models\TeacherAttendance && $record->recorded_by !== $row['teacher']->id;
$description = $dateLabel.' · '.($status ?: 'Not recorded').($record?->scanned_at ? ' · Scanned '.$record->scanned_at->format('H:i') : '');
if ($status === 'excused' && $record->notes) $description .= ' · '.$record->notes;
@endphp
<td class="attendance-mark mark-{{ $status ?: 'empty' }}" title="{{ $description }}{{ $timingDescription }}{{ $checkoutDescription }}{{ $adminMark ? ' · Administrator daily mark' : '' }}" aria-label="{{ $description }}{{ $timingDescription }}{{ $checkoutDescription }}{{ $adminMark ? ' · Administrator daily mark' : '' }}">
<div class="attendance-scan-half attendance-scan-in">
<span class="attendance-scan-label">Scan in</span>
@if($status)
<div class="attendance-status"><span aria-hidden="true">{{ $symbol }}</span><span class="visually-hidden">{{ ucfirst($status) }}</span></div>
@if($arrival)<div class="attendance-detail">In {{ $arrival->format('H:i') }}</div>@endif
@if($lateMinutes !== null)
<div class="attendance-detail">{{ $lateMinutes }} min late</div>
@endif
@else
<span aria-hidden="true">&mdash;</span>
@endif
</div>
<div class="attendance-scan-half attendance-scan-out scan-out-{{ $outState }}" title="{{ $outLabel }}" aria-label="{{ $outLabel }}{{ $departure ? ' at '.$departure->format('H:i') : '' }}">
<span class="attendance-scan-label">Scan out</span>
<span class="attendance-out-symbol" aria-hidden="true">{{ $missingCheckout ? 'X' : ($outComplete ? "\u{2713}" : "\u{2014}") }}</span>
@if($departure)<div class="attendance-detail">Out {{ $departure->format('H:i') }}</div>@endif
</div>
</td>
@endfor
@if($canViewPayrollReports)
    <td class="attendance-report-cell">
        @if($teacherGroupStart)
            <a class="btn btn-sm btn-outline-primary"
                href="{{ route('employee-salaries.teacher-reports', ['month' => $month->format('Y-m'), 'teacher' => $row['teacher']->id]) }}"
                aria-label="{{ __('Reports for :teacher', ['teacher' => $row['teacher']->name]) }}">{{ __('Reports') }}</a>
        @endif
    </td>
@endif
</tr>
@endforeach
</tbody></table></div>
</section>
@empty<section class="panel p-4">No teachers match these filters.</section>@endforelse
</div>
<script src="{{ asset('js/teacher-monthly-filters.js') }}?v={{ filemtime(public_path('js/teacher-monthly-filters.js')) }}" defer></script>
@endsection
