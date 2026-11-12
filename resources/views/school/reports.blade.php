@extends('school.layout')
@section('title', 'Reports')
@section('content')
<link href="{{ asset('css/reports-redesign.css') }}?v={{ filemtime(public_path('css/reports-redesign.css')) }}" rel="stylesheet">
<div class="reports-redesign">
<x-scholar-hero tag="section" class="reports-hero" style="--reports-image:url('{{ asset('images/reports-hero.png') }}')">
<div class="reports-hero-copy"><div class="eyebrow">TURN INFORMATION INTO INSIGHT</div><h1>School Reports</h1><p>A clear view of your school&#8217;s operations.</p></div>
<div class="reports-hero-actions"><form method="get"><input type="hidden" name="date" value="{{ $date }}"><label class="visually-hidden" for="report-year">Academic year</label><select id="report-year" class="form-select" name="academic_year" onchange="this.form.submit()"><option value="">All academic years</option>
@foreach($reportYears as $year)
<option value="{{ $year }}" @selected(request('academic_year') === $year)>Academic Year {{ $year }}</option>
@endforeach
</select></form><button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print Report</button></div>
<nav class="reports-tabs" aria-label="Report sections"><a class="active" href="#report-finance"><i class="bi bi-stack"></i> School Fees &amp; Payments</a><a href="#report-attendance"><i class="bi bi-calendar-check"></i> Attendance</a><a href="#report-students"><i class="bi bi-people"></i> Students</a><a href="{{ route('teacher-attendance.index') }}"><i class="bi bi-person-check"></i> Teachers</a><a href="#report-classes"><i class="bi bi-mortarboard"></i> Academic</a></nav>
</x-scholar-hero>
<div id="report-finance">
@include('school.partials.reports-finance')

</div>
<section class="panel mb-4" id="report-students"><div class="panel-header"><div><h2><i class="bi bi-people"></i> Students by program</h2><p>Current active students. Each student is counted once per program, across all sessions.</p></div></div><div class="panel-body"><div class="row align-items-center g-4"><div class="col-md-6"><div style="position:relative;height:300px"><canvas id="programStudentsChart" data-labels='@json($studentsByProgram->keys()->map(fn ($program) => $program === 'Japan' ? 'Japanese' : $program))' data-values='@json($studentsByProgram->values())' role="img" aria-label="Active students by academic program"></canvas></div></div><div class="col-md-6"><ul class="list-group list-group-flush">
@foreach($studentsByProgram as $program => $count)
<li class="list-group-item d-flex justify-content-between"><span>{{ $program === 'Japan' ? 'Japanese' : $program }}</span><strong>{{ $count }} students</strong></li>
@endforeach
</ul><p class="form-text mt-3">Students enrolled in multiple programs appear in each program. This chart represents program enrollments.</p>
@if($studentsByProgram->sum() === 0)
<p>No active program enrollments yet.</p>
@endif
</div></div></div></section>
<section class="panel mb-4" id="report-attendance"><div class="panel-header"><h2>Daily attendance</h2><form method="get" class="d-flex gap-2"><input type="hidden" name="academic_year" value="{{ request('academic_year') }}"><label class="visually-hidden" for="date">Report date</label><input id="date" class="form-control" type="date" name="date" value="{{ $date }}"><button class="btn btn-primary">View</button></form></div><div class="panel-body"><div class="row g-3">@foreach(['present','absent','late','excused'] as $status)<div class="col-6 col-md-3"><span class="status-badge {{ $status }}">{{ __(ucfirst($status)) }}</span><div class="stat-value">{{ $attendance[$status] ?? 0 }}</div></div>@endforeach</div></div></section>
<section class="panel" id="report-classes"><div class="panel-header"><h2>Class enrollment & capacity</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>{{ __('Class') }}</th><th>{{ __('Teacher') }}</th><th>{{ __('Students') }}</th><th>Capacity</th><th>Utilization</th></tr></thead><tbody>@forelse($classes as $class)<tr><td><strong>{{ $class->name }} Â&middot; {{ $class->section }}</strong></td><td>{{ $class->teacher?->name ?? 'Unassigned' }}</td><td>{{ $class->students_count }}</td><td>{{ $class->capacity }}</td><td style="min-width:150px"><div class="progress" role="progressbar" aria-valuenow="{{ $class->students_count }}" aria-valuemin="0" aria-valuemax="{{ $class->capacity }}" style="height:6px"><div class="progress-bar utilization-{{ $class->students_count > $class->capacity ? 'over' : ($class->students_count / max(1, $class->capacity) >= .9 ? 'high' : ($class->students_count / max(1, $class->capacity) >= .6 ? 'moderate' : 'low')) }}" style="width:{{ min(100, $class->students_count / max(1, $class->capacity) * 100) }}%"></div></div><small>{{ round($class->students_count / max(1, $class->capacity) * 100) }}%</small></td></tr>@empty<tr><td colspan="5" class="p-4 text-center">No classes configured yet.</td></tr>@endforelse</tbody></table></div></section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/chart/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/report-charts.js') }}?v={{ filemtime(public_path('js/report-charts.js')) }}" defer></script>
@endpush
