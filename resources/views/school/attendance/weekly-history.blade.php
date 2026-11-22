@extends('school.layout')
@section('title', __('Student attendance history'))

@section('content')
<div class="page-heading">
    <div>
        <h1>{{ __('Student attendance history') }}</h1>
        <p>{{ $weekStart->translatedFormat('M j, Y') }} &ndash; {{ $weekStart->copy()->addDays(4)->translatedFormat('M j, Y') }}</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route('dashboard', ['attendance_scope' => $attendanceScope]) }}#student-attendance-updates">{{ __('Back to dashboard') }}</a>
</div>

<section class="panel p-3">
    @include('school.attendance._message-scope', ['scopeRoute' => 'attendance.student-history'])
    <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="{{ __('Attendance history week') }}">
        <a class="btn btn-outline-primary" href="{{ route('attendance.student-history', ['week' => $weekStart->copy()->subWeek()->toDateString(), 'attendance_scope' => $attendanceScope]) }}">{{ __('Previous week') }}</a>
        <a class="btn btn-outline-primary" href="{{ route('attendance.student-history', ['attendance_scope' => $attendanceScope]) }}">{{ __('This week') }}</a>
        @if($weekStart->copy()->addWeek()->lte(today()))
            <a class="btn btn-outline-primary" href="{{ route('attendance.student-history', ['week' => $weekStart->copy()->addWeek()->toDateString(), 'attendance_scope' => $attendanceScope]) }}">{{ __('Next week') }}</a>
        @endif
    </nav>
    @include('school.attendance._student-messages')
</section>
@endsection

@push('styles')
    <link href="{{ asset('css/dashboard-report.css') }}?v={{ filemtime(public_path('css/dashboard-report.css')) }}" rel="stylesheet">
@endpush
