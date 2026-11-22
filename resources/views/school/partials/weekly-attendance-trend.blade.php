@php
    ['filters' => $weeklyFilters, 'classes' => $weeklyClasses, 'programs' => $weeklyPrograms, 'grades' => $weeklyGrades,
        'monday' => $weekMonday, 'friday' => $weekFriday, 'thisWeek' => $currentMonday, 'days' => $weekDays] = $weeklyAttendance;
    $dailyQuery = array_filter(['date' => request('date'), 'session' => $attendanceSession]);
    $weekLinks = $dailyQuery + array_diff_key($weeklyQuery, ['attendance_week' => true]);
    $hasRecords = $weekDays->contains(fn ($day) => $day['recorded'] > 0);
@endphp

<section class="panel weekly-trend" id="weekly-attendance" aria-labelledby="weekly-trend-title">
    <header class="weekly-trend-header">
        <div class="weekly-trend-heading">
            <span class="weekly-trend-icon"><i class="bi bi-graph-up" aria-hidden="true"></i></span>
            <div>
                <h2 id="weekly-trend-title">{{ __('Weekly Attendance Trend') }}</h2>
                <p>{{ $weekMonday->translatedFormat($weekMonday->year === $weekFriday->year ? 'M j' : 'M j, Y') }} &ndash; {{ $weekFriday->translatedFormat('M j, Y') }}</p>
            </div>
        </div>
        <nav class="weekly-trend-nav" aria-label="{{ __('Attendance week') }}">
            <a class="btn btn-outline-secondary" href="{{ route('dashboard', $weekLinks + ['attendance_week' => $weekMonday->subWeek()->toDateString()]) }}#weekly-attendance"><i class="bi bi-chevron-left" aria-hidden="true"></i> {{ __('Previous Week') }}</a>
            <a class="btn {{ $weekMonday->equalTo($currentMonday) ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('dashboard', $weekLinks) }}#weekly-attendance" @if($weekMonday->equalTo($currentMonday)) aria-current="date" @endif>{{ __('This Week') }}</a>
            <a class="btn btn-outline-secondary" href="{{ route('dashboard', $weekLinks + ['attendance_week' => $weekMonday->addWeek()->toDateString()]) }}#weekly-attendance">{{ __('Next Week') }} <i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        </nav>
    </header>

    <form class="weekly-trend-filters" method="get" action="{{ route('dashboard') }}#weekly-attendance" data-weekly-filters>
        @foreach($dailyQuery as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        <input type="hidden" name="attendance_week" value="{{ $weekMonday->toDateString() }}">
        <label>
            <span>{{ __('Program') }}</span>
            <select class="form-select" name="weekly_program">
                <option value="">{{ __('All programs') }}</option>
                @foreach($weeklyPrograms as $program)<option value="{{ $program }}" @selected(($weeklyFilters['weekly_program'] ?? null) === $program)>{{ __($program) }}</option>@endforeach
            </select>
        </label>
        <label>
            <span>{{ __('Grade') }}</span>
            <select class="form-select" name="weekly_grade">
                <option value="">{{ __('All grades') }}</option>
                @foreach($weeklyGrades as $grade)<option value="{{ $grade }}" @selected((int) ($weeklyFilters['weekly_grade'] ?? 0) === $grade)>{{ __('Grade :grade', ['grade' => $grade]) }}</option>@endforeach
            </select>
        </label>
        <label>
            <span>{{ __('Class') }}</span>
            <select class="form-select" name="weekly_class">
                <option value="">{{ __('All classes') }}</option>
                @foreach($weeklyClasses as $class)<option value="{{ $class->id }}" @selected((int) ($weeklyFilters['weekly_class'] ?? 0) === $class->id)>{{ $class->label }}</option>@endforeach
            </select>
        </label>
        <label>
            <span>{{ __('Session') }}</span>
            <select class="form-select" name="weekly_session">
                <option value="">{{ __('All sessions') }}</option>
                @foreach(\App\Support\AttendanceSession::OPTIONS as $session)<option value="{{ $session }}" @selected(($weeklyFilters['weekly_session'] ?? null) === $session)>{{ __($session) }}</option>@endforeach
            </select>
        </label>
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> {{ __('Apply') }}</button>
    </form>

    <div class="weekly-trend-chart">
        <canvas id="weeklyAttendanceChart" aria-hidden="true" data-attended-label="{{ __('attended') }}" data-chart-label="{{ __('Attendance rate') }}"></canvas>
        @unless($hasRecords)
            <p class="weekly-trend-empty"><i class="bi bi-calendar2-week" aria-hidden="true"></i> {{ __('No attendance recorded') }}</p>
        @endunless
    </div>
    <ul class="weekly-trend-days" aria-label="{{ __('Daily attendance rates') }}">
        @foreach($weekDays as $day)
            <li>
                <time datetime="{{ $day['date'] }}">{{ $day['label'] }}</time>
                @if($day['rate'] === null)
                    <span>{{ __('No attendance recorded') }}</span>
                @else
                    <strong>{{ $day['rate'] }}%</strong>
                @endif
            </li>
        @endforeach
    </ul>
    <p class="weekly-trend-policy">{{ __('Late counts as attended. Excused counts toward the recorded total.') }}</p>
</section>
