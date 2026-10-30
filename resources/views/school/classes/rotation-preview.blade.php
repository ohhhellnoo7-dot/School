@extends('school.layout')
@section('title', 'Schedule Preview')
@section('content')
<div class="page-heading"><div><h1>Schedule Preview</h1><p>{{ $class->label }} · Review the old and proposed weekly lessons.</p></div><a class="btn btn-outline-secondary" href="{{ route('classes.timetable', $class) }}">Choose Another Time</a></div>
<div class="row g-4">
@foreach(['Existing schedule' => $old, 'Proposed schedule' => $proposed] as $heading => $entries)
<section class="col-lg-6"><div class="panel p-3"><h2 class="h5">{{ $heading }}</h2>
@foreach(\App\Services\TeacherManagementService::DAYS as $day)<h3 class="h6 mt-3">{{ $day }}</h3>
@forelse($entries->where('day', $day)->sortBy('starts_at') as $entry)
@php($unchanged = $old->contains(fn ($s) => $s->day === $entry->day && substr($s->starts_at, 0, 5) === substr($entry->starts_at, 0, 5) && substr($s->ends_at, 0, 5) === substr($entry->ends_at, 0, 5) && $s->subject_id === $entry->subject_id && $s->teacher_id === $entry->teacher_id && $s->room === $entry->room))
<article class="timetable-entry {{ $entry->isRelaxation() ? 'relaxation-entry' : 'academic-entry' }} {{ $heading === 'Proposed schedule' && ! $unchanged ? 'rotation-changed' : '' }}"><strong>{{ $entry->isRelaxation() ? 'Scheduled relaxation' : $entry->subject?->name }}</strong><p>{{ substr($entry->starts_at, 0, 5) }}–{{ substr($entry->ends_at, 0, 5) }} · Room {{ $entry->room }}</p><small>{{ $entry->isRelaxation() ? 'No teacher' : ($entry->teacher?->name ?? $entry->subject?->teacher?->name) }}</small>@if($heading === 'Proposed schedule' && ! $unchanged)<span class="badge text-bg-info">Changed / New</span>@endif</article>
@empty<p class="small text-secondary">No Class</p>@endforelse @endforeach
</div></section>@endforeach</div>
<section class="panel p-4 mt-4"><p>Unused hours remain free. Highlighted blocks are new or changed. Previous entries are retained in cancellation history after replacement. Teacher and student views share the same lesson records.</p>
@if($plan['new_breaks'])<p>{{ count($plan['new_breaks']) }} teacher(s) will also receive three designated one-hour free periods.</p>@foreach($plan['new_breaks'] as $id => $breaks)<p><strong>{{ \App\Models\User::find($id)->name }}</strong>: @foreach($breaks as $b){{ $b['weekday'] }} {{ $b['start_time'] }}–{{ $b['end_time'] }}{{ $loop->last ? '' : ', ' }}@endforeach</p>@endforeach @endif
<form method="post" action="{{ route('classes.timetable.confirm', $class) }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
@if($plan['replace'])<label class="d-block mb-3"><input type="checkbox" name="replace" value="1" required> Replace the current timetable with this proposal.</label>@endif
<label class="d-block mb-3"><input type="checkbox" name="confirm" value="1" required> I approve the displayed lessons, rooms, teacher assignments and free periods.</label><button class="btn btn-primary">Confirm and Save Schedule</button></form></section>
@endsection
