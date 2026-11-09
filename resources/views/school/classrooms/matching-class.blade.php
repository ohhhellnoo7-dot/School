@extends('school.layout')
@section('title', 'Find matching class')
@section('content')
@php($sourceLesson = $source['currentLesson'])
<div class="page-heading"><div><div class="eyebrow">Temporary subject lesson</div><h1>Find matching class</h1><p>{{ $moment->format('l, M j, Y H:i') }} &middot; Room {{ $classroom->room_number }}</p></div><a class="btn btn-outline-secondary" href="{{ route('classrooms.index', ['date' => $moment->toDateString(), 'time' => $moment->format('H:i'), 'academic_year' => request('academic_year')]) }}">Back to classrooms</a></div>
@include('school.classrooms._study-arrangement', ['entry' => $source])
@if($sourceLesson)
<section class="panel p-4 mb-3"><h2 class="h5">{{ $sourceLesson->schoolClass?->label }}</h2><p>{{ $sourceLesson->subject?->name }} &middot; {{ substr($sourceLesson->starts_at, 0, 5) }}&ndash;{{ substr($sourceLesson->ends_at, 0, 5) }}</p><p class="mb-0">Teacher: <strong>{{ $sourceLesson->teacher?->name ?: 'Unassigned' }}</strong> &middot; {{ ucfirst($source['currentTeacherAttendance'] ?? 'Not marked absent') }}</p></section>
@endif
<p>Join the matching class for this subject only, then return to the original classroom when this lesson ends. Matches have the same grade, subject, program and session. Review the room capacity before combining classes.</p>
@if($matches->isNotEmpty())
<section class="panel p-3 mb-3" aria-labelledby="matching-classes-title">
<div class="mb-3"><h2 id="matching-classes-title" class="h5">{{ $matches->count() }} matching {{ Str::plural('class', $matches->count()) }}</h2><p class="small text-secondary mb-0">All matches for {{ $sourceLesson->subject->name }}, {{ $sourceLesson->schoolClass->name }} &middot; {{ substr($sourceLesson->starts_at, 0, 5) }}&ndash;{{ substr($sourceLesson->ends_at, 0, 5) }}. Choose the class to study with.</p></div>
<div class="table-responsive"><table class="table align-middle matching-classes-table">
<thead><tr><th>Class</th><th>Receiving teacher</th><th>Subject / time</th><th>Room</th><th>Choose a class</th></tr></thead>
<tbody>
@foreach($matches as $match)
@php($receiving = $match['currentLesson'])
@php($sourceLabel = trim(preg_replace('/^Grade\s*/i', '', $sourceLesson->schoolClass->name).' '.$sourceLesson->schoolClass->section))
@php($targetLabel = trim(preg_replace('/^Grade\s*/i', '', $receiving->schoolClass->name).' '.$receiving->schoolClass->section))
<tr>
<td><strong class="text-nowrap">{{ $sourceLabel }} &rarr; {{ $targetLabel }}</strong><small class="d-block text-secondary">{{ $receiving->schoolClass->session }}</small></td>
<td><strong>{{ $receiving->teacher->name }}</strong></td>
<td>{{ $receiving->subject->name }}<small class="d-block text-nowrap">{{ substr($sourceLesson->starts_at, 0, 5) }}&ndash;{{ substr($sourceLesson->ends_at, 0, 5) }}</small></td>
<td><a href="{{ route('classrooms.show', ['classroom' => $match['classroom'], 'date' => $moment->toDateString(), 'time' => $moment->format('H:i'), 'program' => $receiving->schoolClass->program, 'session' => $receiving->schoolClass->session, 'academic_year' => request('academic_year')]) }}">{{ $match['classroom']->room_number }}</a><small class="d-block text-secondary">{{ $match['classroom']->capacity }} seats</small></td>
<td>
<form method="post" action="{{ route('classrooms.study-with', $classroom) }}">
    @csrf
    <input type="hidden" name="live" value="{{ request()->boolean('live') ? 1 : 0 }}">
    <input type="hidden" name="date" value="{{ $moment->toDateString() }}">
    <input type="hidden" name="time" value="{{ $moment->format('H:i') }}">
    <input type="hidden" name="academic_year" value="{{ request('academic_year') }}">
    <input type="hidden" name="source_schedule_id" value="{{ $sourceLesson->id }}">
    <input type="hidden" name="target_classroom_id" value="{{ $match['classroom']->id }}">
    <input type="hidden" name="target_schedule_id" value="{{ $receiving->id }}">
    <button type="submit" class="btn btn-primary btn-sm" aria-label="Study with Class {{ $targetLabel }}"><i class="bi bi-people me-1" aria-hidden="true"></i>Study with this class</button>
</form>
<details data-click-only class="mt-2 small"><summary>View note</summary><p class="mt-2">Suggested note: {{ $sourceLabel }} &rarr; {{ $targetLabel }} &middot; {{ $receiving->subject->name }} &middot; Teacher: {{ $receiving->teacher->name }} &middot; Room {{ $match['classroom']->room_number }} &middot; {{ $moment->toDateString() }}. This subject only. Return to Room {{ $classroom->room_number }} at {{ substr($sourceLesson->ends_at, 0, 5) }} after the lesson.</p></details>
</td>
</tr>
@endforeach
</tbody></table></div>
<p class="small mb-0 mt-3">This subject only. Return to Room {{ $classroom->room_number }} at <strong>{{ substr($sourceLesson->ends_at, 0, 5) }}</strong>, after {{ $sourceLesson->subject->name }} finishes.</p>
</section>
@elseif(!data_get($source, 'studyWith'))
<div class="panel p-4">No matching class found for this period with an available teacher.</div>
@endif
<p class="small text-secondary">Select Study with this class to save the arrangement on both classroom cards for this subject only. The labels end automatically at the return time.</p>
@endsection
