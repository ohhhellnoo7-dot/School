<section class="panel timetable-panel"><div class="timetable-scroll"><table class="weekly-timetable"><thead><tr><th scope="col">Time</th>@foreach($days as $day)<th scope="col">{{ $day }}</th>@endforeach</tr></thead><tbody>
@foreach($periods as [$start, $end])<tr><th scope="row" class="period-time"><strong>{{ $start }}</strong><span>{{ $end }}</span></th>
@foreach($days as $day)
@php
    $slot = $slots->get($day, collect())->first(fn ($s) => substr($s->starts_at, 0, 5) <= $start && substr($s->ends_at, 0, 5) > $start);
    $continued = $slot && substr($slot->starts_at, 0, 5) < $start;
    $span = $slot ? collect($periods)->filter(fn ($p) => $p[0] >= $start && $p[0] < substr($slot->ends_at, 0, 5))->count() : 1;
@endphp
@if($continued) @continue @endif
<td rowspan="{{ max(1, $span) }}" class="{{ $slot?->isRelaxation() ? 'relaxation-cell' : '' }}">
@if($slot)<article class="timetable-entry {{ $slot->isRelaxation() ? 'relaxation-entry' : 'academic-entry' }}" style="--subject-hue: {{ (($slot->subject_id ?? 0) * 47) % 360 }}">
<div class="entry-time">{{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }} · {{ \App\Services\TeacherManagementService::duration($slot->starts_at, $slot->ends_at) / 60 }} hour(s)</div>
@if($slot->isRelaxation())<span class="entry-kind">Scheduled relaxation</span><h2>{{ $slot->title_en ?: 'Relaxation Time' }}</h2><p class="khmer-label">{{ $slot->title_km }}</p>
@else<h2>{{ $slot->subject?->name ?: 'Academic lesson' }}</h2><p>{{ $slot->teacher?->name ?: 'Teacher unassigned' }}</p>@endif
<p>Room {{ $slot->room ?: $schoolClass->room }}</p>
@if(auth()->user()->role === 'administrator' && ! $slot->isRelaxation())<a class="entry-edit no-print" href="{{ route('modules.edit', ['schedules', $slot->id]) }}">Edit lesson</a>
<form class="no-print mt-2" method="post" action="{{ route('lessons.cancel', $slot) }}" data-confirm="Cancel this lesson? Its history and room reservation will be preserved.">@csrf<input type="hidden" name="confirm" value="1"><button class="btn btn-sm btn-outline-secondary">Cancel lesson</button></form>@endif
</article>@else<span class="empty-period">FREE PERIOD<br><small>No Class · No teacher</small></span>@endif
</td>@endforeach</tr>@endforeach
</tbody></table></div></section>
