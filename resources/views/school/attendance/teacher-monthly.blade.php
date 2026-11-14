@extends('school.layout')
@section('title', 'Monthly teacher attendance')
@section('content')
<link href="{{ asset('css/teacher-monthly.css') }}?v={{ filemtime(public_path('css/teacher-monthly.css')) }}" rel="stylesheet">
<div class="teacher-attendance-page">
<header class="teacher-attendance-hero"><span class="teacher-attendance-hero-icon"><i class="bi bi-people-fill" aria-hidden="true"></i></span><div><a href="{{ route('teacher-attendance.index') }}">Teacher attendance</a><h1>Teachers &middot; Attendance Overview</h1><p>Manage and track teacher attendance for each scheduled session.</p></div></header>
<div class="teacher-attendance-summary" aria-label="Teacher totals for {{ $summaryDate->format('d M Y') }}" title="Teacher totals for {{ $summaryDate->format('d M Y') }}">
@foreach(['present' => ['check-lg', 'Present'], 'late' => ['clock', 'Late'], 'absent' => ['x-lg', 'Absent'], 'excused' => ['file-earmark-text', 'Excused']] as $summaryStatus => [$summaryIcon, $summaryLabel])
@php
$summaryCount = $summaryMarks->where('status', $summaryStatus)->count();
@endphp
<div class="teacher-summary-card teacher-summary-{{ $summaryStatus }}"><span class="teacher-summary-icon"><i class="bi bi-{{ $summaryIcon }}" aria-hidden="true"></i></span><div><h2>{{ $summaryLabel }}</h2><strong>{{ $summaryCount }}</strong></div></div>
@endforeach
</div>
<section class="panel p-4 mb-4"><form method="get" class="row g-3 align-items-end" id="teacher-monthly-filters" data-status="{{ $filters['status'] ?? '' }}">
<div class="col-md-4"><label class="form-label" for="grid-month">Month</label><input id="grid-month" class="form-control" type="month" name="month" value="{{ $month->format('Y-m') }}" required></div>
<div class="col-md-4"><label class="form-label" for="grid-program">Program</label><select id="grid-program" name="program" class="form-select"><option value="">All programs</option>@foreach(\App\Models\Program::names() as $program)<option @selected(($filters['program'] ?? '') === $program)>{{ $program }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label" for="grid-session">Session</label><select id="grid-session" name="session" class="form-select"><option value="">All sessions</option>@foreach(\App\Support\AttendanceSession::OPTIONS as $session)<option @selected(($filters['session'] ?? '') === $session)>{{ $session }}</option>@endforeach</select></div>
<div class="col-12"><label class="form-label d-block">Attendance status</label><div class="d-flex flex-wrap gap-2" role="group" aria-label="Filter attendance by status">
@foreach(['' => 'All statuses', 'present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $value => $label)
<button type="submit" name="status" value="{{ $value }}" @class(['btn', 'btn-primary' => ($filters['status'] ?? '') === $value, 'btn-outline-primary' => ($filters['status'] ?? '') !== $value]) aria-pressed="{{ ($filters['status'] ?? '') === $value ? 'true' : 'false' }}">{{ $label }}</button>
@endforeach
</div><small class="text-secondary">@if(!empty($filters['date']))Show teachers with this status on {{ \Carbon\Carbon::parse($filters['date'])->format('d M Y') }} in the selected session.@else Show teachers with this status on at least one day in the selected month and session.@endif</small></div>
<div class="col-md-4"><label class="form-label" for="grid-date">Filter status by day (optional)</label><input id="grid-date" class="form-control" type="date" name="date" value="{{ $filters['date'] ?? '' }}"><small class="text-secondary">Leave empty to check the whole month.</small></div>
<div class="col-md-8 d-flex gap-2"><a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['date' => today()->toDateString(), 'month' => today()->format('Y-m')]) }}">Today</a><a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['date' => null, 'month' => $month->format('Y-m')]) }}">Whole month</a></div>
</form></section>
@forelse($groups as $program => $rows)
<section class="panel mb-4 teacher-attendance-panel"><div class="panel-header"><h2><i class="bi bi-people-fill" aria-hidden="true"></i> {{ $program }} <small class="text-secondary">&middot; {{ $rows->pluck('teacher.id')->unique()->count() }} teachers</small></h2><button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> Print</button></div>
<div class="table-responsive"><table class="teacher-monthly-grid"><thead><tr><th>No.</th><th>Teacher's name</th><th>Sex</th><th>Assigned sessions</th><th>Session</th>@for($day = 1; $day <= $month->daysInMonth; $day++)<th @class(['is-weekend' => $month->copy()->day($day)->isWeekend()])>{{ $day }}</th>@endfor</tr></thead><tbody>
@php
$teacherIds = $rows->pluck('teacher.id')->unique()->values();
@endphp
@foreach($rows as $row)
@php
$teacherBand = $teacherIds->search($row['teacher']->id) % 2;
@endphp
<tr class="teacher-attendance-band-{{ $teacherBand }}"><td>{{ $loop->iteration }}</td><th scope="row"><div class="teacher-grid-person">@if($row['teacher']->profile_photo_url)<img src="{{ $row['teacher']->profile_photo_url }}" alt="" loading="lazy">@else<span class="teacher-grid-avatar" aria-hidden="true">{{ mb_substr($row['teacher']->name, 0, 1) }}</span>@endif<span>{{ $row['teacher']->name }}</span></div></th><td><span class="teacher-gender teacher-gender-{{ strtolower($row['teacher']->gender ?? 'unspecified') }}" title="{{ ucfirst($row['teacher']->gender ?: 'Not specified') }}"><i class="bi bi-{{ match (strtolower($row['teacher']->gender ?? '')) { 'female' => 'gender-female', 'male' => 'gender-male', default => 'person' } }}" aria-hidden="true"></i><span class="visually-hidden">{{ ucfirst($row['teacher']->gender ?: 'Not specified') }}</span></span></td><td>{{ $row['assigned']->count() }}<small class="d-block">{{ $row['assigned']->implode(', ') ?: 'No sessions assigned' }}</small></td><td>{{ $row['session'] ?: 'Unassigned' }}</td>
@for($day = 1; $day <= $month->daysInMonth; $day++)
@php
$record = $row['marks']->get($day);
$status = $record?->status;
$scan = ($row['scan_marks'] ?? collect())->get($day);
$arrival = $scan?->scanned_at;
$departure = $scan?->checked_out_at;
$dayScans = isset($row['daily_scans']) ? $row['daily_scans']->get($day, collect()) : collect([$scan])->filter();
$arrivals = $dayScans->filter(fn ($item) => $item->scanned_at);
$arrival = $arrivals->min('scanned_at');
$missingCheckout = $arrivals->contains(fn ($item) => ! $item->checked_out_at && now()->gt($item->scheduled_end ?? $month->copy()->day($day)->endOfDay()));
$allCheckedOut = $arrivals->isNotEmpty() && $arrivals->every(fn ($item) => (bool) $item->checked_out_at);
$departure = $allCheckedOut ? $arrivals->max('checked_out_at') : null;
$outState = $missingCheckout ? 'missing' : ($allCheckedOut ? 'complete' : 'pending');
$outLabel = ['missing' => 'Missing scan-out', 'complete' => 'Scanned out', 'pending' => 'No scan-out yet'][$outState];
$lateMinutes = $scan?->status === 'late' && $arrival && $scan->scheduled_start
    ? max(0, (int) floor($scan->scheduled_start->diffInMinutes($arrival, false))) : null;
