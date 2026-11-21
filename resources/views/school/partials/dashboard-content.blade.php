@if(auth()->user()->role === 'student')
    @include('school.partials.student-home')
@else
@php
    $role = auth()->user()->role;
    $isTeacher = $role === 'teacher';
    $childrenLabel = $role === 'parent' && $parentChildNames->count() === 1 ? $parentChildNames->first()."'s" : "My children's";
    $teacherLabel = match ($role) { 'administrator' => 'Total teachers', 'parent' => $childrenLabel.' teachers', default => 'My teachers' };
    $classLabel = match ($role) { 'administrator' => 'Total classes', 'parent' => $childrenLabel.' classes', default => 'My classes' };
    $subjectLabel = match ($role) { 'administrator' => 'Total subjects', 'parent' => $childrenLabel.' subjects', default => 'My subjects' };
    $studentLabel = match ($role) { 'administrator' => 'Total students', 'teacher' => 'Students in my classes', 'parent' => 'My children', default => 'My student profile' };
    $scope = match ($role) { 'administrator', 'teacher' => null, 'parent' => 'These figures cover your linked children and their classes.', default => 'These figures cover your linked student profile and class.' };
@endphp

@if($role !== 'administrator' && $studentCount === 0)
<div class="alert alert-info" role="status">No student records are linked to your account{{ $role === 'teacher' ? ' through a class or teaching assignment' : '' }}. Ask the school administrator to link your {{ $role === 'parent' ? 'children' : ($role === 'teacher' ? 'teaching assignments' : 'student profile') }}. These zero counts do not mean the school database is empty.</div>
@endif
@if($isTeacher)
<x-scholar-hero tag="div" class="page-heading dashboard-heading school-hero windy-original">
    <div>
        <h1>Good {{ now('Asia/Phnom_Penh')->hour < 12 ? 'morning' : (now('Asia/Phnom_Penh')->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }} <span aria-hidden="true">&#128075;</span></h1>
        <a class="btn btn-outline-secondary mt-2" href="{{ route('modules.index', 'assignments') }}">{{ __('View assignments') }}</a>
    </div>
</x-scholar-hero>
<section class="student-attendance-hero teacher-dashboard-hero windy-only" aria-labelledby="teacher-dashboard-heading">
    <div class="student-attendance-breadcrumb">{{ __('School workspace') }} <i class="bi bi-chevron-right" aria-hidden="true"></i> {{ __('Overview') }}</div>
    <div class="student-attendance-heading">
        <i class="bi bi-grid-1x2" aria-hidden="true"></i>
        <div>
            <h1 id="teacher-dashboard-heading">
                Good {{ now('Asia/Phnom_Penh')->hour < 12 ? 'morning' : (now('Asia/Phnom_Penh')->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }}
                <span class="teacher-greeting-emoji" aria-hidden="true">👋</span>
            </h1>
            <p>{{ __('Manage your teaching schedule and attendance.') }}</p>
        </div>
    </div>
    <a class="btn btn-light" href="{{ route('modules.index', 'assignments') }}"><i class="bi bi-journal-bookmark me-2" aria-hidden="true"></i>{{ __('View assignments') }}</a>
</section>
@else
<x-scholar-hero tag="div" class="page-heading dashboard-heading school-hero">
    @include('school.partials.windy-landscape', ['placement' => 'hero'])
    <div><h1><span class="windy-original">Good {{ now('Asia/Phnom_Penh')->hour < 12 ? 'morning' : (now('Asia/Phnom_Penh')->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }} <span aria-hidden="true">&#128075;</span></span><span class="windy-only">Welcome back, {{ auth()->user()->name }}</span></h1>
            <p><span class="windy-original">Here's what's happening at Greenfield Academy today.</span><span class="windy-only">Here's what's happening at your school today.</span></p>
    </div>
    @if($role === 'administrator' && $reportDate->isToday())
        <div class="dashboard-attendance-actions d-flex flex-wrap gap-2">
        <details class="attendance-actions">
            <summary class="btn btn-primary"><i class="bi bi-check2-all me-2" aria-hidden="true"></i>Teacher attendance <i class="bi bi-chevron-down ms-2" aria-hidden="true"></i></summary>
            <div class="attendance-action-menu">
                <div class="attendance-menu-heading"><strong>{{ __('Today\'s attendance') }}</strong><span>{{ $reportDate->format('M d, Y') }} &middot; {{ $activeTeacherCount }} active teachers</span></div>
                <p class="attendance-recorded">{{ $teacherRecordedCount }} recorded &middot; {{ max(0, $activeTeacherCount - $teacherRecordedCount) }} unmarked</p>
                <form method="post" action="{{ route('dashboard.teacher-attendance.bulk', 'all-present') }}">
                    @csrf
                    <label for="teacher-bulk-session" class="form-label">Session to mark present</label>
                    <select id="teacher-bulk-session" name="session" class="form-select mb-2">
                        <option value="">All sessions</option>
                        @foreach(\App\Support\AttendanceSession::OPTIONS as $sessionOption)
                        <option value="{{ $sessionOption }}" @selected($attendanceSession === $sessionOption)>{{ $sessionOption }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2-all me-2" aria-hidden="true"></i>Mark all present today</button>
                </form>
                <small class="attendance-action-note">Marks active teachers in the selected session. Existing scans keep their present or late status.</small>
                <form method="post" action="{{ route('dashboard.teacher-attendance.bulk', 'scan-in') }}" class="mt-3">
                    @csrf
                    <label for="teacher-scan-session" class="form-label">Scan in / out session</label>
                    <select id="teacher-scan-session" name="session" class="form-select mb-2">
                        <option value="">All eligible sessions</option>
                        @foreach(\App\Support\AttendanceSession::OPTIONS as $sessionOption)
                        <option value="{{ $sessionOption }}" @selected($attendanceSession === $sessionOption)>{{ $sessionOption }}</option>
                        @endforeach
                    </select>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">Record scan in</button>
                        <button type="submit" formaction="{{ route('dashboard.teacher-attendance.bulk', 'scan-out') }}" class="btn btn-outline-primary flex-fill">Record scan out</button>
                    </div>
                    <small class="attendance-action-note">Administrator entry for active teachers: scan in records the current time during a scheduled session. Scan out records the current time after a checked-in session ends. Existing times are kept. These entries do not verify location.</small>
                </form>
                <form method="post" action="{{ route('dashboard.teacher-attendance.bulk', 'random') }}" class="attendance-random-form">
                    @csrf
                    <input type="hidden" name="session" value="{{ $attendanceSession }}">
                    <button type="submit" class="btn attendance-random-button w-100"><i class="bi bi-shuffle me-2" aria-hidden="true"></i>Random attendance</button>
                    <div class="attendance-ratio"><span>70% present</span><span>15% late</span><span>10% absent</span><span>5% excused</span></div>
                </form>
                <small class="attendance-action-note">Mark all present preserves teachers' scan results and marks teachers who have not scanned as present. Random attendance replaces today's statuses for {{ $attendanceSession ? $attendanceSession.' active teachers' : 'all active teachers' }}. Random totals are rounded to whole teachers.</small>
            </div>
        </details>
        <details class="attendance-actions">
            <summary class="btn btn-primary"><i class="bi bi-check2-all me-2" aria-hidden="true"></i>Student attendance <i class="bi bi-chevron-down ms-2" aria-hidden="true"></i></summary>
            <div class="attendance-action-menu">
                <div class="attendance-menu-heading"><strong>{{ __('Today\'s attendance') }}</strong><span>{{ $reportDate->format('M d, Y') }} &middot; {{ $attendanceStudentCount }} students</span></div>
                <p class="attendance-recorded">{{ $attendanceCount }} recorded &middot; {{ max(0, $attendanceStudentCount - $attendanceCount) }} unmarked</p>
                <form method="post" action="{{ route('dashboard.attendance.all-present') }}">
                    @csrf
                    <label for="student-bulk-session" class="form-label">Session to mark present</label>
                    <select id="student-bulk-session" name="session" class="form-select mb-2">
                        <option value="">All sessions</option>
                        @foreach(\App\Support\AttendanceSession::OPTIONS as $sessionOption)
                        <option value="{{ $sessionOption }}" @selected($attendanceSession === $sessionOption)>{{ $sessionOption }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2-all me-2" aria-hidden="true"></i>Mark all present today</button>
                </form>
                <small class="attendance-action-note">Marks students enrolled in the selected session. Existing present and late records are preserved.</small>
                <form method="post" action="{{ route('dashboard.attendance.random') }}" class="attendance-random-form">
                    @csrf
                    <input type="hidden" name="session" value="{{ $attendanceSession }}">
                    <button type="submit" class="btn attendance-random-button w-100"><i class="bi bi-shuffle me-2" aria-hidden="true"></i>Random attendance</button>
                    <div class="attendance-ratio"><span>70% present</span><span>15% late</span><span>10% absent</span><span>5% excused</span></div>
                </form>
                <small class="attendance-action-note">Mark all present preserves existing present and late records. Random attendance replaces today's statuses for {{ $attendanceSession ? $attendanceSession.' students' : 'all students' }}. Random totals are rounded to whole students.</small>
            </div>
        </details>
        </div>
    @endif
</x-scholar-hero>
@endif

@if(in_array($role, ['administrator', 'teacher'], true))
    @include('school.partials.windy-dashboard-attendance')
@endif

<div class="row g-3 dashboard-metrics">
    @foreach([[$studentLabel, $studentCount, 'people', '🎓'], [$teacherLabel, $teacherCount, 'person-workspace', '👨‍🏫'], [$classLabel, $classCount, 'building', '🏫'], [$subjectLabel, $subjectCount, 'book', '📚']] as [$label, $value, $icon, $illustration])
    @continue($isTeacher && $icon === 'person-workspace')
    <div class="col-6 {{ $isTeacher ? 'col-md-4' : 'col-md-3' }}"><div class="dashboard-metric {{ $isTeacher ? 'teacher-dashboard-metric teacher-dashboard-metric--'.$icon : '' }}">
        @if($isTeacher)
            <span class="dashboard-metric-icon windy-only"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
            <span class="dashboard-metric-icon windy-original"><span aria-hidden="true">{{ $illustration }}</span></span>
        @elseif(isset($programBreakdowns[$icon]))
            <div class="stat-breakdown">
                <button type="button" class="dashboard-metric-icon stat-breakdown-trigger" aria-label="{{ __($label) }} by program" aria-describedby="breakdown-{{ $icon }}"><span aria-hidden="true">{{ $illustration }}</span></button>
                <div class="stat-breakdown-menu" id="breakdown-{{ $icon }}">
                    <div class="breakdown-heading"><span class="breakdown-eyebrow">PROGRAM BREAKDOWN</span><strong>{{ __($label) }} by program</strong><span class="breakdown-subtitle">Current school data</span></div>
                    <dl class="mb-0">
                        @foreach($programBreakdowns[$icon] as $program => $count)
                            @php($share = array_sum($programBreakdowns[$icon]) ? round($count / array_sum($programBreakdowns[$icon]) * 100) : 0)
                            <div class="breakdown-program breakdown-program-{{ strtolower($program) }}">
                                <dt><span class="breakdown-dot" aria-hidden="true"></span>{{ __($program) }}</dt>
                                <dd>{{ number_format($count) }}</dd>
                                <div class="breakdown-track" aria-hidden="true"><span style="width: {{ $share }}%"></span></div>
                            </div>
                        @endforeach
                    </dl>
                    @if(in_array($icon, ['building', 'book']))
                        <p class="small text-secondary border-top pt-2 mt-2 mb-0">{{ $icon === 'building' ? 'Unique grades and sections per program.' : 'Unique subject names per program.' }} Shared {{ $icon === 'building' ? 'classes' : 'subjects' }} count in each program.</p>
                    @endif
                </div>
            </div>
        @else
            <span class="dashboard-metric-icon"><span aria-hidden="true">{{ $illustration }}</span></span>
        @endif
    <div class="dashboard-metric-copy"><span>{{ __($label) }}</span><strong>{{ number_format($value) }}</strong></div></div></div>
    @endforeach
</div>
@if($scope)
<p class="dashboard-scope">{{ $scope }}</p>
@endif
@if($isTeacher)
    <div class="row g-3 dashboard-feature-row">
        <div class="col-xl-6">
            @include('school.partials.today-teaching-periods')
        </div>
        <div class="col-xl-6">
            <div class="panel h-100">
                @include('school.partials.khmer-calendar')
            </div>
        </div>
    </div>
@endif
@unless($isTeacher)
<div class="mini-stats">
    <div class="mini-stat"><i class="bi bi-calendar2-check"></i><div><small>Present students</small><strong>{{ $presentCount }} <span class="fw-normal text-secondary" style="font-size:10px">/ {{ $attendanceCount }} recorded</span></strong></div></div>
    <div class="mini-stat"><i class="bi bi-cash-stack"></i><div><small>Fees collected · USD</small><strong>${{ number_format($collected, 2) }}</strong></div></div>
    <div class="mini-stat"><i class="bi bi-hourglass-split"></i><div><small>Pending payments · USD</small><strong>${{ number_format($pending, 2) }}</strong></div></div>
    <div class="mini-stat"><i class="bi bi-person-dash"></i><div><small>Absent students</small><strong>{{ $absentCount }}</strong></div></div>
</div>
@endunless
<section class="attendance-toolbar" aria-label="Daily attendance report filters">
    <div class="attendance-toolbar-title">
        <span class="attendance-toolbar-icon"><i class="bi bi-calendar2-week" aria-hidden="true"></i></span>
        <div><h2>Daily attendance</h2><p>{{ $reportDate->format('l, F j, Y') }} <span class="attendance-date-badge">{{ $reportDate->isToday() ? 'Today' : ($reportDate->isYesterday() ? 'Yesterday' : 'History') }}</span></p></div>
    </div>
    <form method="get" action="{{ route('dashboard') }}" class="attendance-filters">
        <div class="attendance-shortcuts" aria-label="Quick report dates">
            <a class="{{ $reportDate->isYesterday() ? 'is-selected' : '' }}" href="{{ route('dashboard', ['date' => today()->subDay()->toDateString(), 'session' => $attendanceSession]) }}" @if($reportDate->isYesterday()) aria-current="date" @endif>Yesterday</a>
            <a class="{{ $reportDate->isToday() ? 'is-selected' : '' }}" href="{{ route('dashboard', array_filter(['session' => $attendanceSession])) }}" @if($reportDate->isToday()) aria-current="date" @endif>Today</a>
        </div>
        <div class="attendance-session-field">
            <label for="attendance-report-session">Session</label>
            <select id="attendance-report-session" name="session" class="form-select">
                <option value="">All sessions</option>
                @foreach(\App\Support\AttendanceSession::OPTIONS as $sessionOption)
                <option value="{{ $sessionOption }}" @selected($attendanceSession === $sessionOption)>{{ $sessionOption }}</option>
                @endforeach
            </select>
        </div>
        <div class="attendance-date-field">
            <label for="attendance-report-date">Choose a date</label>
            <input type="date" id="attendance-report-date" name="date" value="{{ $reportDate->toDateString() }}" required>
        </div>
        <button type="submit" class="btn btn-primary attendance-view-button">View report <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></button>
    </form>
</section>
<div class="row g-3 dashboard-feature-row">
    @unless($isTeacher)
    <div class="col-lg-8"><section class="panel h-100"><div class="panel-header"><div class="dashboard-panel-title"><span aria-hidden="true">&#129689;</span><div><h2>{{ in_array(auth()->user()->role, ['administrator', 'parent']) ? 'Monthly revenue' : 'Enrollment trend' }}</h2><p>{{ in_array(auth()->user()->role, ['administrator', 'parent']) ? 'Recorded fee payments · USD' : 'New students joining your classes' }}</p></div></div><div class="chart-key"><span>{{ in_array(auth()->user()->role, ['administrator', 'parent']) ? 'Collected fees' : 'New students' }}</span></div></div><div class="chart-wrap"><canvas id="revenueChart" role="img" aria-label="Monthly trend chart"></canvas></div></section></div>
    @endunless
    <div class="{{ $isTeacher ? 'col-12 col-lg-6' : 'col-lg-4' }}"><section class="panel h-100"><div class="panel-header"><div><h2>Student attendance report</h2><p>{{ $reportDate->format('l, M d, Y') }} &middot; {{ $attendanceSession ?: 'All sessions' }}</p></div><span class="stat-icon tint-green"><i class="bi bi-check2-circle"></i></span></div><div class="chart-wrap small attendance-donut"><div class="donut-label"><strong>{{ $attendanceCount ? round($presentCount / $attendanceCount * 100) : 0 }}%</strong><span>Present</span></div><canvas id="attendanceChart" role="img" aria-label="Attendance by status for {{ $reportDate->format('M d, Y') }}"></canvas></div><div class="chart-legend">@foreach(['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $status => $label)<a class="text-reset" href="{{ route('attendance.people', array_filter(['kind' => 'students', 'status' => $status, 'date' => $reportDate->toDateString(), 'session' => $attendanceSession])) }}"><span><b>{{ $charts['attendance'][array_search($status, ['present', 'absent', 'late', 'excused'])] }}</b> {{ $label }}</span></a>@endforeach</div>@if($attendanceCount === 0)<p class="text-secondary text-center small mt-2">No attendance recorded for this date.</p>@endif</section></div>
@unless($isTeacher)
</div>
@endunless
@if(auth()->user()->role === 'administrator')
<div class="mb-4">
@include('school.attendance._workspace', [
    'insights' => $dashboardAttendance['insights'],
    'counts' => $teacherAttendanceCounts,
    'total' => $teacherAttendanceTotal,
    'date' => $reportDate->toDateString(),
    'filters' => ['session' => $attendanceSession],
    'attendanceTitle' => 'Teacher attendance report',
    'attendanceChartId' => 'teacherAttendanceChart',
    'attendanceActionUrl' => route('modules.index', ['module' => 'teachers', 'attendance_date' => $reportDate->toDateString(), 'attendance_session' => $attendanceSession]).'#teacher-attendance-records',
    'attendanceScope' => ($attendanceSession ?: 'All sessions').' across all programs for the selected report date.',
])
</div>
@endif
@unless($isTeacher)
<div class="row g-3 dashboard-feature-row">
    <div class="col-lg-8"><section class="panel h-100"><div class="panel-header"><div><h2>Student enrollment</h2><p>A growing community of bright minds</p></div><span class="date-pill py-1">Last 6 months <i class="bi bi-chevron-down ms-2"></i></span></div><div class="chart-wrap"><canvas id="enrollmentChart" role="img" aria-label="Monthly student enrollment chart"></canvas></div></section></div>
    <div class="col-lg-4"><section class="panel h-100"><div class="panel-header"><div class="dashboard-panel-title"><span aria-hidden="true">&#128101;</span><div><h2>Students by gender</h2><p>Different perspectives. Shared possibilities.</p></div></div></div>@php($genderTotal = collect($charts['gender'])->sum())
        <div class="dashboard-gender">
            <div class="chart-wrap small">
                <div class="donut-label"><strong>{{ number_format($genderTotal) }}</strong><span>Students</span></div>
                <canvas id="genderChart" role="img" aria-label="Student distribution by gender"></canvas>
            </div>
            <dl class="dashboard-gender-legend">
                @foreach([1 => [__('Male'), 'bi-gender-male'], 0 => [__('Female'), 'bi-gender-female'], 2 => [__('Other'), null]] as $index => [$label, $icon])
                    <div class="dashboard-gender-{{ $index }}">
                        <dt>
                            <span class="dashboard-gender-marker" aria-hidden="true">
                                @if($icon)<i class="bi {{ $icon }}"></i>@endif
                            </span>
                            {{ $label }}
                        </dt>
                        <dd>{{ number_format($charts['gender'][$index]) }}</dd>
                        <dd>{{ number_format($genderTotal ? $charts['gender'][$index] / $genderTotal * 100 : 0, 1) }}%</dd>
                    </div>
                @endforeach
            </dl>
        </div></section></div>
</div>
@endunless
@unless($isTeacher)
<div class="row g-3 dashboard-feature-row">
    <div class="col-lg-8"><section class="panel h-100"><div class="panel-header"><div class="dashboard-panel-title"><span aria-hidden="true">&#128203;</span><div><h2>Recent registrations</h2><p>The newest faces in our community</p></div></div><a class="text-link" href="{{ route('students.index') }}">View all students <i class="bi bi-arrow-right ms-1"></i></a></div><div class="table-responsive"><table class="table"><thead><tr><th>Student name</th><th>{{ __('Student ID') }}</th><th>{{ __('Class') }}</th><th>Enrolled</th><th>{{ __('Status') }}</th></tr></thead><tbody>@forelse($recent as $student)<tr><td><a href="{{ route('students.show', $student) }}">@include('school.partials.avatar', ['person' => $student])<strong>{{ $student->name }}</strong></a></td><td>{{ $student->student_number }}</td><td>{{ $student->schoolClass->name }} · {{ $student->schoolClass->section }}</td><td>{{ $student->enrollment_date->format('M d, Y') }}</td><td><span class="status-badge {{ $student->status }}">{{ __(ucfirst($student->status)) }}</span></td></tr>@empty<tr><td colspan="5" class="empty-state">No student records yet. {{ auth()->user()->role === 'administrator' ? 'Enroll your first student to get started.' : 'Ask the school office to link your account.' }}</td></tr>@endforelse</tbody></table></div>
    @if(auth()->user()->role === 'administrator')<div class="p-3 border-top"><a href="{{ route('students.create') }}" class="text-link"><i class="bi bi-plus-lg me-1"></i> Enroll a new student</a></div>@endif
    @include('school.partials.khmer-calendar')
    </section></div>
    @endunless
    <div class="{{ $isTeacher ? 'col-12 col-lg-6' : 'col-lg-4' }}"><section class="panel h-100"><div class="panel-header"><div class="dashboard-panel-title"><span aria-hidden="true">&#128227;</span><div><h2>School announcements</h2><p>Stay in the loop</p></div></div><a href="{{ route('modules.index', 'announcements') }}" aria-label="View announcements"><i class="bi bi-arrow-up-right"></i></a></div><div class="panel-body pt-0">@forelse($announcements as $announcement)<article class="announcement"><span class="announcement-icon"><i class="bi bi-megaphone"></i></span><div><h3>{{ $announcement->title }}</h3><p>{{ \Illuminate\Support\Str::limit($announcement->body, 110) }}</p><small><i class="bi bi-clock me-1"></i>{{ $announcement->published_at->format('M d, Y') }}</small></div></article>@empty<div class="empty-state py-4">You're all caught up. No announcements yet.</div>@endforelse</div></section></div>
</div>


@endif
