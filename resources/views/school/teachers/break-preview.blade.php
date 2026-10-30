@extends('school.layout')
@section('title', 'Teacher Break Preview')
@section('content')
<div class="page-heading"><div><h1>Preview Weekly Teacher Breaks</h1><p>No breaks or teaching lessons have been changed.</p></div><a class="btn btn-outline-secondary" href="{{ route('modules.index', 'teachers') }}">Back to Teachers</a></div>
@foreach($missing as $failure)<section class="panel p-3 mb-3"><strong>{{ $failure['name'] }}</strong><p>{{ $failure['message'] }}</p><a class="btn btn-outline-secondary" href="{{ route('teacher-management.show', $failure['id']) }}#assign">Fix Schedule</a></section>@endforeach
@foreach($plans as $id => $plan)<section class="panel p-3 mb-3"><h2 class="h5">{{ $plan['name'] }}</h2>@if($plan['replaces'])<p>Existing breaks will be replaced only after confirmation.</p>@endif<ul>@foreach($plan['breaks'] as $b)<li>{{ $b['weekday'] }} · {{ $b['start_time'] }}–{{ $b['end_time'] }} · 1 hour</li>@endforeach</ul></section>@endforeach
@if($plans)<form method="post" action="{{ route('teacher-management.breaks.confirm') }}" class="panel p-3">@csrf<input type="hidden" name="token" value="{{ $token }}"><label class="d-block mb-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required> I confirm these weekly break schedules.</label>@if(collect($plans)->contains('replaces', true))<label class="d-block mb-3"><input class="form-check-input" type="checkbox" name="replace" value="1" required> Replace the existing break schedules shown above.</label>@endif<button class="btn btn-primary">Confirm and Save Breaks</button></form>@endif
@endsection
