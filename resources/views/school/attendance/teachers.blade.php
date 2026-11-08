@extends('school.layout')
@section('title', 'Teacher attendance')
@section('content')
<div class="page-heading"><div><div class="eyebrow">TEACHERS</div><h1>Teacher attendance</h1><p>Daily attendance across all academic programs. Unmarked teachers are not counted as absent.</p></div></div>
<div id="teacher-attendance-records">
@include('school.attendance._teachers')
</div>
@endsection
