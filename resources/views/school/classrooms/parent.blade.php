@extends('school.layout')
@php($isTeacherFamilyView = auth()->user()->role === 'teacher')
@section('title', $isTeacherFamilyView ? __('My children’s classrooms') : __('My Class'))
@push('styles')
<link rel="stylesheet" href="{{ asset('css/parent-classroom.css') }}?v={{ filemtime(public_path('css/parent-classroom.css')) }}">
@endpush
@section('content')
<div class="parent-classroom">
    <header class="parent-classroom-hero" id="classroom-view" style="scroll-margin-top: 105px">
        <span class="parent-classroom-hero-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
        <div>
            <h1>{{ $isTeacherFamilyView ? __('My children’s classrooms') : __('My Class') }}</h1>
            <p>{{ __("View your child’s classroom and its current status.") }}</p>
            @if($isTeacherFamilyView)
                <div class="mt-2">@include('school.classrooms._teacher-room-switch', ['familyRoomView' => true])</div>
            @endif
        </div>
    </header>
    <div class="parent-classroom-rooms" data-parent-classroom-url="{{ route('classrooms.data', $isTeacherFamilyView ? ['my_children' => 1] : []) }}">
        @include('school.classrooms._parent-rooms')
    </div>
</div>
@endsection
@push('scripts')
<script src="{{ asset('js/parent-classroom.js') }}?v={{ filemtime(public_path('js/parent-classroom.js')) }}" defer></script>
@endpush
