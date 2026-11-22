@php
    ['filters' => $weeklyFilters, 'classes' => $weeklyClasses, 'programs' => $weeklyPrograms, 'grades' => $weeklyGrades,
        'monthStart' => $monthStart, 'days' => $monthDays] = $monthlyAttendance;
    $dailyQuery = array_filter(['date' => request('date'), 'session' => $attendanceSession]);
    $recordedDays = $monthDays->whereNotNull('rate');
    $hasRecords = $recordedDays->isNotEmpty();
    $formatRate = fn ($rate) => $rate === null ? '—' : (round($rate, 1).'%');
    $highest = $recordedDays->sortByDesc('rate')->first();
    $lowest = $recordedDays->sortBy('rate')->first();
    $metrics = [
        ['people-fill', 'Average Attendance', $formatRate($recordedDays->avg('rate')), null, 'average'],
        ['arrow-up-right', 'Highest Day', $formatRate($highest['rate'] ?? null), $highest, 'high'],
        ['arrow-down', 'Lowest Day', $formatRate($lowest['rate'] ?? null), $lowest, 'low'],
        ['calendar3', 'Days in Month', trans_choice(':count day|:count days', $monthDays->count()), null, 'days'],
    ];
@endphp

<section class="panel weekly-trend" id="weekly-attendance" aria-labelledby="weekly-trend-title">
    <header class="weekly-trend-header">
        <div class="weekly-trend-heading">
            <span class="weekly-trend-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
            <div>
                <h2 id="weekly-trend-title">{{ __('Monthly Attendance Trend') }}</h2>
                <p>{{ $monthStart->translatedFormat('F Y') }}</p>
            </div>
        </div>
        <img class="monthly-attendance-art" src="{{ asset('images/student-attendance-calendar.png') }}" alt="">
    </header>

    <form class="weekly-trend-filters" method="get" action="{{ route('dashboard') }}#weekly-attendance" data-weekly-filters>
        @foreach($dailyQuery as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
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

    <dl class="monthly-attendance-metrics">
        @foreach($metrics as [$icon, $label, $value, $day, $tone])
            <div class="monthly-attendance-metric" data-tone="{{ $tone }}">
                <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                <dt>{{ __($label) }}</dt>
                <dd>
                    <strong>{{ $value }}</strong>
                    @if($day)
                        <time datetime="{{ $day['date'] }}">{{ $monthStart->addDays((int) $day['label'] - 1)->translatedFormat('M j') }}</time>
                    @endif
                </dd>
            </div>
        @endforeach
    </dl>

    <div class="monthly-attendance-modes" role="group" aria-label="{{ __('Attendance chart view') }}">
        @foreach(['combined' => ['bar-chart-line', 'Line & Column'], 'area' => ['graph-up', 'Area'], 'column' => ['bar-chart-fill', 'Column']] as $mode => [$icon, $label])
            <button type="button" data-attendance-mode="{{ $mode }}" aria-pressed="{{ $mode === 'combined' ? 'true' : 'false' }}">
                <i class="bi bi-{{ $icon }}" aria-hidden="true"></i> {{ __($label) }}
            </button>
        @endforeach
    </div>
    <div class="monthly-attendance-chart">
        <canvas id="monthlyAttendanceChart" aria-hidden="true"
            data-rate-label="{{ __('Attendance rate') }}"
            @foreach(\App\Services\MonthlyStudentAttendance::STATUSES as $status)
                data-{{ $status }}="{{ __(ucfirst($status)) }}"
            @endforeach
        ></canvas>
    </div>
    <div class="monthly-attendance-legend">
        <span>{{ __('Attendance rate') }}</span>
        <span>{{ __('90% reference') }}</span>
    </div>
    @unless($hasRecords)
        <p>{{ __('No attendance recorded this month') }}</p>
    @endunless
    <details data-monthly-rates>
        <summary>{{ __('Daily attendance details') }}</summary>
        <ul class="weekly-trend-days">
            @foreach($monthDays as $day)
                <li>
                    <time datetime="{{ $day['date'] }}">{{ $day['label'] }}</time>
                    @if($day['rate'] === null)
                        <span>{{ __('No attendance recorded') }}</span>
                    @else
                        <strong>{{ $day['rate'] }}%</strong>
                        @foreach($day['counts'] as $status => $count)
                            <span>{{ __(ucfirst($status)) }} {{ $count }}</span>
                        @endforeach
                    @endif
                </li>
            @endforeach
        </ul>
    </details>
    <p class="weekly-trend-policy">{{ __('Late counts as attended. Excused counts toward the recorded total.') }}</p>
</section>
