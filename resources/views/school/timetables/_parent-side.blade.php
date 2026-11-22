<aside class="parent-timetable-side" data-parent-timetable-side>
    <section class="parent-timetable-side-panel" id="parent-timetable-today">
        <header>
            <i class="bi bi-calendar2-check" aria-hidden="true"></i>
            <div>
                <h2>{{ __('Today’s Schedule') }}</h2>
                <time datetime="{{ $parentSchedule['today']->toDateString() }}">{{ $parentSchedule['today']->translatedFormat('D, M j, Y') }}</time>
            </div>
        </header>
        @if($parentSchedule['todayLessons']->isEmpty())
            <p class="parent-today-empty">{{ __('No lessons scheduled today.') }}</p>
            @if($parentSchedule['nextDay'])
                <h3 class="parent-next-timetable-day">{{ __('Next in the timetable') }} &middot;
                    <time datetime="{{ $parentSchedule['nextDay']->toDateString() }}">{{ $parentSchedule['nextDay']->translatedFormat('D, M j') }}</time>
                </h3>
            @endif
        @endif
        @if($parentSchedule['todayLessons']->isNotEmpty() || $parentSchedule['nextLessons']->isNotEmpty())
        <ol class="parent-today-lessons">
            @foreach($parentSchedule['todayLessons']->isNotEmpty() ? $parentSchedule['todayLessons'] : $parentSchedule['nextLessons'] as $entry)
                <li>
                    <time>{{ substr($entry->starts_at, 0, 5) }}–{{ substr($entry->ends_at, 0, 5) }}</time>
                    @include('school.timetables._parent-lesson', ['parentList' => false, 'continuation' => false])
                </li>
            @endforeach
        </ol>
        @endif
    </section>

    <section class="parent-timetable-side-panel" id="parent-timetable-summary">
        <header>
            <i class="bi bi-bar-chart-line-fill" aria-hidden="true"></i>
            <h2>{{ __('Quick Summary') }}</h2>
        </header>
        <dl class="parent-timetable-summary">
            @foreach([
                ['book', 'Subjects', $parentSchedule['subjects'], 'teal'],
                ['layers', 'Daily Periods', count($periods), 'purple'],
                ['shield-check', 'Conflicts', $parentSchedule['conflicts'], 'green'],
                ['calendar-check', 'Days Scheduled', $parentSchedule['days'], 'pink'],
            ] as [$icon, $label, $value, $tone])
                <div data-tone="{{ $tone }}">
                    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                    <dt>{{ __($label) }}</dt>
                    <dd>{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</aside>
