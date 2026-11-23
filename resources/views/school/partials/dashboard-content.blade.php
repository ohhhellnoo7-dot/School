@if(auth()->user()->role === 'student')
    @include('school.partials.student-home')
@else
@php
    $role = auth()->user()->role;
    $isTeacher = $role === 'teacher';
    $isParent = $role === 'parent';
    $childrenLabel = $role === 'parent' && $parentChildNames->count() === 1 ? $parentChildNames->first()."'s" : "My children's";
    $teacherLabel = match ($role) { 'administrator' => 'Total teachers', 'parent' => $childrenLabel.' teachers', default => 'My teachers' };
    $classLabel = match ($role) { 'administrator' => 'Total classes', 'parent' => $childrenLabel.' classes', default => 'My classes' };
    $subjectLabel = match ($role) { 'administrator' => 'Total subjects', 'parent' => $childrenLabel.' subjects', default => 'My subjects' };
    $studentLabel = match ($role) { 'administrator' => 'Total students', 'teacher' => 'Students in my classes', 'parent' => 'My children', default => 'My student profile' };
    $scope = match ($role) { 'administrator', 'teacher' => null, 'parent' => 'These figures cover your linked children and their classes.', default => 'These figures cover your linked student profile and class.' };
    $weeklyQuery = array_filter($monthlyAttendance['filters']);
    if ($isTeacher && $attendanceScope === 'children') {
        $weeklyQuery['attendance_scope'] = 'children';
    }
    $monthlyStatusCounts = collect(\App\Services\MonthlyStudentAttendance::STATUSES)
        ->mapWithKeys(fn ($status) => [$status => $monthlyAttendance['days']->sum("counts.$status")]);
    $monthlyRecordCount = $monthlyStatusCounts->sum();
@endphp

