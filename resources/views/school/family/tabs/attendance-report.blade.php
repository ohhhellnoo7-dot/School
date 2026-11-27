@php
    $statusIcons = ['present' => 'check-lg', 'late' => 'clock', 'absent' => 'x-lg', 'excused' => 'file-earmark-check'];
    $days = collect(range(1, $month->daysInMonth))->map(fn ($number) => $month->day($number));
    $closure = app(\App\Services\SchoolHolidayClosure::class);
    $holidays = $closure->enabled() ? $days->mapWithKeys(fn ($day) => [$day->day => $closure->calendarHoliday($day)]) : collect();
    $reportClasses = $classes->isEmpty() ? [null] : $classes;
    $remarks = $records->filter(fn ($record) => filled($record->notes))->sortKeys();
@endphp
<ul class="family-mark-legend" aria-label="{{ __('Attendance status') }}">
    @foreach($statusIcons as $status => $icon)
        <li><span class="family-day-mark" data-tone="{{ $status }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span> {{ __(ucfirst($status)) }}</li>
    @endforeach
    <li><span aria-hidden="true">–</span> {{ __('Not recorded') }}</li>
</ul>
@foreach($reportClasses as $class)
    @php
        $marks = $class ? $classRecords->get($class->id, collect()) : $records;
    @endphp
    <section class="family-program-report">
        <h4 id="family-month-heading-{{ $class?->id ?? 'daily' }}">{{ $class?->program ?: ($class?->name ?? __('Daily attendance')) }}</h4>
        @if($class)
            <p>{{ collect([$class->program ? $class->name : null, $class->section, $class->session])->filter()->join(' / ') }}</p>
        @endif
        <div class="family-month-scroll" role="region" aria-labelledby="family-month-heading-{{ $class?->id ?? 'daily' }}" tabindex="0">
            <table class="family-month-grid">
                <thead>
                    <tr>
                        @foreach($days as $day)
                            @php
                                $holiday = $holidays->get($day->day);
                            @endphp
                            <th scope="col" @class(['is-weekend' => $day->isWeekend(), 'is-holiday' => $holiday])>
                                <time datetime="{{ $day->toDateString() }}" title="{{ $holiday ?? $day->translatedFormat('D, M j') }}">
                                    <span aria-hidden="true">{{ $day->day }}</span>
                                    <span class="visually-hidden">{{ $day->translatedFormat('D, M j') }} {{ $holiday }}</span>
                                </time>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        @foreach($days as $day)
                            @php
                                $date = $day->toDateString();
                            $mark = $marks->get($date) ?? $records->get($date);
                                $label = $mark ? __(ucfirst($mark->status)) : __('Not recorded');
                            @endphp
                            <td data-date="{{ $date }}" @class(['is-holiday' => $holidays->get($day->day)])>
                                <span class="family-day-mark" data-tone="{{ $mark?->status }}" title="{{ $label }}">
                                    @if(isset($statusIcons[$mark?->status]))
                                        <i class="bi bi-{{ $statusIcons[$mark->status] }}" aria-hidden="true"></i>
                                    @else
                                        <span aria-hidden="true">–</span>
                                    @endif
                                    <span class="visually-hidden">{{ $label }}</span>
                                </span>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
@endforeach
@if($remarks->isNotEmpty())
    <details class="mt-3">
        <summary>{{ __('Attendance remarks') }}</summary>
        <ul class="mb-0">
            @foreach($remarks as $record)
                <li><time datetime="{{ $record->date->toDateString() }}">{{ $record->date->translatedFormat('D, M j') }}</time> · {{ $record->notes }}</li>
            @endforeach
        </ul>
    </details>
@endif
