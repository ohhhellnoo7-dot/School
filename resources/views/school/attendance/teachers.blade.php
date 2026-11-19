@extends('school.layout')
@section('title', 'Teacher attendance')
@section('content')
<div class="page-heading"><div><div class="eyebrow">TEACHERS</div><h1>Teacher attendance</h1><p>Daily attendance across all academic programs. Unmarked teachers are not counted as absent.</p></div></div>
<div id="teacher-attendance-records">
@if(auth()->user()->role === 'teacher')
    <a class="btn btn-outline-primary mb-3" href="{{ route('reports', array_filter(['date' => $date, 'session' => $filters['session'] ?? null])) }}">My attendance report</a>
@endif
@include('school.attendance._teachers')
</div>
@endsection