@if($role !== 'administrator' && $studentCount === 0)
<div class="alert alert-info" role="status">No student records are linked to your account{{ $role === 'teacher' ? ' through a class or teaching assignment' : '' }}. Ask the school administrator to link your {{ $role === 'parent' ? 'children' : ($role === 'teacher' ? 'teaching assignments' : 'student profile') }}. These zero counts do not mean the school database is empty.</div>
@endif
@if($isTeacher)
<x-scholar-hero tag="div" class="page-heading dashboard-heading school-hero windy-original">
    <div>
        <h1>Good {{ now('Asia/Phnom_Penh')->hour < 12 ? 'morning' : (now('Asia/Phnom_Penh')->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }} <span aria-hidden="true">&#128075;</span></h1>
        <a class="btn btn-outline-secondary mt-2" href="{{ route('modules.index', 'assignments') }}">{{ __('View assignments') }}</a>
        <a class="btn btn-outline-secondary mt-2" href="{{ route('students.index', ['my_children' => 1]) }}"><i class="bi bi-person-heart me-2" aria-hidden="true"></i>{{ __('My children') }}</a>
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
    <div class="teacher-dashboard-actions d-flex flex-wrap gap-2">
        <a class="btn btn-light" href="{{ route('modules.index', 'assignments') }}"><i class="bi bi-journal-bookmark me-2" aria-hidden="true"></i>{{ __('View assignments') }}</a>
        <a class="btn btn-light" href="{{ route('teachers.schedule', auth()->id()) }}"><i class="bi bi-calendar-week me-2" aria-hidden="true"></i>{{ __('My teaching schedule') }}</a>
        <a class="btn btn-light" href="{{ route('students.index', ['my_children' => 1]) }}"><i class="bi bi-person-heart me-2" aria-hidden="true"></i>{{ __('My children') }}</a>
    </div>
</section>
@else
<x-scholar-hero tag="div" class="page-heading dashboard-heading school-hero">
    <div class="dashboard-welcome"><h1><span class="windy-original">Good {{ now('Asia/Phnom_Penh')->hour < 12 ? 'morning' : (now('Asia/Phnom_Penh')->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }} <span aria-hidden="true">&#128075;</span></span><span class="windy-only">Welcome back, {{ auth()->user()->name }}</span></h1>
            @if($isParent)
                <p>{{ __('See attendance, classes, results, and fees for your linked children.') }}</p>
            @else
                <p><span class="windy-original">Here's what's happening at Greenfield Academy today.</span><span class="windy-only">Here's what's happening at your school today.</span></p>
            @endif
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

@if(!$isParent || $studentCount > 0)
<div class="row g-3 dashboard-metrics">
    @foreach([[$studentLabel, $studentCount, 'people', '🎓'], [$teacherLabel, $teacherCount, 'person-workspace', '👨‍🏫'], [$classLabel, $classCount, 'building', '🏫'], ['My children', $staffChildCount, 'family', "\u{1F46A}"], [$subjectLabel, $subjectCount, 'book', '📚']] as [$label, $value, $icon, $illustration])
    @continue(($isTeacher && $icon === 'person-workspace') || ($icon === 'family' && !$staffChildCount))
    <div class="{{ $staffChildCount ? ($isTeacher ? 'col-12 col-md-6 col-xl-3' : 'col-6 col-md-4 col-xl') : ($isTeacher ? 'col-12 col-md-4' : 'col-6 col-md-3') }}"><div class="dashboard-metric {{ $isTeacher ? 'teacher-dashboard-metric' : '' }}">
        @if($isTeacher)
            <span class="dashboard-metric-icon"><span aria-hidden="true">{{ $illustration }}</span></span>
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
@if($isParent && $dashboardChildren->isNotEmpty())
    @include('school.partials.dashboard-children')
@endif
@if($role === 'administrator')
<div class="mini-stats">
    <div class="mini-stat"><i class="bi bi-calendar2-check"></i><div><small>Present students</small><strong>{{ $presentCount }} <span class="fw-normal text-secondary" style="font-size:10px">/ {{ $attendanceCount }} recorded</span></strong></div></div>
    <div class="mini-stat"><i class="bi bi-cash-stack"></i><div><small>Fees collected · USD</small><strong>${{ number_format($collected, 2) }}</strong></div></div>
    <div class="mini-stat"><i class="bi bi-hourglass-split"></i><div><small>Pending payments · USD</small><strong>${{ number_format($pending, 2) }}</strong></div></div>
    <div class="mini-stat"><i class="bi bi-person-dash"></i><div><small>Absent students</small><strong>{{ $absentCount }}</strong></div></div>
</div>
@endif
@if(!$isParent || $studentCount > 0)
@unless($isParent)
<section class="attendance-toolbar" aria-label="Daily attendance report filters">
    <div class="attendance-toolbar-title">
        <span class="attendance-toolbar-icon"><i class="bi bi-calendar2-week" aria-hidden="true"></i></span>
        <div><h2>Daily attendance</h2><p>{{ $reportDate->format('l, F j, Y') }} <span class="attendance-date-badge">{{ $reportDate->isToday() ? 'Today' : ($reportDate->isYesterday() ? 'Yesterday' : 'History') }}</span></p></div>
    </div>
    <form method="get" action="{{ route('dashboard') }}" class="attendance-filters">
        @foreach($weeklyQuery as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        <div class="attendance-shortcuts" aria-label="Quick report dates">
            <a class="{{ $reportDate->isYesterday() ? 'is-selected' : '' }}" href="{{ route('dashboard', ['date' => today()->subDay()->toDateString(), 'session' => $attendanceSession] + $weeklyQuery) }}" @if($reportDate->isYesterday()) aria-current="date" @endif>Yesterday</a>
            <a class="{{ $reportDate->isToday() ? 'is-selected' : '' }}" href="{{ route('dashboard', array_filter(['session' => $attendanceSession]) + $weeklyQuery) }}" @if($reportDate->isToday()) aria-current="date" @endif>Today</a>
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
@endunless
<div class="row g-3 dashboard-feature-row">
    @if($role === 'administrator')
    <div class="col-lg-8">@include('school.partials.dashboard-revenue')</div>
    @endif
    @if($isParent)
        <div class="col-12 col-lg-6">@include('school.partials.dashboard-payment-coverage')</div>
        <div class="col-12 col-lg-6">@include('school.partials.family-announcements')</div>
    @else
    <div class="{{ $isTeacher ? 'col-12 col-lg-6' : 'col-lg-4' }}"><section class="panel h-100"><div class="panel-header"><div><h2>Student attendance report</h2><p>{{ $reportDate->format('l, M d, Y') }} &middot; {{ $attendanceSession ?: 'All sessions' }}</p></div><span class="stat-icon tint-green"><i class="bi bi-check2-circle"></i></span></div><div class="chart-wrap small attendance-donut"><div class="donut-label"><strong>{{ $attendanceCount ? round($presentCount / $attendanceCount * 100) : 0 }}%</strong><span>Present</span></div><canvas id="attendanceChart" role="img" aria-label="Attendance by status for {{ $reportDate->format('M d, Y') }}"></canvas></div><div class="chart-legend">@foreach(['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'excused' => 'Excused'] as $status => $label)<a class="text-reset" href="{{ route('attendance.people', array_filter(['kind' => 'students', 'status' => $status, 'date' => $reportDate->toDateString(), 'session' => $attendanceSession])) }}"><span><b>{{ $charts['attendance'][array_search($status, ['present', 'absent', 'late', 'excused'])] }}</b> {{ $label }}</span></a>@endforeach</div>@if($attendanceCount === 0)<p class="text-secondary text-center small mt-2">{{ ($studentReportHoliday = app(\App\Services\SchoolHolidayClosure::class)->holiday($reportDate)) ? __('School holiday. No attendance required.') : __('No attendance recorded for this date.') }}</p>@endif</section></div>
    @endif
@unless($isTeacher)
</div>
@endunless
@endif
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
@if($role === 'administrator')
<div class="row g-3 dashboard-feature-row">
    <div class="col-lg-8">@include('school.partials.monthly-attendance-trend')</div>
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
        </div>
        <section class="dashboard-monthly-breakdown" aria-labelledby="monthly-breakdown-title">
            <header>
                <div>
                    <h3 id="monthly-breakdown-title">{{ __('Monthly attendance breakdown') }}</h3>
                    <p>{{ $monthlyAttendance['monthStart']->translatedFormat('F Y') }}</p>
                </div>
                <span class="dashboard-monthly-records">{{ trans_choice(':count record|:count records', $monthlyRecordCount, ['count' => number_format($monthlyRecordCount)]) }}</span>
            </header>
            @if($monthlyRecordCount)
                <dl>
                    @foreach($monthlyStatusCounts as $status => $count)
                        @php($share = round(100 * $count / $monthlyRecordCount, 1))
                        <div class="dashboard-monthly-status" data-status="{{ $status }}">
                            <dt>{{ __(ucfirst($status)) }}</dt>
                            <dd><strong>{{ number_format($count) }}</strong><span>{{ number_format($share, 1) }}%</span></dd>
                            <dd class="dashboard-monthly-status-track" aria-hidden="true">
                                <span style="width: {{ $share }}%"></span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="dashboard-monthly-empty">{{ __('No attendance records for the selected filters.') }}</p>
            @endif
        </section>
    </section></div>
</div>
@endif
@unless($isParent)
@unless($isTeacher)
<div class="row g-3 dashboard-feature-row">
    <div class="col-lg-8"><section class="panel h-100"><div class="panel-header"><div class="dashboard-panel-title"><span aria-hidden="true">&#128203;</span><div><h2>Recent registrations</h2><p>The newest faces in our community</p></div></div><a class="text-link" href="{{ route('students.index') }}">View all students <i class="bi bi-arrow-right ms-1"></i></a></div><div class="table-responsive"><table class="table"><thead><tr><th>Student name</th><th>{{ __('Student ID') }}</th><th>{{ __('Class') }}</th><th>Enrolled</th><th>{{ __('Status') }}</th></tr></thead><tbody>@forelse($recent as $student)<tr><td><a href="{{ route('students.show', $student) }}">@include('school.partials.avatar', ['person' => $student])<strong>{{ $student->name }}</strong></a></td><td>{{ $student->student_number }}</td><td>{{ $student->schoolClass->name }} · {{ $student->schoolClass->section }}</td><td>{{ $student->enrollment_date->format('M d, Y') }}</td><td><span class="status-badge {{ $student->status }}">{{ __(ucfirst($student->status)) }}</span></td></tr>@empty<tr><td colspan="5" class="empty-state">No student records yet. {{ auth()->user()->role === 'administrator' ? 'Enroll your first student to get started.' : 'Ask the school office to link your account.' }}</td></tr>@endforelse</tbody></table></div>
    @if(auth()->user()->role === 'administrator')<div class="p-3 border-top"><a href="{{ route('students.create') }}" class="text-link"><i class="bi bi-plus-lg me-1"></i> Enroll a new student</a></div>@endif
    @include('school.partials.khmer-calendar')
    </section></div>
    @endunless
    @if($isTeacher)
        <div class="col-12 col-lg-6">
            <section class="panel h-100" id="student-attendance-updates" aria-labelledby="student-attendance-updates-title">
                <div class="panel-header">
                    <div class="dashboard-panel-title">
                        <span aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
                        <div>
                            <h2 id="student-attendance-updates-title">{{ __('Student attendance updates') }}</h2>
                            <p>{{ $attendanceWeekStart->translatedFormat('M j') }} &ndash; {{ $attendanceWeekStart->copy()->addDays(4)->translatedFormat('M j, Y') }}</p>
                        </div>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('attendance.student-history', ['attendance_scope' => $attendanceScope]) }}">{{ __('Attendance history') }}</a>
                </div>
                <div class="panel-body pt-0">
                    @include('school.attendance._message-scope', ['scopeRoute' => 'dashboard'])
                    @include('school.attendance._student-messages', ['records' => $attendanceMessages, 'live' => true])
                </div>
            </section>
        </div>
    @else
    <div class="col-lg-4">@include('school.partials.dashboard-announcements')</div>
    @endif
</div>

@endunless
@if($isTeacher)
    <div class="mt-3">@include('school.partials.monthly-attendance-trend')</div>
@endif

@endif
