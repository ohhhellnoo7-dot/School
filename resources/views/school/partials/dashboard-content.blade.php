@if(auth()->user()->role === 'student')
    @include('school.partials.student-home')
@else
@php
    $role = auth()->user()->role;
    $childrenLabel = $role === 'parent' && $parentChildNames->count() === 1 ? $parentChildNames->first()."'s" : "My children's";
    $teacherLabel = match ($role) { 'administrator' => 'Total teachers', 'parent' => $childrenLabel.' teachers', default => 'My teachers' };
    $classLabel = match ($role) { 'administrator' => 'Total classes', 'parent' => $childrenLabel.' classes', default => 'My classes' };
    $subjectLabel = match ($role) { 'administrator' => 'Total subjects', 'parent' => $childrenLabel.' subjects', default => 'My subjects' };
    $studentLabel = match ($role) { 'administrator' => 'Total students', 'teacher' => 'Students in my classes', 'parent' => 'My children', default => 'My student profile' };
    $scope = match ($role) { 'administrator' => 'School-wide totals from the current database.', 'teacher' => 'These figures cover your assigned classes and subjects.', 'parent' => 'These figures cover your linked children and their classes.', default => 'These figures cover your linked student profile and class.' };
@endphp

@if($role !== 'administrator' && $studentCount === 0)
<div class="alert alert-info" role="status">No student records are linked to your account{{ $role === 'teacher' ? ' through a class or teaching assignment' : '' }}. Ask the school administrator to link your {{ $role === 'parent' ? 'children' : ($role === 'teacher' ? 'teaching assignments' : 'student profile') }}. These zero counts do not mean the school database is empty.</div>
@endif
<div class="page-heading dashboard-heading school-hero">
    <div><div class="eyebrow">WELCOME BACK &#128075;</div><h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->name }} <i class="bi bi-brightness-high text-warning" aria-hidden="true" style="font-size:22px"></i></h1><p>Here's what's happening at your school today.</p><span class="hero-school"><i class="bi bi-buildings" aria-hidden="true"></i> Greenfield Academy <span aria-hidden="true">&middot;</span> {{ __(ucfirst($role)) }} workspace</span></div>
    @if($role === 'administrator' && $reportDate->isToday())
        <details class="attendance-actions">
            <summary class="btn btn-primary"><i class="bi bi-check2-all me-2" aria-hidden="true"></i>Student attendance <i class="bi bi-chevron-down ms-2" aria-hidden="true"></i></summary>
            <div class="attendance-action-menu">
                <div class="attendance-menu-heading"><strong>{{ __('Today\'s attendance') }}</strong><span>{{ $reportDate->format('M d, Y') }} &middot; {{ $studentCount }} students</span></div>
                <div class="attendance-status-grid">
                    @foreach([['Present', $presentCount, 'present', 'check-circle'], ['Absent', $absentCount, 'absent', 'x-circle'], ['Late', $lateCount, 'late', 'clock']] as [$statusLabel, $statusCount, $statusClass, $statusIcon])
                        <div class="attendance-status-item attendance-status-{{ $statusClass }}"><i class="bi bi-{{ $statusIcon }}" aria-hidden="true"></i><strong>{{ $statusCount }}</strong><span>{{ $statusLabel }}</span></div>
                    @endforeach
                </div>
                <p class="attendance-recorded">{{ $attendanceCount }} recorded &middot; {{ max(0, $studentCount - $attendanceCount) }} unmarked @if($charts['attendance'][3]) &middot; {{ $charts['attendance'][3] }} excused @endif</p>
                <form method="post" action="{{ route('dashboard.attendance.all-present') }}">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2-all me-2" aria-hidden="true"></i>Mark all present today</button>
                </form>
                <small class="attendance-action-note">All students, across every class.</small>
                <form method="post" action="{{ route('dashboard.attendance.random') }}" class="attendance-random-form">
                    @csrf
                    <button type="submit" class="btn attendance-random-button w-100"><i class="bi bi-shuffle me-2" aria-hidden="true"></i>Random attendance</button>
                    <div class="attendance-ratio"><span>70% present</span><span>20% late</span><span>10% absent</span></div>
                </form>
                <small class="attendance-action-note">Either action replaces today's statuses for all students. Random totals are rounded to whole students.</small>
            </div>
        </details>
    @endif
</div>

