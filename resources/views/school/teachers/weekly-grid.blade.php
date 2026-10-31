<section class="panel p-3"><div class="d-flex flex-wrap gap-3 mb-3"><span class="teacher-legend lesson">{{ __('Teaching') }}</span><span class="teacher-legend break">{{ __('Free Period') }}</span><span class="teacher-legend">{{ __('Available / Off duty') }}</span><span class="teacher-legend conflict">{{ __('Conflict') }}</span></div>
<div class="table-responsive"><table class="table teacher-weekly-grid"><thead><tr><th>{{ __('Time') }}</th>@foreach(\App\Services\TeacherManagementService::DAYS as $day)<th>{{ __($day) }}</th>@endforeach</tr></thead><tbody>
@php($windows = collect(\App\Services\TeacherManagementService::SESSIONS)->filter(fn ($hours, $session) => $session !== 'Evening' || $periods->contains(fn ($p) => substr($p->end_time, 0, 5) > '17:00') || $lessons->contains(fn ($s) => substr($s->ends_at, 0, 5) > '17:00')))
@foreach($windows as $session => [$from, $to])
@if(request()->filled('session') && request('session') !== $session) @continue @endif
@for($minute = \App\Services\TeacherManagementService::minute($from); $minute + 60 <= \App\Services\TeacherManagementService::minute($to); $minute += 60)
@php($start = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60)) @php($end = sprintf('%02d:%02d', intdiv($minute+60, 60), ($minute+60)%60))
<tr><th scope="row">{{ $start }}–{{ $end }}</th>@foreach(\App\Services\TeacherManagementService::DAYS as $day)
@php($dailyLessons = $lessons->filter(fn ($s) => $s->day === $day && \App\Services\TeacherManagementService::overlaps($start, $end, $s->starts_at, $s->ends_at)))
@php($dailyBreaks = $breaks->filter(fn ($b) => $b->weekday === $day && \App\Services\TeacherManagementService::overlaps($start, $end, $b->start_time, $b->end_time)))
@php($conflict = $dailyLessons->contains(fn ($a) => $dailyLessons->contains(fn ($b) => $a->id !== $b->id && \App\Services\TeacherManagementService::overlaps($a->starts_at, $a->ends_at, $b->starts_at, $b->ends_at))) || $dailyLessons->contains(fn ($s) => $dailyBreaks->contains(fn ($b) => \App\Services\TeacherManagementService::overlaps($s->starts_at, $s->ends_at, $b->start_time, $b->end_time))))
<?php
    $single = $dailyLessons->count() === 1 ? $dailyLessons->first() : null;
    $canSpan = $single && \App\Services\TeacherManagementService::duration($single->starts_at, $single->ends_at) % 60 === 0
        && !$breaks->contains(fn ($b) => $b->weekday === $day && \App\Services\TeacherManagementService::overlaps($single->starts_at, $single->ends_at, $b->start_time, $b->end_time))
        && !$lessons->contains(fn ($s) => $s->id !== $single->id && $s->day === $day && \App\Services\TeacherManagementService::overlaps($single->starts_at, $single->ends_at, $s->starts_at, $s->ends_at));
    $span = $canSpan ? max(1, (int) ceil((min(\App\Services\TeacherManagementService::minute($single->ends_at), \App\Services\TeacherManagementService::minute($to)) - $minute) / 60)) : 1;
?>
@if($canSpan && \App\Services\TeacherManagementService::minute($single->starts_at) < $minute) @continue @endif
<td rowspan="{{ $span }}" class="{{ $conflict ? 'teacher-cell-conflict' : '' }}">@if($conflict)<strong class="text-danger d-block">{{ __('Review conflict') }}</strong>@endif
@foreach($dailyLessons as $slot)<article class="teacher-lesson-card"><strong>{{ $slot->subject?->name }}</strong><span>{{ $slot->subject?->schoolClass?->label }} · {{ __('Room') }} {{ $slot->room ?: __('Not assigned') }}</span><small>{{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}</small></article>@endforeach
@foreach($dailyBreaks as $b)<article class="teacher-break-card"><strong>{{ __('FREE PERIOD') }}</strong><span>{{ __('1 HOUR') }}</span><small>{{ substr($b->start_time, 0, 5) }}–{{ substr($b->end_time, 0, 5) }}</small></article>@endforeach
@if($dailyLessons->isEmpty() && $dailyBreaks->isEmpty())<span class="small text-secondary">{{ __($service->onDuty($teacher, $day, $start, $end) ? 'Available' : 'Off duty') }}</span>@endif</td>@endforeach</tr>
@endfor @endforeach
</tbody></table></div></section>
