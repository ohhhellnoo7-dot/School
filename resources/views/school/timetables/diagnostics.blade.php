@extends('school.layout')
@section('title', 'Timetable Diagnostics')
@section('content')
<section class="panel p-4"><h1>Khmer timetable diagnostics</h1><p>{{ $report['identities'] }} active identities · {{ $report['cohorts'] }} cohorts · {{ $report['active_lessons'] }} active lessons across all programs · {{ $report['missing_room_ids'] }} missing physical room IDs.</p><p>Existing lessons remain published until an administrator approves a complete valid preview. Other programs remain constraints. A full PostgreSQL backup procedure is documented in docs/khmer-timetable-rebuild.md.</p>
<form method="post" action="{{ route('timetables.generate.preview') }}">@csrf<label>Academic year <input name="academic_year" required pattern="[0-9]{4}-[0-9]{4}" value="{{ now()->year }}-{{ now()->year+1 }}"></label><button class="btn btn-primary">Prepare complete Khmer replacement preview</button></form>
<h2 class="h5 mt-4">Findings requiring review ({{ count($report['issues']) }})</h2><ul>@foreach($report['issues'] as $issue)<li><strong>{{ $issue['type'] }}</strong>: {{ $issue['message'] }}</li>@endforeach</ul></section>
@include('school.timetables.class-validation', ['validation' => $report['class_validation']])
@endsection
