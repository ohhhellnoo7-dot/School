@extends('school.layout')
@section('title', 'Sample Teacher Preview')
@section('content')
<div class="page-heading"><div><h1>Preview Sample Teachers</h1><p>These are fictional planning records. Nothing has been saved.</p></div><a href="{{ route('modules.index', 'teachers') }}" class="btn btn-outline-secondary">Cancel</a></div>
<section class="panel p-4"><p>Subject qualifications and working hours remain unconfirmed. These teachers cannot receive teaching assignments until an administrator verifies their profiles.</p><ul>@foreach($samples as $sample)<li>{{ $sample['name'] }} · {{ $sample['email'] }}</li>@endforeach</ul>
<form method="post" action="{{ route('teacher-management.samples.confirm') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><label class="d-block mb-3"><input class="form-check-input" type="checkbox" name="confirm" value="1" required> I approve adding these fictional teachers. Existing teacher records will be preserved.</label><button class="btn btn-primary">Confirm and Generate Sample Teachers</button></form></section>
@endsection
