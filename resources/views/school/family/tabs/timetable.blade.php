<div class="family-timetable-heading">
    <span class="family-timetable-heading-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
    <div>
        <h2 id="family-tab-heading">{{ __('Timetable') }}</h2>
        @if($firstClass = $timetableClasses->first())
            <p>{{ collect([$firstClass->program, $firstClass->name, $firstClass->section, $firstClass->session])->filter()->join(' / ') }}</p>
        @endif
    </div>
    <nav class="family-week-nav" aria-label="{{ __('Timetable week') }}">
        <a href="{{ route('students.family.show', [$student, 'tab' => 'timetable', 'week' => $week->subWeek()->toDateString()]) }}" aria-label="{{ __('Previous week') }}" data-family-navigation><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
        <span>{{ $week->translatedFormat('M j') }} &ndash; {{ $week->addDays(4)->translatedFormat('M j, Y') }}</span>
        <a href="{{ route('students.family.show', [$student, 'tab' => 'timetable', 'week' => $week->addWeek()->toDateString()]) }}" aria-label="{{ __('Next week') }}" data-family-navigation><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
    </nav>
</div>
@forelse($timetableClasses as $class)
    @php
        $classLessons = $lessons->get($class->timetableOwnerId(), collect());
    @endphp
    <section class="family-timetable-program" aria-labelledby="{{ $loop->first ? 'family-tab-heading' : 'family-timetable-'.$class->id }}">
        @unless($loop->first)
            <h3 id="family-timetable-{{ $class->id }}">{{ $class->program ?: $class->name }}</h3>
            <p>{{ collect([$class->program ? $class->name : null, $class->section, $class->session])->filter()->join(' / ') }}</p>
        @endunless
        <div class="family-timetable-week">
            @foreach($days as $weekday)
                <section class="family-timetable-day">
                    <h4><i class="bi bi-calendar3" aria-hidden="true"></i> {{ __($weekday) }}</h4>
                    <ul class="family-records">
                        @forelse($classLessons->get($weekday, []) as $lesson)
                            @php
                                $subject = $lesson->subject?->name ?? __('Activity');
                                $englishIcon = match (Str::lower(trim($subject))) {
                                    'english grammar' => 'book-check',
                                    'english vocabulary' => 'languages',
                                    'english reading' => 'book-open',
                                    'english writing' => 'pen-line',
                                    'english listening' => 'headphones',
                                    'english speaking' => 'messages-square',
                                    default => null,
                                };
                                $icon = match (true) {
                                    Str::contains($subject, 'Earth Science', true) => 'tree',
                                    Str::contains($subject, 'Chemistry', true) => 'droplet',
                                    Str::contains($subject, 'Geography', true) => 'globe-americas',
                                    Str::contains($subject, 'Physics', true) => 'radar',
                                    Str::contains($subject, 'Math', true) => 'calculator',
                                    Str::contains($subject, 'Biology', true) => 'flower1',
                                    Str::contains($subject, 'History', true) => 'bank',
                                    Str::contains($subject, 'Physical Education', true) => 'person-walking',
                                    Str::contains($subject, 'Civics', true) => 'building',
                                    Str::contains($subject, ['ICT', 'Computer'], true) => 'laptop',
                                    Str::contains($subject, 'English', true) => 'alphabet',
                                    default => 'book',
                                };
                            @endphp
                            <li>
                                <span class="family-subject-icon">
                                    @if($englishIcon)
                                        @include('school.family.tabs.english-subject-icon', ['name' => $englishIcon])
                                    @else
                                        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                                    @endif
                                </span>
                                <div>
                                    <span class="family-lesson-time"><i class="bi bi-clock" aria-hidden="true"></i> {{ substr($lesson->starts_at, 0, 5) }} &ndash; {{ substr($lesson->ends_at, 0, 5) }}</span>
                                    <strong>{{ $subject }}</strong>
                                    <small>{{ collect([$lesson->teacher?->name ?? $lesson->subject?->teacher?->name, $lesson->classroom?->room_number ?? $lesson->room])->filter()->join(' / ') }}</small>
                                </div>
                            </li>
                        @empty
                            <li>{{ __('No lessons scheduled for this day.') }}</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>
    </section>
@empty
    <p>{{ __('No lessons scheduled.') }}</p>
@endforelse
