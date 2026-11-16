@php
    $records = $student->attendances->keyBy(fn ($record) => $record->date->toDateString());
    $dates = $records->isEmpty() ? collect() : collect(\Carbon\CarbonPeriod::create(
        $student->attendances->min('date')->toDateString(),
        $student->attendances->max('date')->toDateString()
    ))->reverse();
@endphp
@forelse($dates as $date)
    @php($attendance = $records->get($date->toDateString()))
    <tr>
        <td>{{ $date->format('M d, Y') }} <small class="text-secondary">{{ $date->format('l') }}</small></td>
        <td>
            @if($attendance)
                <span class="status-badge {{ $attendance->status }}">{{ $attendance->status }}</span>
            @else
                <span class="text-secondary">{{ $date->isWeekend() ? $date->format('l') : 'Not recorded' }}</span>
            @endif
        </td>
        <td>{{ $attendance?->notes ?: '—' }}</td>
    </tr>
@empty
    <tr><td colspan="3" class="p-4 text-center">No attendance recorded yet.</td></tr>
@endforelse
