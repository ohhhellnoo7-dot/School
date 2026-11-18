<p class="small text-muted">All subjects · {{ $academicYear }} · Saved lessons and designated breaks</p>
<div class="teacher-preview-week">
@foreach(\App\Services\TeacherManagementService::DAYS as $day)
<section><h3>{{ __($day) }}</h3>
@forelse($lessons->where('day', $day)->sortBy('starts_at') as $entry)
<article class="teacher-preview-lesson"><strong>{{ $entry->subject?->name ?? 'Lesson' }}</strong><div>{{ substr($entry->starts_at, 0, 5) }}–{{ substr($entry->ends_at, 0, 5) }}</div><small>{{ $entry->schoolClass?->label }}</small><div><small>Room {{ $entry->classroom?->room_number ?? $entry->room }}</small></div></article>
@empty<p class="small text-muted">No saved lessons</p>@endforelse
@foreach($breaks->where('weekday', $day) as $break)<article class="teacher-preview-break">Free period<br>{{ substr($break->start_time, 0, 5) }}–{{ substr($break->end_time, 0, 5) }}</article>@endforeach
</section>
@endforeach
</div>
