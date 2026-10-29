@extends('school.layout')
@section('title', $schoolClass->label.' timetable')
@section('content')
<div class="page-heading"><div><div class="eyebrow">CLASS TIMETABLE</div><h1>{{ $schoolClass->label }}</h1><p>Weekly study schedule · {{ $schoolClass->room ?: 'Room not assigned' }}</p></div><div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-secondary" href="{{ route('students.index', ['class' => $schoolClass->id]) }}">View students</a>@if(auth()->user()->role === 'administrator')<a class="btn btn-outline-secondary" href="{{ route('modules.edit', ['classes', $schoolClass->id]) }}">Edit class</a><a class="btn btn-primary" href="{{ route('modules.create', 'schedules') }}">Add lesson</a>@endif</div></div>
@if($schoolClass->session)<p class="text-secondary">{{ $schoolClass->session }} session: {{ implode(' – ', \App\Models\SchoolClass::SESSION_HOURS[$schoolClass->session]) }}</p>@endif
@if($slots->isEmpty())<div class="alert alert-info">No lessons have been scheduled for this class yet.@if(auth()->user()->role === 'administrator') Add subjects under Subjects, then add their days and times under Timetable.@endif</div>@endif
<div class="row g-3">
@foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
<div class="col-md-6 col-xl-4"><section class="panel h-100"><div class="panel-header"><h2>{{ $day }}</h2><span class="badge text-bg-light">{{ $slots->get($day, collect())->count() }} lessons</span></div><div class="p-3">
@forelse($slots->get($day, collect()) as $slot)
<article class="border rounded p-3 mb-2"><div class="small text-secondary mb-1"><i class="bi bi-clock me-1"></i>{{ substr($slot->starts_at, 0, 5) }} – {{ substr($slot->ends_at, 0, 5) }}</div><h3 class="h6 mb-1">{{ $slot->subject->name }}</h3><div class="small">@if($slot->subject->teacher)@include('school.partials.avatar', ['person' => $slot->subject->teacher])@endif{{ $slot->subject->teacher?->name ?: 'Teacher not assigned' }}</div><div class="small text-secondary">Room: {{ $slot->room ?: $schoolClass->room ?: 'Not assigned' }}</div>@if(auth()->user()->role === 'administrator')<a class="small" href="{{ route('modules.edit', ['schedules', $slot->id]) }}">Edit lesson</a>@endif</article>
@empty<p class="text-secondary mb-0">No lessons scheduled.</p>@endforelse
</div></section></div>
@endforeach
</div>
@endsection
