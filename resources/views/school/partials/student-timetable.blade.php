@php
    $links = ['section' => 'timetable'];
    $selectedDay = $date->format('l');
@endphp

<div class="student-timetable">
    <header class="student-timetable-hero">
        <span class="st-calendar-icon"><i class="bi bi-calendar-week" aria-hidden="true"></i></span>
        <div>
            <h1>{{ __('My Timetable') }}</h1>
            <p>{{ __('View your class schedule for the week.') }}</p>
        </div>
    </header>

    <section class="st-panel" aria-labelledby="student-lessons-title">
        <div class="st-toolbar">
            <div class="st-date">
                <a class="st-arrow" href="{{ route('dashboard', $links + ['date' => $date->subDay()->toDateString()]) }}" aria-label="{{ __('Previous day') }}" @if($weekly) hidden @endif>
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </a>
                <h2 id="student-lessons-title">
                    <i class="bi bi-calendar" aria-hidden="true"></i>
                    {{ $weekly ? __('Monday to Friday') : $date->translatedFormat('l, M j, Y') }}
                </h2>
                <a class="st-arrow" href="{{ route('dashboard', $links + ['date' => $date->addDay()->toDateString()]) }}" aria-label="{{ __('Next day') }}" @if($weekly) hidden @endif>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
            </div>
            <a class="st-pill st-view" href="{{ route('dashboard', $links + ['date' => $date->toDateString()] + ($weekly ? [] : ['view' => 'weekly'])) }}">
                <i class="bi bi-calendar-week" aria-hidden="true"></i>
                {{ $weekly ? __('View Daily Timetable') : __('View Weekly Timetable') }}
            </a>
        </div>

        @unless($weekly)
            <nav class="st-weekdays" aria-label="{{ __('Choose timetable day') }}">
                @foreach($weekdays as $weekday)
                    <a class="st-pill" href="{{ route('dashboard', $links + ['date' => $weekStart->addDays($loop->index)->toDateString()]) }}" @if($selectedDay === $weekday) aria-current="date" @endif>{{ __($weekday) }}</a>
                @endforeach
            </nav>
        @endunless

        @unless($student)
            <p class="st-empty">{{ __('No student records are linked to your account. Contact your school if you need help.') }}</p>
        @endunless

        @foreach($days as $weekday)
            <div class="st-day">
                @if($weekly)
                    <h3>{{ __($weekday) }}</h3>
                @endif
                <ul class="st-lessons">
                    @forelse($lessonsByDay->get($weekday, []) as $lesson)
                        @php
                            $subject = $lesson->subject?->name;
                            $icon = match (true) {
                                ! $subject => 'cup-hot',
                                preg_match('/\b(ICT|IT|computer|computing|technology)\b/i', $subject) === 1 => 'display',
                                default => 'book',
                            };
                        @endphp
                        <li class="st-lesson">
                            <span class="st-subject-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                            <div class="st-subject">
                                <strong>{{ $subject ?? __('Study break') }}</strong>
                                <div class="st-meta">
                                    @if($lesson->teacher)
                                        <span><i class="bi bi-person" aria-hidden="true"></i>{{ $lesson->teacher->name }}</span>
                                    @endif
                                    @if($room = $lesson->classroom?->room_number ?? $lesson->room)
                                        <span><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ __('Room :number', ['number' => $room]) }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="st-time">
                                <span class="st-pill"><i class="bi bi-clock" aria-hidden="true"></i>{{ substr($lesson->starts_at, 0, 5) }} &ndash; {{ substr($lesson->ends_at, 0, 5) }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="st-empty">{{ $holidays->has($weekday) ? __('School holiday. No class.') : __('No lessons scheduled for this day.') }}</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </section>
</div>
