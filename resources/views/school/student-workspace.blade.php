@extends('school.layout')

@php($title = __(\App\Http\Controllers\StudentWorkspaceController::SECTIONS[$section][0] ?? 'My dashboard'))
@section('title', $title)

@if($section === 'overview')
    @push('scripts')
        @include('school.partials.khmer-calendar-scripts')
    @endpush
@endif

@section('content')
@fragment('student-content')
@if($section === 'overview')
    @include('school.partials.student-dashboard')
@elseif($section === 'classes')
    @include('school.partials.student-classes')
@elseif($section === 'timetable')
    @include('school.partials.student-timetable')
@elseif($section === 'attendance')
    @include('school.partials.student-attendance')
@elseif($section === 'fees')
    @include('school.partials.student-fees')
@elseif($section === 'coursework')
    @include('school.partials.student-coursework')
@elseif($section === 'announcements')
    @include('school.partials.student-announcements')
@else
    @include('school.partials.student-record')
@endif
@endfragment
@endsection