<div class="row g-3 dashboard-stat-grid">
    @foreach([[$studentLabel, $studentCount, 'people', 'green', 'Enrolled in your school community'], [$teacherLabel, $teacherCount, 'person-workspace', 'blue', 'Inspiring the next generation'], [$classLabel, $classCount, 'building', 'orange', 'Spaces for growing minds'], [$subjectLabel, $subjectCount, 'book', 'purple', 'A world of learning opportunities']] as [$label, $value, $icon, $color, $note])
    <div class="col-6 col-xl-3"><div class="stat-card stat-card-{{ $color }}"><div class="stat-top"><span>{{ __($label) }}</span>
        @if(isset($programBreakdowns[$icon]))
            <div class="stat-breakdown">
                <button type="button" class="stat-icon tint-{{ $color }} stat-breakdown-trigger" aria-label="{{ __($label) }} by program" aria-describedby="breakdown-{{ $icon }}"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></button>
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
            <span class="stat-icon tint-{{ $color }}"><i class="bi bi-{{ $icon }}"></i></span>
        @endif
    </div><div class="stat-value">{{ number_format($value) }}</div><div class="stat-note"><i class="bi bi-arrow-up-right"></i>{{ $note }}</div></div></div>
    @endforeach
</div>
<p class="dashboard-scope">{{ $scope }}</p>
<div class="mini-stats">
    <div class="mini-stat"><i class="bi bi-calendar2-check"></i><div><small>Present students</small><strong>{{ $presentCount }} <span class="fw-normal text-secondary" style="font-size:10px">/ {{ $attendanceCount }} recorded</span></strong></div></div>
    @if(in_array(auth()->user()->role, ['administrator', 'parent']))
    <div class="mini-stat"><i class="bi bi-cash-stack"></i><div><small>Fees collected · USD</small><strong>${{ number_format($collected, 2) }}</strong></div></div>
    <div class="mini-stat"><i class="bi bi-hourglass-split"></i><div><small>Pending payments · USD</small><strong>${{ number_format($pending, 2) }}</strong></div></div>
    @else
    <div class="mini-stat"><i class="bi bi-journal-text"></i><div><small>Learning resources</small><a href="{{ route('modules.index', 'assignments') }}">View assignments <i class="bi bi-arrow-up-right"></i></a></div></div>
    <div class="mini-stat"><i class="bi bi-clock"></i><div><small>Your school week</small><a href="{{ route('modules.index', 'schedules') }}">View timetable <i class="bi bi-arrow-up-right"></i></a></div></div>
    @endif
    <div class="mini-stat"><i class="bi bi-person-dash"></i><div><small>Absent students</small><strong>{{ $absentCount }}</strong></div></div>
</div>
<section class="attendance-toolbar" aria-label="Daily attendance report filters">
    <div class="attendance-toolbar-title">
        <span class="attendance-toolbar-icon"><i class="bi bi-calendar2-week" aria-hidden="true"></i></span>
        <div><h2>Daily attendance</h2><p>{{ $reportDate->format('l, F j, Y') }} <span class="attendance-date-badge">{{ $reportDate->isToday() ? 'Today' : ($reportDate->isYesterday() ? 'Yesterday' : 'History') }}</span></p></div>
    </div>
    <form method="get" action="{{ route('dashboard') }}" class="attendance-filters">
        <div class="attendance-shortcuts" aria-label="Quick report dates">
            <a class="{{ $reportDate->isYesterday() ? 'is-selected' : '' }}" href="{{ route('dashboard', ['date' => today()->subDay()->toDateString()]) }}" @if($reportDate->isYesterday()) aria-current="date" @endif>Yesterday</a>
            <a class="{{ $reportDate->isToday() ? 'is-selected' : '' }}" href="{{ route('dashboard') }}" @if($reportDate->isToday()) aria-current="date" @endif>Today</a>
        </div>
        <div class="attendance-date-field">
            <label for="attendance-report-date">Choose a date</label>
            <input type="date" id="attendance-report-date" name="date" value="{{ $reportDate->toDateString() }}" required>
        </div>
        <button type="submit" class="btn btn-primary attendance-view-button">View report <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></button>
    </form>
