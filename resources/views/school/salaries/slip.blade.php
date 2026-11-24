@extends('school.layout')
@section('title', $slip === 'salary' ? __('Salary slip') : __('Leave record'))

@section('content')
<article class="panel p-4 payroll-slip">
    <header class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
            <p class="text-secondary mb-1">{{ config('finance.school_name') }}</p>
            <h1 class="h3">{{ $slip === 'salary' ? __('Salary slip') : __('Annual leave record') }}</h1>
            <p class="mb-0">{{ $teacher->name }} @if($teacher->profile_number)&middot; {{ $teacher->profile_number }}@endif &middot; {{ $teacher->program ?: __('Teacher') }}</p>
            <p class="text-secondary mb-0">{{ $slip === 'salary' ? $month->translatedFormat('F Y') : $month->year }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2 d-print-none">
            <a class="btn btn-outline-primary" href="{{ route('employee-salaries.show', ['teacher' => $teacher, 'month' => $month->format('Y-m')]) }}">{{ __('Salary details') }}</a>
            <a class="btn btn-outline-primary" href="{{ route('teacher-attendance.monthly', ['month' => $month->format('Y-m'), 'program' => $teacher->program]) }}">{{ __('Monthly attendance') }}</a>
            <button class="btn btn-primary" type="button" onclick="window.print()">{{ __('Print') }}</button>
        </div>
    </header>

    @if($slip === 'leave')
        @foreach($leaveSegments as $leaveSegment)
            @include('school.salaries._leave-segment-note', ['segment' => $leaveSegment])
            @include('school.salaries._slip-table', ['reportRows' => [$leaveSegment], 'individualSlip' => true])
        @endforeach
        @include('school.salaries._leave-note')
    @else
        @include('school.salaries._slip-table')
    @endif
</article>
@endsection
