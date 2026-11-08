@php
    $chartValues = collect(['present','absent','late','excused'])->map(fn ($status) => (int) ($counts[$status] ?? 0));
    $recorded = (int) $counts->sum();
    $unmarked = max(0, $total - $recorded);
    $completion = $total ? round($recorded / $total * 100) : 0;
    $presentRate = $recorded ? round(($counts['present'] ?? 0) / $recorded * 100) : 0;
@endphp
<section class="panel attendance-insight mb-4" aria-labelledby="attendance-insight-title">
    <header class="attendance-insight-header">
        <div><span class="attendance-eyebrow"><i class="bi bi-activity" aria-hidden="true"></i> Daily overview</span><h2 id="attendance-insight-title">{{ $attendanceTitle ?? 'Overall teacher attendance' }}</h2><p>{{ \Carbon\Carbon::parse($date)->format('l, d F Y') }} &middot; {{ ($filters['program'] ?? null) ?: 'All programs' }}</p></div>
        <span class="attendance-date-badge"><i class="bi bi-calendar-check" aria-hidden="true"></i> {{ $recorded }} / {{ $total }} recorded</span>
    </header>
    <div class="attendance-insight-grid">
        <div class="attendance-ring-area">
            <div class="attendance-ring">
                <canvas id="{{ $attendanceChartId ?? 'teacherOverallChart' }}" data-teacher-values='@json($chartValues)' role="img" aria-label="{{ $presentRate }} percent present among {{ $recorded }} recorded teachers"></canvas>
                <div class="attendance-ring-label"><strong>{{ $recorded ? $presentRate.'%' : 'Pending' }}</strong><span>{{ $recorded ? 'Present rate' : 'Awaiting attendance' }}</span></div>
            </div>
            <small>Based on recorded attendance</small>
        </div>
        <div class="attendance-status-grid">
            @foreach(['present' => ['Present', 'check-circle'], 'absent' => ['Absent', 'x-circle'], 'late' => ['Late', 'clock'], 'excused' => ['Excused', 'shield-check']] as $status => [$label, $icon])
            <article class="attendance-status-tile attendance-status-{{ $status }}"><span class="attendance-status-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><div><span>{{ $label }}</span><strong>{{ $counts[$status] ?? 0 }}</strong></div><small>{{ $recorded ? round(($counts[$status] ?? 0) / $recorded * 100) : 0 }}%</small></article>
            @endforeach
        </div>
        <aside class="attendance-progress-card">
            <span class="attendance-eyebrow">Recording progress</span>
            <strong class="attendance-completion">{{ $completion }}<small>%</small></strong>
            <div class="attendance-progress-track" role="progressbar" aria-label="Attendance recording progress" aria-valuenow="{{ $completion }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $completion }}%"></span></div>
            <p><strong>{{ $unmarked }}</strong> {{ $unmarked === 1 ? 'teacher' : 'teachers' }} still unmarked</p>
            <small>{{ !$total ? 'No teachers match these filters.' : ($unmarked ? 'Complete the daily picture by recording the remaining teachers.' : 'All teachers in this view have attendance recorded.') }}</small>
            @if(auth()->user()->role === 'administrator' && $total && $date <= today()->toDateString())<a href="{{ $attendanceActionUrl ?? '#teacher-attendance-records' }}" class="btn btn-primary">{{ $unmarked ? 'Mark attendance' : 'Review attendance' }} <i class="bi bi-arrow-down" aria-hidden="true"></i></a>@endif
        </aside>
    </div>
    <footer class="attendance-insight-footer"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ $recorded ? 'Status percentages exclude unmarked teachers.' : 'No attendance recorded yet. Unmarked teachers are not counted as absent.' }} {{ $attendanceScope ?? 'Results reflect the selected date, program and teacher filters.' }}</footer>
</section>
