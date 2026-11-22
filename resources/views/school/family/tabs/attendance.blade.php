<form class="family-filter" action="{{ $profile }}" method="get" data-family-navigation>
    <input type="hidden" name="tab" value="attendance">
    <div><label class="form-label" for="family-month">{{ __('Attendance month') }}</label><input class="form-control" id="family-month" type="month" name="month" value="{{ $month->format('Y-m') }}" required></div>
    <button class="btn family-button" type="submit">{{ __('Show month') }}</button>
</form>
<div class="family-attendance-grid">
    <div>
        <div class="family-attendance-total">
            <strong>{{ $rate === null ? '—' : $rate.'%' }}</strong>
            <div>{{ __('Attendance rate') }}<small>{{ __('Present and late days as a share of recorded days') }}</small></div>
        </div>
        @if($rate !== null)<progress class="family-progress" value="{{ $rate }}" max="100" aria-label="{{ __('Attendance rate') }}"></progress>@endif
        <ul class="family-attendance-legend">
            @foreach(['present', 'absent', 'late', 'excused'] as $status)
                <li class="family-pill" data-tone="{{ $status }}">{{ __(ucfirst($status)) }} <strong>{{ $counts->get($status, 0) }}</strong></li>
            @endforeach
        </ul>
        <h3>{{ __('Recent records') }}</h3>
        <ul class="family-records">
            @forelse($records->take(6) as $record)
                <li><time datetime="{{ $record->date->toDateString() }}">{{ $record->date->translatedFormat('D, M j') }}</time><span class="family-pill" data-tone="{{ $record->status }}">{{ __(ucfirst($record->status)) }}</span></li>
            @empty
                <li>{{ __('No attendance recorded this month.') }}</li>
            @endforelse
        </ul>
    </div>
    <div class="family-calendar">
        <h3>{{ $month->translatedFormat('F Y') }}</h3>
        <div class="family-calendar-days">
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayLabel)
                <b>{{ __($dayLabel) }}</b>
            @endforeach
            @for($offset = 1; $offset < $month->dayOfWeekIso; $offset++)
                <span aria-hidden="true"></span>
            @endfor
            @for($number = 1; $number <= $month->daysInMonth; $number++)
                @php
                    $date = $month->day($number)->toDateString();
                    $status = $records->get($date)?->status;
                    $caption = $status ? __(ucfirst($status)) : __('Not recorded');
                @endphp
                <time datetime="{{ $date }}" data-tone="{{ $status ?? 'neutral' }}" title="{{ $date }} · {{ $caption }}" aria-label="{{ $date }} · {{ $caption }}">{{ $number }}@if($status)<small aria-hidden="true">{{ mb_substr($caption, 0, 1) }}</small>@endif</time>
            @endfor
        </div>
    </div>
</div>
