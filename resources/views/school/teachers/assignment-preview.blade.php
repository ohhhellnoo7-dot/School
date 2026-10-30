@extends('school.layout')
@section('title', 'Teacher Assignment Preview')
@section('content')
<div class="page-heading"><div><h1>Preview Teacher Assignments</h1><p>Only vacant assignments are proposed. Existing assignments are preserved.</p></div><a class="btn btn-outline-secondary" href="{{ route('modules.index', 'teachers') }}">Back to Teachers</a></div>
<section class="panel p-4"><h2 class="h5">Proposed assignments</h2><ul>@forelse($assignments as $a)<li>{{ $a['description'] }}</li>@empty<li>No valid assignments could be proposed.</li>@endforelse</ul>
@if($assignments)<form method="post" action="{{ route('teacher-management.assignments.confirm') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><label class="d-block mb-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required> Confirm these assignments.</label><button class="btn btn-primary">Confirm and Save Assignments</button></form>@endif</section>
@if($missing)<section class="panel p-4 mt-4"><h2 class="h5">Requirements to resolve</h2><ul>@foreach($missing as $message)<li>{{ $message }}</li>@endforeach</ul></section>@endif
@endsection
