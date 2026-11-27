@php
    $statusIcons = ['present' => 'check-lg', 'late' => 'clock', 'absent' => 'x-lg', 'excused' => 'file-earmark-check'];
    $days = collect(range(1, $month->daysInMonth))->map(fn ($number) => $month->day($number));
    $closure = app(\App\Services\SchoolHolidayClosure::class);
    $holidays = $closure->enabled() ? $days->mapWithKeys(fn ($day) => [$day->day => $closure->calendarHoliday($day)]) : collect();
    $remarks = $records->filter(fn ($record) => filled($record->notes))->sortKeys();
@endphp
<ul class="family-mark-legend" aria-label="{{ __('Attendance status') }}">
    @foreach($statusIcons as $status => $icon)
        <li><span class="family-day-mark" data-tone="{{ $status }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span> {{ __(ucfirst($status)) }}</li>
    @endforeach
    <li><span aria-hidden="true">–</span> {{ __('Not recorded') }}</li>
</ul>
<div class="family-month-scroll" role="region" aria-labelledby="family-attendance-report" tabindex="0">
    <table class="family-month-grid">
        <thead>
            <tr>
                <th scope="col">{{ __('Program / Class') }}</th>
                <th scope="col">{{ __('Session') }}</th>
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
            @foreach($classes->isEmpty() ? [null] : $classes as $class)
                <tr>
                    <th scope="row">
                        {{ $class?->program ?: ($class ? $class->name : __('Daily attendance')) }}
                        @if($class)<small class="d-block fw-normal">{{ $class->program ? $class->name : '' }} {{ $class->section }}</small>@endif
                    </th>
                    <td>{{ $class?->session ?: '–' }}</td>
                    @foreach($days as $day)
                        @php
                            $date = $day->toDateString();
                            $mark = $class ? $classRecords->get($class->id.':'.$student->id.':'.$date) : $records->get($date);
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
            @endforeach
        </tbody>
    </table>
</div>
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
