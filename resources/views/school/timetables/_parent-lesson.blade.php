@php
    $subject = $entry->isRelaxation() ? __('Relaxation Period') : ($entry->subject?->name ?? __('Missing subject'));
    $style = \App\Support\TimetableGrid::subjectStyle($subject, $entry->isRelaxation());
@endphp
<article data-lesson-id="{{ $entry->id }}" class="parent-timetable-lesson" data-tone="{{ $style[0] }}"
    @if($parentList ?? false)
        data-parent-export="{{ json_encode([__($entry->day), substr($entry->starts_at, 0, 5), substr($entry->ends_at, 0, 5), $subject, $entry->teacher?->name, $entry->classroom?->room_number ?? $entry->room]) }}"
    @endif>
    <strong><i class="bi bi-{{ $style[1] }}" aria-hidden="true"></i> {{ $subject }}</strong>
    @if($parentList ?? false)
        <span><i class="bi bi-clock" aria-hidden="true"></i> {{ substr($entry->starts_at, 0, 5) }}–{{ substr($entry->ends_at, 0, 5) }}</span>
        @if($entry->teacher && !$entry->isRelaxation())<span><i class="bi bi-person" aria-hidden="true"></i> {{ $entry->teacher->name }}</span>@endif
        @if($room = $entry->classroom?->room_number ?? $entry->room)<span><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $room }}</span>@endif
    @elseif($continuation ?? false)
        <small class="visually-hidden">{{ __('Continued') }}</small>
    @endif
</article>
