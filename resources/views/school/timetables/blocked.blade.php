@extends('school.layout')
@section('title','Khmer Draft Requires Review')
@section('content')
<section class="panel p-4"><h1>Khmer replacement draft is blocked</h1><p>No lessons were published. Resolve these prerequisites before a complete timetable can be generated. Existing students, enrollments and timetables are preserved.</p><ul>@foreach($draft['blockers'] as $blocker)<li>{{ $blocker['message'] }}</li>@endforeach</ul><a class="btn btn-outline-secondary" href="{{ route('timetables.diagnostics') }}">Review diagnostics</a></section>
@include('school.timetables.class-validation', ['validation' => $draft['diagnostics']['class_validation']])
@endsection