$timingDescription = $arrival ? ' | In '.$arrival->format('H:i').($lateMinutes !== null ? ' | '.$lateMinutes.' min late' : '') : '';
$symbol = ['present' => '✓', 'late' => 'L', 'absent' => 'X', 'excused' => 'E'][$status] ?? '—';
$checkoutDescription = $departure ? ' | Out '.$departure->format('H:i') : ($arrival ? ' | Out: Not scanned' : '');
$adminMark = $record instanceof \App\Models\TeacherAttendance && $record->recorded_by !== $row['teacher']->id;
$description = $month->copy()->day($day)->format('d M Y').' · '.($row['session'] ?: 'Unassigned').' · '.($status ?: 'Not recorded').($record?->scanned_at ? ' · Scanned '.$record->scanned_at->format('H:i') : '');
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
<span class="attendance-out-symbol" aria-hidden="true">{{ $missingCheckout ? 'X' : ($allCheckedOut ? "\u{2713}" : "\u{2014}") }}</span>
@if($departure)<div class="attendance-detail">Out {{ $departure->format('H:i') }}</div>@endif
</div>
</td>
@endfor</tr>
@endforeach
</tbody></table></div></section>
@empty<section class="panel p-4">No teachers match these filters.</section>@endforelse
</div>
<script src="{{ asset('js/teacher-monthly-filters.js') }}?v={{ filemtime(public_path('js/teacher-monthly-filters.js')) }}" defer></script>
@endsection
