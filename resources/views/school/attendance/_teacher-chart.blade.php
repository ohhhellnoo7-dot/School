@php
    $holiday = $holiday ?? null;
    $chartValues = collect(['present','absent','late','excused'])->map(fn ($status) => (int) ($counts[$status] ?? 0));
    $recorded = (int) $counts->sum();
    $unmarked = max(0, $total - $recorded);
    $completion = $total ? round($recorded / $total * 100) : 0;
    $presentRate = $recorded ? round(($counts['present'] ?? 0) / $recorded * 100) : 0;
@endphp
<section class="panel attendance-insight mb-4" aria-labelledby="attendance-insight-title">
    <header class="attendance-insight-header">
        <div class="attendance-insight-title-group">
            @if($dashboardOverview ?? false)<i class="bi bi-calendar2-check-fill attendance-overview-icon" aria-hidden="true"></i>@endif
            <div><span class="attendance-eyebrow"><i class="bi bi-activity" aria-hidden="true"></i> Daily overview</span><h2 id="attendance-insight-title">{{ $attendanceTitle ?? 'Overall teacher attendance' }}</h2><p>{{ \Carbon\Carbon::parse($date)->format('l, d F Y') }} &middot; {{ ($filters['program'] ?? null) ?: 'All programs' }} &middot; {{ ($filters['session'] ?? null) ?: 'All sessions' }}</p></div>
        </div>
        @if($holiday)
            <span class="attendance-date-badge"><i class="bi bi-umbrella-fill" aria-hidden="true"></i> {{ __('School holiday') }}</span>
        @else
        <span class="attendance-date-badge"><i class="bi bi-calendar-check" aria-hidden="true"></i> {{ $recorded }} / {{ $total }} recorded</span>
        @endif
    </header>
    @if($holiday)
        <p class="attendance-holiday-notice"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ app(\App\Services\SchoolHolidayClosure::class)->message($holiday) }}</p>
    @endif
    <div class="attendance-insight-grid">
        @if($holiday && ($dashboardOverview ?? false))
            <div class="attendance-holiday-display">
                <span class="attendance-holiday-art teacher-overview-art" aria-hidden="true"></span>
                <strong>{{ __('Holiday') }}</strong>
                <span>{{ __('No attendance required') }}</span>
            </div>
        @else
        <div class="attendance-ring-area">
            <div class="attendance-ring">
                <canvas id="{{ $attendanceChartId ?? 'teacherOverallChart' }}" data-teacher-values='@json($chartValues)' role="img" aria-label="{{ $holiday ? __('School holiday. Attendance is not required.') : "$presentRate percent present among $recorded recorded teachers" }}"></canvas>
                <div class="attendance-ring-label"><strong>{{ $holiday ? __('Holiday') : ($recorded ? $presentRate.'%' : 'Pending') }}</strong><span>{{ $holiday ? __('No attendance required') : ($recorded ? 'Present rate' : 'Awaiting attendance') }}</span></div>
            </div>
            @unless($holiday)<small>Based on recorded attendance</small>@endunless
        </div>
        @endif
        <div class="attendance-status-grid">
            @foreach(['present' => ['Present', 'check-circle'], 'absent' => ['Absent', 'x-circle'], 'late' => ['Late', 'clock'], 'excused' => ['Excused', 'shield-check']] as $status => [$label, $icon])
            <a href="{{ route('attendance.people', array_filter(['kind' => 'teachers', 'status' => $status, 'date' => $date, 'session' => $filters['session'] ?? null, 'program' => $filters['program'] ?? null, 'search' => $filters['search'] ?? null])) }}" aria-label="View {{ strtolower($label) }} teachers" class="attendance-status-tile attendance-status-{{ $status }}"><span class="attendance-status-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><div><span>{{ $label }}</span><strong>{{ $counts[$status] ?? 0 }}</strong></div><small>{{ $holiday ? __('N/A') : ($recorded ? round(($counts[$status] ?? 0) / $recorded * 100) : 0).'%' }}</small></a>
            @endforeach
            @if(auth()->user()->role === 'administrator')
                <a class="btn btn-sm btn-outline-secondary teacher-excuse-review-link" href="{{ route('teacher-excuses.index') }}">
                    <i class="bi bi-list-check me-1" aria-hidden="true"></i>{{ __('Review excuse requests') }}
                    @include('school.attendance._teacher-excuse-badge')
                </a>
            @endif
        </div>
        @unless($holiday)
        <aside class="attendance-progress-card">
            <span class="attendance-eyebrow">Recording progress</span>
            <strong class="attendance-completion">{{ $completion }}<small>%</small></strong>
            <div class="attendance-progress-track" role="progressbar" aria-label="Attendance recording progress" aria-valuenow="{{ $completion }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $completion }}%"></span></div>
            <p><strong>{{ $unmarked }}</strong> {{ $unmarked === 1 ? 'teacher' : 'teachers' }} still unmarked</p>
            <small>{{ !$total ? 'No teachers match these filters.' : ($unmarked ? 'Complete the daily picture by recording the remaining teachers.' : 'All teachers in this view have attendance recorded.') }}</small>
            @if(auth()->user()->role === 'administrator' && $total && $date <= today()->toDateString())<a href="{{ $attendanceActionUrl ?? '#teacher-attendance-records' }}" class="btn btn-primary">{{ $unmarked ? 'Mark attendance' : 'Review attendance' }} <i class="bi bi-arrow-down" aria-hidden="true"></i></a>@endif
        </aside>
        @endunless
    </div>
    <footer class="attendance-insight-footer d-flex align-items-center gap-2"><i class="bi bi-{{ $holiday ? 'bar-chart' : 'info-circle' }}" aria-hidden="true"></i> {{ $holiday ? __('View attendance for the whole month.') : ($recorded ? 'Status percentages exclude unmarked teachers.' : 'No attendance recorded yet. Unmarked teachers are not counted as absent.') }} @unless($holiday){{ $attendanceScope ?? 'Results reflect the selected date, program and teacher filters.' }}@endunless<a href="{{ route('teacher-attendance.monthly', array_filter(['month' => substr($date, 0, 7), 'program' => $filters['program'] ?? null, 'session' => $filters['session'] ?? null])) }}" class="btn btn-sm btn-outline-primary ms-auto" aria-label="View monthly teacher attendance" title="View monthly teacher attendance"><i class="bi bi-arrow-right" aria-hidden="true"></i></a></footer>
</section>
