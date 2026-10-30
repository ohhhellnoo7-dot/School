@extends('school.layout')
@section('title', 'Lesson Conflicts')
@section('content')
<section class="panel p-4"><h1>Lesson conflict review</h1><p>The same teacher cannot teach overlapping lessons. The same student cohort and the same physical classroom cannot have overlapping activities.</p><p>A lesson outside its configured program/session hours also needs correction.</p>@foreach($all as $entry)@include('school.timetables.lesson')@endforeach<a class="btn btn-primary mt-3" href="{{ route('timetables.lessons.edit',$lesson) }}">Fix Conflict</a></section>
@endsection
