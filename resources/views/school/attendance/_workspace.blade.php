<div class="teacher-attendance-dashboard">
    <div class="teacher-attendance-main-grid">
        <div class="teacher-attendance-compact">
            @include('school.attendance._teacher-chart')
        </div>
        <section class="panel teacher-insight-card teacher-trend-card" aria-labelledby="teacher-trend-title">
            <header><h2 id="teacher-trend-title"><i class="bi bi-bar-chart" aria-hidden="true"></i>Attendance trend</h2><span class="teacher-period-pill">Last 7 days</span></header>
            <p class="teacher-card-caption">Present rate among recorded teachers</p>
            <div class="teacher-trend-plot" role="list" aria-label="Daily attendance rates for the last seven days">
                @foreach($insights['trend'] as $day)
                <div class="teacher-trend-day" role="listitem" aria-label="{{ $day['date'] }}: {{ $day['rate'] === null ? 'No records' : $day['rate'].'% present' }}">
                    <span class="teacher-trend-value">{{ $day['rate'] === null ? 'N/A' : $day['rate'].'%' }}</span>
                    <div class="teacher-trend-track"><span class="{{ $loop->last ? 'is-selected' : '' }}" style="height:{{ $day['rate'] ?? 0 }}%"></span></div>
                    <span class="teacher-trend-label">{{ $day['label'] }}</span>
                </div>
                @endforeach
            </div>
            <footer><i class="bi bi-info-circle" aria-hidden="true"></i> Empty days have no recorded attendance.</footer>
        </section>
    </div>
    <div class="teacher-attendance-detail-grid">
        <section class="panel teacher-insight-card" aria-labelledby="teacher-program-attendance-title">
            <header><h2 id="teacher-program-attendance-title"><i class="bi bi-layers" aria-hidden="true"></i>Attendance by program</h2></header>
            <div class="teacher-program-list">
                @forelse($insights['programs'] as $program)
                <div class="teacher-program-item">
                    <span class="teacher-program-icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
                    <div><div class="teacher-program-label"><strong>{{ $program['name'] === 'Japan' ? 'Japanese' : $program['name'] }}</strong><span>{{ $program['rate'] === null ? 'Pending' : $program['rate'].'%' }}</span></div>
                    <div class="teacher-program-track"><span style="width:{{ $program['rate'] ?? 0 }}%"></span></div><small>{{ $program['recorded'] }} / {{ $program['total'] }} recorded</small></div>
                </div>
                @empty<p class="teacher-card-caption">No programs match these filters.</p>@endforelse
            </div>
        </section>
        <section class="panel teacher-insight-card" aria-labelledby="teacher-recent-title">
            <header><h2 id="teacher-recent-title"><i class="bi bi-clock-history" aria-hidden="true"></i>Recent attendance</h2><a href="{{ $attendanceActionUrl ?? '#teacher-attendance-records' }}">View teachers <i class="bi bi-arrow-right" aria-hidden="true"></i></a></header>
            <p class="teacher-card-caption">Latest updates for the selected date</p>
            <div class="teacher-recent-list">
                @forelse($insights['recent'] as $record)
                <div class="teacher-recent-item">@include('school.partials.avatar', ['person' => $record->teacher])<div><strong>{{ $record->teacher->name }}</strong><small>{{ $record->program === 'Japan' ? 'Japanese' : ($record->program ?: 'Unassigned') }}</small></div><span class="teacher-attendance-badge is-{{ $record->status }}">{{ ucfirst($record->status) }}</span><time datetime="{{ $record->updated_at->toIso8601String() }}">{{ $record->updated_at->timezone(config('classrooms.timezone', 'Asia/Phnom_Penh'))->format('H:i') }}</time></div>
                @empty<div class="teacher-attendance-empty"><i class="bi bi-calendar2-check" aria-hidden="true"></i><strong>A fresh start for this day</strong><p>Mark a teacher's attendance to see updates here.</p><a href="{{ $attendanceActionUrl ?? '#teacher-attendance-records' }}">Mark attendance <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>@endforelse
            </div>
        </section>
        <section class="panel teacher-insight-card" aria-labelledby="teacher-attendance-actions-title">
            <header><h2 id="teacher-attendance-actions-title"><i class="bi bi-lightning-charge" aria-hidden="true"></i>Quick actions</h2></header>
            <div class="teacher-attendance-actions">
                <a href="{{ $attendanceActionUrl ?? '#teacher-attendance-records' }}"><i class="bi bi-calendar2-check" aria-hidden="true"></i><span>Mark<br>attendance</span></a>
                <a href="{{ route('teacher-management.create') }}"><i class="bi bi-person-plus" aria-hidden="true"></i><span>Add<br>teacher</span></a>
                <a href="{{ route('teacher-management.reports') }}"><i class="bi bi-bar-chart-line" aria-hidden="true"></i><span>Teacher<br>reports</span></a>
                <a href="{{ route('teacher-attendance.index', ['date' => $date]) }}"><i class="bi bi-journal-check" aria-hidden="true"></i><span>Attendance<br>records</span></a>
            </div>
        </section>
    </div>
</div>
