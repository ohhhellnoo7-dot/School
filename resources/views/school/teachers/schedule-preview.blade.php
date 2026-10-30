@extends('school.layout')
@section('title', 'Teacher Schedule Preview')
@section('content')
<div class="page-heading"><div><h1>Teacher Schedule Preview</h1><p>{{ $teacher->name }} · Review changes before saving.</p></div><a class="btn btn-outline-secondary" href="{{ route('teacher-management.show', $teacher) }}">Choose Another Time</a></div>
<h2 class="h5">Existing schedule</h2>
@include('school.teachers.weekly-grid')
<section class="panel p-4 my-4"><h2 class="h5">Proposed changes</h2>
<p>Existing teaching lessons stay in place. Approved curriculum supplies the demand for new lessons; additional available hours remain unscheduled.</p>
<div class="row g-3"><div class="col-md-6"><h3 class="h6">Designated free periods</h3>
@foreach($plan['breaks'] as $b)<article class="teacher-break-card"><strong>FREE PERIOD · 1 HOUR</strong><span>{{ $b['weekday'] }} {{ $b['start_time'] }}–{{ $b['end_time'] }}</span></article>@endforeach
</div><div class="col-md-6"><h3 class="h6">New teaching lessons ({{ count($plan['lessons']) }})</h3>
@forelse($plan['lessons'] as $row)
@php($subject = \App\Models\Subject::with('schoolClass')->find($row['subject_id']))
<article class="teacher-lesson-card"><strong>{{ $subject->name }}</strong><span>{{ $subject->schoolClass->label }} · Room {{ $row['room'] }}</span><small>{{ $row['day'] }} {{ $row['starts_at'] }}–{{ $row['ends_at'] }}</small></article>
@empty<p>No additional approved teaching demand. No lessons will be invented.</p>@endforelse
</div></div>
<form method="post" action="{{ route('teacher-management.schedules.confirm') }}" class="mt-4">@csrf
<input type="hidden" name="token" value="{{ $token }}">
@if($breaks->isNotEmpty())<label class="d-block mb-3"><input type="checkbox" name="replace" value="1" required> Replace the existing designated free periods with this proposal.</label>@endif
<label class="d-block mb-3"><input type="checkbox" name="confirm" value="1" required> I confirm this schedule and the displayed changes.</label>
<button class="btn btn-primary">Confirm and Save Schedule</button></form></section>
@endsection
