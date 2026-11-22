<nav class="family-day-nav" aria-label="{{ __('Timetable day') }}">
    @foreach($days as $weekday)
        <a href="{{ route('students.family.show', [$student, 'tab' => 'timetable', 'day' => $weekday]) }}" @if($day === $weekday) aria-current="page" @endif data-family-navigation>{{ __($weekday) }}</a>
    @endforeach
</nav>
<ul class="family-records">
    @forelse($lessons as $lesson)
        <li>
            <span class="family-pill">{{ substr($lesson->starts_at, 0, 5) }} – {{ substr($lesson->ends_at, 0, 5) }}</span>
            <div>
                <strong>{{ $lesson->subject?->name ?? __('Activity') }}</strong>
                <small>{{ collect([$lesson->teacher?->name ?? $lesson->subject?->teacher?->name, $lesson->classroom?->room_number ?? $lesson->room, $lesson->schoolClass?->program, $lesson->schoolClass?->session])->filter()->join(' · ') }}</small>
            </div>
        </li>
    @empty
        <li>{{ __('No lessons scheduled for this day.') }}</li>
    @endforelse
</ul>
