@extends('school.layout')
@section('title', 'Check-in session times')
@section('content')
<div class="page-heading"><div><h1>Work session times</h1><p>Set when teachers start and finish work for each session.</p></div><a class="btn btn-outline-primary" href="{{ route('dashboard') }}">Back to overview</a></div>
<section class="panel p-4">
<p>Times use the school timezone: <strong>{{ config('app.timezone') }}</strong>. A scan after the start time plus the allowance is late. For a 7:15 AM cutoff, enter 07:15 with a 0-minute allowance.</p>
<p class="text-secondary small">Leave a start time empty to use each teacher's first scheduled lesson. These settings apply to new scans in scheduled teaching sessions. Existing scan results are preserved.</p>
<form method="post" action="{{ route('attendance-settings.update') }}">
@csrf @method('put')
<div class="row g-3">
@foreach(\App\Support\AttendanceSession::OPTIONS as $session)
@php($setting = $settings->get($session))
<div class="col-md-4"><fieldset class="border rounded-4 p-3 h-100"><legend class="float-none w-auto px-2 fs-5">{{ $session }}</legend>
<label for="start-{{ $session }}" class="form-label">Work start time</label>
<input type="time" id="start-{{ $session }}" name="sessions[{{ $session }}][starts_at]" class="form-control mb-3" value="{{ old('sessions.'.$session.'.starts_at', $setting?->starts_at ? substr($setting->starts_at, 0, 5) : '') }}">
<label for="end-{{ $session }}" class="form-label">Work finish time</label>
<input type="time" id="end-{{ $session }}" name="sessions[{{ $session }}][ends_at]" class="form-control mb-3" value="{{ old('sessions.'.$session.'.ends_at', $setting?->ends_at ? substr($setting->ends_at, 0, 5) : '') }}">
<p class="small text-secondary">Leave empty to use the last scheduled lesson's end. Finish work scans are allowed at or after this time.</p>
<label for="grace-{{ $session }}" class="form-label">Late allowance (minutes)</label>
<input type="number" id="grace-{{ $session }}" name="sessions[{{ $session }}][grace_minutes]" class="form-control" min="0" max="120" required value="{{ old('sessions.'.$session.'.grace_minutes', $setting?->grace_minutes ?? 10) }}">
</fieldset></div>
@endforeach
</div><button class="btn btn-primary mt-4">Save session times</button>
</form></section>
@endsection
