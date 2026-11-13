@extends('school.layout')
@section('title', 'Monthly teacher attendance')
@section('content')
<link href="{{ asset('css/teacher-monthly.css') }}?v={{ filemtime(public_path('css/teacher-monthly.css')) }}" rel="stylesheet">
<div class="page-heading"><div><a href="{{ route('teacher-attendance.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Teacher attendance</a><h1>Monthly teacher attendance</h1><p>{{ $month->format('F Y') }} &middot; Attendance by program and teaching session</p></div><button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i> Print</button></div>
<section class="panel p-4 mb-4"><form method="get" class="row g-3 align-items-end">
<div class="col-md-3"><label class="form-label" for="grid-month">Month</label><input id="grid-month" class="form-control" type="month" name="month" value="{{ $month->format('Y-m') }}" required></div>
<div class="col-md-3"><label class="form-label" for="grid-program">Program</label><select id="grid-program" name="program" class="form-select"><option value="">All programs</option>@foreach(\App\Models\Program::names() as $program)<option @selected(($filters['program'] ?? '') === $program)>{{ $program }}</option>@endforeach</select></div>
<div class="col-md-3"><label class="form-label" for="grid-session">Session</label><select id="grid-session" name="session" class="form-select"><option value="">All sessions</option>@foreach(\App\Support\AttendanceSession::OPTIONS as $session)<option @selected(($filters['session'] ?? '') === $session)>{{ $session }}</option>@endforeach</select></div><div class="col-md-3"><button class="btn btn-primary" name="status" value="{{ $filters['status'] ?? '' }}">View attendance</button></div>
<div class="col-12"><label class="form-label d-block">Attendance status</label><div class="d-flex flex-wrap gap-2" role="group" aria-label="Filter attendance by status">
@foreach(['' => 'All statuses', 'present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $value => $label)
<button type="submit" name="status" value="{{ $value }}" @class(['btn', 'btn-primary' => ($filters['status'] ?? '') === $value, 'btn-outline-primary' => ($filters['status'] ?? '') !== $value]) aria-pressed="{{ ($filters['status'] ?? '') === $value ? 'true' : 'false' }}">{{ $label }}</button>
@endforeach
</div><small class="text-secondary">@if(!empty($filters['date']))Show teachers with this status on {{ \Carbon\Carbon::parse($filters['date'])->format('d M Y') }} in the selected session.@else Show teachers with this status on at least one day in the selected month and session.@endif</small></div>
<div class="col-md-4"><label class="form-label" for="grid-date">Filter status by day (optional)</label><input id="grid-date" class="form-control" type="date" name="date" value="{{ $filters['date'] ?? '' }}"><small class="text-secondary">Leave empty to check the whole month.</small></div>
<div class="col-md-8 d-flex gap-2"><a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['date' => today()->toDateString(), 'month' => today()->format('Y-m')]) }}">Today</a><a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['date' => null, 'month' => $month->format('Y-m')]) }}">Whole month</a></div>
</form></section>
<p class="small text-secondary">✓ Present &nbsp; L Late &nbsp; X Absent &nbsp; E Excused &nbsp; — Not recorded. Attendance follows the overview. Each scheduled session requires its own scan. In = start work; Out = finish work. Times use the 24-hour clock. Out ? = no finish scan recorded. Hover over a cell for details.</p>
@forelse($groups as $program => $rows)
<section class="panel mb-4"><div class="panel-header"><h2>{{ $program }} <small class="text-secondary">&middot; {{ $rows->pluck('teacher.id')->unique()->count() }} teachers</small></h2></div>
<div class="table-responsive"><table class="teacher-monthly-grid"><thead><tr><th>No.</th><th>Teacher's name</th><th>Sex</th><th>Assigned sessions</th><th>Session</th>@for($day = 1; $day <= $month->daysInMonth; $day++)<th @class(['is-weekend' => $month->copy()->day($day)->isWeekend()])>{{ $day }}</th>@endfor</tr></thead><tbody>
@foreach($rows as $row)
<tr><td>{{ $loop->iteration }}</td><th scope="row">{{ $row['teacher']->name }}</th><td>{{ ucfirst($row['teacher']->gender ?: '—') }}</td><td>{{ $row['assigned']->count() }}<small class="d-block">{{ $row['assigned']->implode(', ') ?: 'No sessions assigned' }}</small></td><td>{{ $row['session'] ?: 'Unassigned' }}</td>
@for($day = 1; $day <= $month->daysInMonth; $day++)
@php
$record = $row['marks']->get($day);
$status = $record?->status;
$scan = ($row['scan_marks'] ?? collect())->get($day);
$arrival = $scan?->scanned_at;
$departure = $scan?->checked_out_at;
$lateMinutes = $scan?->status === 'late' && $arrival && $scan->scheduled_start
    ? max(0, (int) floor($scan->scheduled_start->diffInMinutes($arrival, false))) : null;
$timingDescription = $arrival ? ' | In '.$arrival->format('H:i').($lateMinutes !== null ? ' | '.$lateMinutes.' min late' : '') : '';
$symbol = ['present' => '✓', 'late' => 'L', 'absent' => 'X', 'excused' => 'E'][$status] ?? '—';
$checkoutDescription = $departure ? ' | Out '.$departure->format('H:i') : ($arrival ? ' | Out: Not scanned' : '');
$adminMark = $record instanceof \App\Models\TeacherAttendance && $record->recorded_by !== $row['teacher']->id;
$description = $month->copy()->day($day)->format('d M Y').' · '.($row['session'] ?: 'Unassigned').' · '.($status ?: 'Not recorded').($record?->scanned_at ? ' · Scanned '.$record->scanned_at->format('H:i') : '');
@endphp
<td class="attendance-mark mark-{{ $status ?: 'empty' }}" title="{{ $description }}{{ $timingDescription }}{{ $checkoutDescription }}{{ $adminMark ? ' · Administrator daily mark' : '' }}" aria-label="{{ $description }}{{ $timingDescription }}{{ $checkoutDescription }}{{ $adminMark ? ' · Administrator daily mark' : '' }}">
@if($status)
<div class="attendance-status">{{ ucfirst($status) }}</div>
@if($arrival || $departure)
<div class="attendance-detail attendance-times">@if($arrival)<span>In {{ $arrival->format('H:i') }}</span>@endif @if($departure)<span>Out {{ $departure->format('H:i') }}</span>@elseif($arrival)<span>Out &mdash;</span>@endif</div>
@endif
@if($lateMinutes !== null)
<div class="attendance-detail">{{ $lateMinutes }} min late</div>
@endif
@else
<span aria-hidden="true">&mdash;</span>
@endif
</td>
@endfor</tr>
@endforeach
</tbody></table></div></section>
@empty<section class="panel p-4">No teachers match these filters.</section>@endforelse
@endsection
