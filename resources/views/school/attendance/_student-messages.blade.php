@php
    $statuses = [
        'present' => ['check-circle-fill', ':student arrived on time.'],
        'late' => ['clock-fill', ':student arrived late.'],
        'absent' => ['x-circle-fill', ':student was marked absent.'],
        'excused' => ['shield-check', ':student’s absence was excused.'],
    ];
@endphp
<ul class="student-attendance-messages" @if($live ?? false) data-attendance-message-feed="{{ $attendanceWeekStart->toDateString() }}" tabindex="0" aria-label="{{ __('Attendance messages for this week') }}" @endif>
    @forelse($records as $record)
        @php([$icon, $message] = $statuses[$record->status])
        <li class="student-attendance-message" data-status="{{ $record->status }}">
            <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
            <div>
                <strong>{{ __($message, ['student' => $record->student?->name ?? __('Student')]) }}</strong>
                <small>
                    <time datetime="{{ $record->date->toDateString() }}">{{ $record->date->translatedFormat('D, M j') }}</time>
                    @if($record instanceof \App\Models\ClassAttendance && $record->schoolClass)
                        &middot; {{ $record->schoolClass->label }}
                    @endif
                </small>
            </div>
        </li>
    @empty
        <li class="empty-state py-4">{{ __('No student attendance recorded for this week.') }}</li>
    @endforelse
</ul>
