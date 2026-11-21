<section class="attendance-summary-grid {{ $summaryClass ?? '' }}" aria-label="{{ __('Student attendance summary') }} · {{ $attendanceSummary['date']->format('M j, Y') }}">
    @foreach(['total' => ['Total students', 'people-fill'], 'present' => ['Present', 'check-lg'], 'absent' => ['Absent', 'x-lg'], 'late' => ['Late', 'clock'], 'excused' => ['Excused', 'shield-check']] as $status => [$label, $icon])
        @php
            $count = $status === 'total' ? $attendanceSummary['total'] : ($attendanceSummary['statuses'][$status] ?? 0);
        @endphp
        <article class="attendance-summary-card attendance-tone-{{ $status }}">
            <span class="attendance-summary-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
            <div><h2>{{ __($status !== 'total' && $attendanceSummary['date']->isToday() ? $label.' today' : $label) }}</h2><strong>{{ number_format($count) }}</strong></div>
            @if($status !== 'total' && $attendanceSummary['total'])
                <small>{{ round(100 * $count / $attendanceSummary['total'], 1) }}%</small>
            @endif
        </article>
    @endforeach
</section>
