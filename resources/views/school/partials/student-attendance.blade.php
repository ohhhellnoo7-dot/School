@php
    $links = ['section' => 'attendance', 'trend_days' => $trendDays];
    $firstColumn = $month->dayOfWeekIso;
    $shownStatuses = $counts->has('excused') ? $statuses : array_diff_key($statuses, ['excused' => true]);
@endphp

<div class="student-timetable student-attendance">
    <header class="student-timetable-hero sa-hero">
        <span class="st-calendar-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></span>
        <div>
            <h1>{{ __('My Attendance') }}</h1>
            <p>{{ __('Keep track of your attendance records and patterns.') }}</p>
        </div>
    </header>

    @unless($student)
        <p class="st-empty">{{ __('No student records are linked to your account. Contact your school if you need help.') }}</p>
    @endunless

    <div class="sa-metrics">
        @foreach(array_intersect_key($statuses, array_flip(['present', 'late', 'absent'])) + ['rate' => 'bar-chart-fill'] as $status => $icon)
            <div class="sa-metric" data-status="{{ $status }}">
                <span class="sa-symbol"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                <div>
                    <span>{{ $status === 'rate' ? __('Attendance Rate') : __(ucfirst($status)) }}</span>
                    <strong>{{ $status === 'rate' ? ($rate === null ? '–' : $rate.'%') : trans_choice(':count day|:count days', $counts->get($status, 0)) }}</strong>
                </div>
            </div>
        @endforeach
    </div>

    <div class="sa-board">
        <section class="sa-panel sa-calendar" aria-labelledby="sa-calendar-title">
            <h2 id="sa-calendar-title"><i class="bi bi-calendar2-week" aria-hidden="true"></i>{{ __('Attendance Calendar') }}</h2>
            <nav class="sa-month" aria-label="{{ __('Choose attendance month') }}">
                <a class="st-arrow" href="{{ route('dashboard', $links + ['month' => $month->subMonth()->format('Y-m')]) }}" aria-label="{{ __('Previous month') }}"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                <strong>{{ $month->translatedFormat('F Y') }}</strong>
                <a class="st-arrow" href="{{ route('dashboard', $links + ['month' => $month->addMonth()->format('Y-m')]) }}" aria-label="{{ __('Next month') }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
            </nav>
            <table class="sa-days" aria-label="{{ $month->translatedFormat('F Y') }}">
                <thead><tr>
                    @foreach(range(0, 6) as $offset)
                        <th scope="col">{{ $month->startOfWeek(\Carbon\CarbonInterface::MONDAY)->addDays($offset)->translatedFormat('D') }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                    @for($cell = 1; $cell <= (int) ceil(($month->daysInMonth + $firstColumn - 1) / 7) * 7; $cell++)
                        @php
                            $day = $cell - $firstColumn + 1;
                        @endphp
                        @if(($cell - 1) % 7 === 0)<tr>@endif
                        <td>
                            @if($day > 0 && $day <= $month->daysInMonth)
                                @php
                                    $date = $month->day($day);
                                    $status = $byDate->get($date->toDateString())?->status;
                                @endphp
                                <time datetime="{{ $date->toDateString() }}" data-status="{{ $status }}">
                                    {{ $day }}@if($status)<span class="visually-hidden"> {{ __(ucfirst($status)) }}</span>@endif
                                </time>
                            @endif
                        </td>
                        @if($cell % 7 === 0)</tr>@endif
                    @endfor
                </tbody>
            </table>
            <ul class="sa-legend">
                @foreach($shownStatuses as $status => $icon)
                    <li data-status="{{ $status }}"><span aria-hidden="true"></span>{{ __(ucfirst($status)) }}</li>
                @endforeach
            </ul>
        </section>

        <section class="sa-panel sa-summary" aria-labelledby="sa-summary-title">
            <h2 id="sa-summary-title"><i class="bi bi-calendar2-check" aria-hidden="true"></i>{{ __('Attendance Summary') }}</h2>
            <div class="sa-summary-body">
                <div class="sa-ring" style="--sa-rate: {{ $rate ?? 0 }}%" title="{{ __('Share of recorded days marked present') }}">
                    <div><strong>{{ $rate === null ? '–' : $rate.'%' }}</strong><span>{{ __('Attendance Rate') }}</span></div>
                </div>
                <ul>
                    @foreach($shownStatuses as $status => $icon)
                        <li data-status="{{ $status }}">
                            <span class="sa-status"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ __(ucfirst($status)) }}</span>
                            <strong>{{ trans_choice(':count day|:count days', $counts->get($status, 0)) }}</strong>
                            <span>{{ $percentages->get($status, 0) }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="sa-panel sa-trend" aria-labelledby="sa-trend-title">
            <header>
                <h2 id="sa-trend-title"><i class="bi bi-calendar2-week" aria-hidden="true"></i>{{ __('Attendance Trend') }}</h2>
                <details class="sa-trend-picker">
                    <summary>{{ __('Last :count school days', ['count' => $trendDays]) }} <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                    <div>
                        @foreach([8, 16] as $count)
                            <a href="{{ route('dashboard', ['section' => 'attendance', 'month' => $month->format('Y-m'), 'trend_days' => $count]) }}" @if($count === $trendDays) aria-current="true" @endif>{{ __('Last :count school days', ['count' => $count]) }}</a>
                        @endforeach
                    </div>
                </details>
            </header>
            @if($trend->isEmpty())
                <p class="st-empty">{{ __('No attendance records for this month.') }}</p>
            @else
                @php
                    $points = $trend->map(fn ($point, $i) => [
                        round(1000 * ($i + .5) / $trend->count(), 2),
                        100 - $point['rate'],
                    ]);
                    $line = $points->map(fn ($point) => implode(',', $point))->implode(' ');
                @endphp
                <div class="sa-chart" role="img" aria-label="{{ __('Cumulative share of recorded days marked present') }}. {{ $trend->map(fn ($point) => $point['date']->translatedFormat('M j').' '.$point['rate'].'%')->implode(', ') }}">
                    <div class="sa-axis" aria-hidden="true">
                        @foreach([100, 75, 50, 25, 0] as $tick)<span>{{ $tick }}%</span>@endforeach
                    </div>
                    <div class="sa-plot" aria-hidden="true" style="--sa-points: {{ $trend->count() }}">
                        <div class="sa-plot-lines">
                            <svg viewBox="0 0 1000 100" preserveAspectRatio="none">
                                <defs><linearGradient id="sa-chart-fill" x2="0" y2="100%"><stop stop-color="currentColor" stop-opacity=".35"/><stop offset="1" stop-color="currentColor" stop-opacity=".015"/></linearGradient></defs>
                                <polygon points="{{ $points->first()[0] }},100 {{ $line }} {{ $points->last()[0] }},100" fill="url(#sa-chart-fill)"/>
                                <polyline points="{{ $line }}" fill="none" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke"/>
                            </svg>
                            @foreach($trend as $index => $point)
                                <span class="sa-dot" data-status="{{ $point['status'] }}" style="--sa-x: {{ $points[$index][0] / 10 }}%; --sa-y: {{ $points[$index][1] }}%"></span>
                            @endforeach
                        </div>
                        <div class="sa-chart-dates">
                            @foreach($trend as $point)
                                <span>{{ $point['date']->translatedFormat('M j') }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </section>
    </div>

    @include('school.partials.student-attendance-history')
</div>
