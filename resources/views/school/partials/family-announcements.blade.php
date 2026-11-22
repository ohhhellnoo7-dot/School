@php
    $attendanceLabels = [
        'present' => ['check-circle-fill', 'On time', ':child arrived on time.'],
        'late' => ['clock-fill', 'Late', ':child arrived late.'],
        'absent' => ['x-circle-fill', 'Absent', ':child was marked absent.'],
        'excused' => ['shield-check', 'Excused', ':child’s absence was excused.'],
    ];
@endphp
<section class="panel h-100 family-notices" aria-labelledby="family-notices-title">
    <header class="panel-header">
        <div class="family-notices-heading">
            <i class="bi bi-megaphone-fill" aria-hidden="true"></i>
            <div>
                <h2 id="family-notices-title">{{ __('Family Announcements') }}</h2>
                <p>{{ __('School updates, holidays, and today’s attendance') }}</p>
            </div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('modules.index', 'announcements') }}">{{ __('View all') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </header>
    <ul class="family-notices-list">
        @foreach($familyHolidayNotices as $holiday)
            <li class="family-notice" data-tone="holiday">
                <span class="family-notice-icon"><i class="bi bi-calendar-heart" aria-hidden="true"></i></span>
                <div>
                    <h3>{{ __('khmer-calendar.holidays.'.$holiday['name']) }}</h3>
                    <p>{{ __('Khmer calendar holiday') }}</p>
                    <small><i class="bi bi-calendar-event" aria-hidden="true"></i>
                        <time datetime="{{ $holiday['start']->toDateString() }}">{{ $holiday['start']->translatedFormat($holiday['start']->eq($holiday['end']) ? 'D, M j, Y' : 'D, M j') }}</time>
                        @unless($holiday['start']->eq($holiday['end']))
                            &ndash; <time datetime="{{ $holiday['end']->toDateString() }}">{{ $holiday['end']->translatedFormat('D, M j, Y') }}</time>
                        @endunless
                    </small>
                </div>
            </li>
        @endforeach
        @foreach($familyAttendanceNotices as ['child' => $child, 'record' => $record])
            @php([$icon, $label, $message] = $attendanceLabels[$record->status])
            <li>
                <a class="family-notice" data-tone="{{ $record->status }}" data-family-attendance-notice
                    href="{{ route('students.family.show', [$child, 'tab' => 'attendance']) }}">
                    <span class="family-notice-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                    <div>
                        <h3>{{ __('Attendance') }} &middot; {{ __($label) }}</h3>
                        <p>{{ __($message, ['child' => $child->name]) }}</p>
                        <small><i class="bi bi-calendar-event" aria-hidden="true"></i>
                            <time datetime="{{ $record->date->toDateString() }}">{{ $record->date->translatedFormat('D, M j, Y') }}</time>
                            @if($record instanceof \App\Models\ClassAttendance && $record->schoolClass)
                                &middot; {{ $record->schoolClass->label }}
                            @endif
                        </small>
                    </div>
                    <i class="bi bi-chevron-right family-notice-arrow" aria-hidden="true"></i>
                </a>
            </li>
        @endforeach
        @foreach($announcements as $announcement)
            <li>
                <a class="family-notice" data-tone="{{ ['school', 'present', 'late', 'info'][$loop->index % 4] }}"
                    href="{{ route('modules.show', ['module' => 'announcements', 'id' => $announcement->id]) }}">
                    <span class="family-notice-icon"><i class="bi bi-megaphone" aria-hidden="true"></i></span>
                    <div>
                        <h3>{{ $announcement->title }}</h3>
                        <p>{{ \Illuminate\Support\Str::limit($announcement->body, 155) }}</p>
                        <small><i class="bi bi-calendar-event" aria-hidden="true"></i>
                            <time datetime="{{ $announcement->published_at->toDateString() }}">{{ $announcement->published_at->translatedFormat('M j, Y') }}</time>
                        </small>
                    </div>
                    <i class="bi bi-chevron-right family-notice-arrow" aria-hidden="true"></i>
                </a>
            </li>
        @endforeach
        @if(!$familyHolidayNotices && $familyAttendanceNotices->isEmpty() && $announcements->isEmpty())
            <li class="empty-state py-4">{{ __('No family announcements yet.') }}</li>
        @endif
    </ul>
</section>