</section>
<div class="row g-3 mb-3">
    <div class="col-lg-8"><section class="panel h-100"><div class="panel-header"><div><h2>Student enrollment</h2><p>A growing community of bright minds</p></div><span class="date-pill py-1">Last 6 months <i class="bi bi-chevron-down ms-2"></i></span></div><div class="chart-wrap"><canvas id="enrollmentChart" role="img" aria-label="Monthly student enrollment chart"></canvas></div></section></div>
    <div class="col-lg-4"><section class="panel h-100"><div class="panel-header"><div><h2>Daily attendance report</h2><p>{{ $reportDate->format('l, M d, Y') }}</p></div><span class="stat-icon tint-green"><i class="bi bi-check2-circle"></i></span></div><div class="chart-wrap small attendance-donut"><div class="donut-label"><strong>{{ $attendanceCount ? round($presentCount / $attendanceCount * 100) : 0 }}%</strong><span>Present</span></div><canvas id="attendanceChart" role="img" aria-label="Attendance by status for {{ $reportDate->format('M d, Y') }}"></canvas></div><div class="chart-legend"><span><b>{{ $charts['attendance'][0] }}</b> Present</span><span><b>{{ $charts['attendance'][1] }}</b> Absent</span><span><b>{{ $lateCount }}</b> Late</span><span><b>{{ $charts['attendance'][3] }}</b> Excused</span></div>@if($attendanceCount === 0)<p class="text-secondary text-center small mt-2">No attendance recorded for this date.</p>@endif</section></div>
</div>
<div class="row g-3 mb-3">
    <div class="col-lg-8"><section class="panel h-100"><div class="panel-header"><div><h2>{{ in_array(auth()->user()->role, ['administrator', 'parent']) ? 'Monthly revenue' : 'Enrollment trend' }}</h2><p>{{ in_array(auth()->user()->role, ['administrator', 'parent']) ? 'Recorded fee payments · USD' : 'New students joining your classes' }}</p></div><div class="chart-key"><span>{{ in_array(auth()->user()->role, ['administrator', 'parent']) ? 'Collected fees' : 'New students' }}</span></div></div><div class="chart-wrap"><canvas id="revenueChart" role="img" aria-label="Monthly trend chart"></canvas></div></section></div>
    <div class="col-lg-4"><section class="panel h-100"><div class="panel-header"><div><h2>Students by gender</h2><p>Different perspectives. Shared possibilities.</p></div></div><div class="chart-wrap small"><canvas id="genderChart" role="img" aria-label="Student distribution by gender"></canvas></div><div class="chart-legend"><span><b>{{ $charts['gender'][0] }}</b> {{ __('Female') }}</span><span><b>{{ $charts['gender'][1] }}</b> {{ __('Male') }}</span><span><b>{{ $charts['gender'][2] }}</b> {{ __('Other') }}</span></div></section></div>
</div>
<div class="row g-3">
    <div class="col-lg-8"><section class="panel h-100"><div class="panel-header"><div><h2>Recent registrations</h2><p>The newest faces in our community</p></div><a class="text-link" href="{{ route('students.index') }}">View all students <i class="bi bi-arrow-right ms-1"></i></a></div><div class="table-responsive"><table class="table"><thead><tr><th>Student name</th><th>{{ __('Student ID') }}</th><th>{{ __('Class') }}</th><th>Enrolled</th><th>{{ __('Status') }}</th></tr></thead><tbody>@forelse($recent as $student)<tr><td><a href="{{ route('students.show', $student) }}">@include('school.partials.avatar', ['person' => $student])<strong>{{ $student->name }}</strong></a></td><td>{{ $student->student_number }}</td><td>{{ $student->schoolClass->name }} · {{ $student->schoolClass->section }}</td><td>{{ $student->enrollment_date->format('M d, Y') }}</td><td><span class="status-badge {{ $student->status }}">{{ __(ucfirst($student->status)) }}</span></td></tr>@empty<tr><td colspan="5" class="empty-state">No student records yet. {{ auth()->user()->role === 'administrator' ? 'Enroll your first student to get started.' : 'Ask the school office to link your account.' }}</td></tr>@endforelse</tbody></table></div>
    @if(auth()->user()->role === 'administrator')<div class="p-3 border-top"><a href="{{ route('students.create') }}" class="text-link"><i class="bi bi-plus-lg me-1"></i> Enroll a new student</a></div>@endif</section></div>
    <div class="col-lg-4"><section class="panel h-100"><div class="panel-header"><div><h2>School announcements</h2><p>Stay in the loop</p></div><a href="{{ route('modules.index', 'announcements') }}" aria-label="View announcements"><i class="bi bi-arrow-up-right"></i></a></div><div class="panel-body pt-0">@forelse($announcements as $announcement)<article class="announcement"><span class="announcement-icon"><i class="bi bi-megaphone"></i></span><div><h3>{{ $announcement->title }}</h3><p>{{ \Illuminate\Support\Str::limit($announcement->body, 110) }}</p><small><i class="bi bi-clock me-1"></i>{{ $announcement->published_at->format('M d, Y') }}</small></div></article>@empty<div class="empty-state py-4">You're all caught up. No announcements yet.</div>@endforelse</div></section></div>
</div>

@endif
