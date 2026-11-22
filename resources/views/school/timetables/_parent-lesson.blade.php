@php
    $subject = $entry->isRelaxation() ? __('Relaxation Period') : ($entry->subject?->name ?? __('Missing subject'));
    $name = mb_strtolower($subject);
    $style = match (true) {
        $entry->isRelaxation() => ['gold', 'cup-hot'],
        str_contains($name, 'physic'), str_contains($name, 'រូបវិទ្យា') => ['teal', 'flask'],
        str_contains($name, 'chemistry'), str_contains($name, 'គីមី') => ['pink', 'flask'],
        str_contains($name, 'math'), str_contains($name, 'គណិត') => ['blue', 'calculator'],
        str_contains($name, 'english'), str_contains($name, 'អង់គ្លេស') => ['purple', 'book'],
        str_contains($name, 'biolog'), str_contains($name, 'ជីវវិទ្យា') => ['green', 'leaf'],
        str_contains($name, 'geography'), str_contains($name, 'ភូមិវិទ្យា') => ['gold', 'globe2'],
        str_contains($name, 'ict'), str_contains($name, 'computer') => ['sky', 'display'],
        str_contains($name, 'khmer'), str_contains($name, 'ភាសាខ្មែរ') => ['green', 'book'],
        default => [['teal', 'blue', 'purple', 'green', 'sky'][crc32($name) % 5], 'book'],
    };
@endphp
<article data-lesson-id="{{ $entry->id }}" class="parent-timetable-lesson" data-tone="{{ $style[0] }}">
    <strong><i class="bi bi-{{ $style[1] }}" aria-hidden="true"></i> {{ $subject }}</strong>
    @if($parentList ?? false)
        <span><i class="bi bi-clock" aria-hidden="true"></i> {{ substr($entry->starts_at, 0, 5) }}–{{ substr($entry->ends_at, 0, 5) }}</span>
        @if($entry->teacher && !$entry->isRelaxation())<span><i class="bi bi-person" aria-hidden="true"></i> {{ $entry->teacher->name }}</span>@endif
        @if($room = $entry->classroom?->room_number ?? $entry->room)<span><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $room }}</span>@endif
    @elseif($continuation ?? false)
        <small>{{ __('Continued') }}</small>
    @endif
</article>
