<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Overview') · Scholar School</title>
    @if($myChildren ?? false)<link href="{{ asset('css/family-dashboard.css') }}?v={{ filemtime(public_path('css/family-dashboard.css')) }}" rel="stylesheet">@endif
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('css/school.css') }}?v={{ filemtime(public_path('css/school.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/dashboard-breakdowns.css') }}?v={{ filemtime(public_path('css/dashboard-breakdowns.css')) }}" rel="stylesheet">
@include('school.partials.theme-head')
@if(request()->routeIs('teacher-management.*', 'teachers.schedule') || request()->route('module') === 'teachers')<link href="{{ asset('css/teacher-management.css') }}?v={{ filemtime(public_path('css/teacher-management.css')) }}" rel="stylesheet">@endif
@if(request()->routeIs('modules.index') && request()->route('module') === 'parents')<link href="{{ asset('css/parent-directory.css') }}?v={{ filemtime(public_path('css/parent-directory.css')) }}" rel="stylesheet">@endif
    @if(request()->routeIs('students.index'))<link href="{{ asset('css/student-directory.css') }}?v={{ filemtime(public_path('css/student-directory.css')) }}" rel="stylesheet">@endif
    @if(request()->routeIs('classes.timetable*') || (request()->routeIs('modules.show') && request()->route('module') === 'classes'))<link href="{{ asset('css/timetable.css') }}?v={{ filemtime(public_path('css/timetable.css')) }}" rel="stylesheet">@endif
    @if(request()->routeIs('class-groups.*', 'classrooms.*', 'classroom-bookings.*'))<link href="{{ asset('css/classrooms.css') }}?v={{ filemtime(public_path('css/classrooms.css')) }}" rel="stylesheet">@endif

@if(request()->routeIs('modules.index') && in_array(request()->route('module'), ['teachers', 'classes', 'subjects']))<link href="{{ asset('css/teacher-directory.css') }}?v={{ filemtime(public_path('css/teacher-directory.css')) }}" rel="stylesheet">@endif
    <link href="{{ asset('css/workspace-polish.css') }}?v={{ filemtime(public_path('css/workspace-polish.css')) }}" rel="stylesheet">
    @if(request()->routeIs('class-groups.index'))<link href="{{ asset('css/class-groups-directory.css') }}?v={{ filemtime(public_path('css/class-groups-directory.css')) }}" rel="stylesheet">@endif
    @if(request()->routeIs('finance.*', 'reports') || request()->route('module') === 'fees')<link href="{{ asset('css/finance.css') }}?v={{ filemtime(public_path('css/finance.css')) }}" rel="stylesheet">@endif
    @if(request()->routeIs('employee-salaries.*'))<link href="{{ asset('css/employee-salaries.css') }}?v={{ filemtime(public_path('css/employee-salaries.css')) }}" rel="stylesheet">@endif
    <link href="{{ asset('css/workspace-refresh.css') }}?v={{ filemtime(public_path('css/workspace-refresh.css')) }}" rel="stylesheet">
    @if(request()->routeIs('modules.index') && request()->route('module') === 'subjects')<link href="{{ asset('css/subject-refresh.css') }}?v={{ filemtime(public_path('css/subject-refresh.css')) }}" rel="stylesheet">@endif
    <link href="{{ asset('css/scholar-hero.css') }}?v={{ filemtime(public_path('css/scholar-hero.css')) }}" rel="stylesheet">
    @if(auth()->user()->role === 'administrator' || request()->routeIs('students.index'))
        <link rel="stylesheet" href="{{ asset('css/student-excuses.css') }}?v={{ filemtime(public_path('css/student-excuses.css')) }}">
    @endif
    @stack('styles')
    @if(request()->routeIs('classrooms.show'))<link href="{{ asset('css/room-detail-refresh.css') }}?v={{ filemtime(public_path('css/room-detail-refresh.css')) }}" rel="stylesheet">@endif
    @if(request()->routeIs('classrooms.index'))<link href="{{ asset('css/classroom-refresh.css') }}?v={{ filemtime(public_path('css/classroom-refresh.css')) }}" rel="stylesheet">@endif
@if(request()->routeIs('dashboard', 'teacher-attendance.*') || request()->route('module') === 'teachers')
<link href="{{ asset('css/teacher-attendance.css') }}?v={{ filemtime(public_path('css/teacher-attendance.css')) }}" rel="stylesheet">
@endif
@if(request()->routeIs('students.index'))<link href="{{ asset('css/student-attendance-workspace.css') }}?v={{ filemtime(public_path('css/student-attendance-workspace.css')) }}" rel="stylesheet">@endif
@if(isset($paymentCoverage) || (request()->routeIs('dashboard') && auth()->user()->role === 'parent'))
    <link href="{{ asset('css/family-payment-coverage.css') }}?v={{ filemtime(public_path('css/family-payment-coverage.css')) }}" rel="stylesheet">
@endif
@if(request()->routeIs('dashboard'))
    <link href="{{ asset('css/dashboard-report.css') }}?v={{ filemtime(public_path('css/dashboard-report.css')) }}" rel="stylesheet">
    <link href="{{ asset('css/dashboard-campus.css') }}?v={{ filemtime(public_path('css/dashboard-campus.css')) }}" rel="stylesheet">
    @if(auth()->user()->role === 'student')
        <link href="{{ asset('css/student-workspace.css') }}?v={{ filemtime(public_path('css/student-workspace.css')) }}" rel="stylesheet">
    @endif
@endif
@php
    $studentAttendancePage = request()->routeIs('modules.index') && request()->route('module') === 'attendance';
    $parentChildAttendance = auth()->user()->role === 'parent' && request()->routeIs('students.family.show') && request('tab') === 'attendance';
    $teacherWorkspaceDesign = auth()->user()->role === 'teacher'
        && (request()->routeIs('dashboard') || $studentAttendancePage);
@endphp
@if($teacherWorkspaceDesign || $studentAttendancePage || request()->routeIs('dashboard'))
    <link href="{{ asset('css/student-attendance.css') }}?v={{ filemtime(public_path('css/student-attendance.css')) }}" rel="stylesheet">
@endif
<script src="{{ asset('js/hover-menus.js') }}?v={{ filemtime(public_path('js/hover-menus.js')) }}"></script>
<style>
html[data-hover-menus=off] .stat-breakdown:not(.is-open) .stat-breakdown-menu,
html[data-hover-menus=off] .building-picker:not(.is-open) .building-options,
html[data-hover-menus=off] .program-picker:not(.is-open) .program-options { display:none!important; }
.dashboard-metric .stat-breakdown.is-open .stat-breakdown-menu { display:block; }
a.attendance-status-tile { text-decoration:none; color:inherit; }
a.attendance-status-tile:hover, a.attendance-status-tile:focus-visible { outline:2px solid currentColor; outline-offset:2px; }
.topbar .teacher-scan-shortcut { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-width:42px; min-height:42px; flex-shrink:0; padding:9px 12px; border:1px solid var(--border-color); border-radius:12px; background:var(--primary); color:#fff; font-size:12px; font-weight:700; text-decoration:none; }
.topbar .teacher-scan-shortcut i { font-size:20px; }
.topbar .teacher-scan-shortcut:hover { background:var(--primary); color:#fff; filter:brightness(.92); border-color:var(--primary); }
.topbar .teacher-scan-shortcut:focus-visible { outline:3px solid var(--primary); outline-offset:3px; }
#attendance-check-in { scroll-margin-top:100px; }
</style>
</head>
<body class="scholar-workspace {{ auth()->user()->role === 'student' ? 'student-portal' : '' }} {{ $teacherWorkspaceDesign ? 'teacher-workspace-design' : '' }} {{ request()->routeIs('dashboard') ? 'dashboard-page' : '' }} {{ request()->routeIs('modules.index') && request()->route('module') === 'subjects' ? 'subjects-page' : '' }} {{ request()->routeIs('class-groups.*') ? 'classes-page' : '' }} {{ request()->routeIs('classrooms.*', 'classroom-bookings.*') ? 'classrooms-page' : '' }} {{ request()->routeIs('modules.index') && request()->route('module') === 'teachers' ? 'teachers-page' : '' }} {{ request()->routeIs('modules.index') && request()->route('module') === 'parents' ? 'parents-page' : '' }} {{ request()->routeIs('students.index') ? 'students-page' : '' }}">
<a href="#main-content" class="skip-link">{{ __('Skip to content') }}</a>
@include('school.partials.windy-breeze-air')
<aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="{{ __('Main navigation') }}">
    <span class="windy-sidebar-sun windy-only" aria-hidden="true"></span>
    <span class="windy-sidebar-cloud windy-only" aria-hidden="true"></span>
    <span class="windy-sidebar-cloud windy-sidebar-cloud--far windy-only" aria-hidden="true"></span>
    <button class="btn sidebar-close d-lg-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="{{ __('Close navigation') }}"><i class="bi bi-x-lg"></i></button>
    <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span><span class="brand-name">Scholar<small>{{ __('School Portal') }}</small></span><span class="brand-dot">.</span></a>

    <div class="nav-caption">{{ __('Main menu') }}</div>
    <nav class="school-nav school-nav--pinned" aria-label="{{ __('Dashboard') }}">
        <a class="{{ request()->routeIs('dashboard') && !request()->filled('section') ? 'active' : '' }}" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard') && !request()->filled('section')) aria-current="page" @endif><i class="bi bi-grid-1x2" aria-hidden="true"></i><span class="nav-label">{{ __('Dashboard') }}</span></a>
    </nav>
    <nav class="school-nav" data-school-nav-scroll aria-label="{{ __('School navigation') }}">
        @if(auth()->user()->role === 'student')
            @foreach(\App\Http\Controllers\StudentWorkspaceController::SECTIONS as $studentSection => [$label, $icon])
                @if($studentSection === 'coursework')<div class="nav-group-caption">{{ __('Academics') }}</div>@endif
                @php($activeSection = (request()->routeIs('dashboard') && request('section') === $studentSection)
                    || ($studentSection === 'timetable' && request()->routeIs('timetables.index', 'classes.timetable'))
                    || ($studentSection === 'fees' && request()->routeIs('fees.pay', 'finance.invoice', 'finance.receipt')))
                <a href="{{ route('dashboard', ['section' => $studentSection]) }}" @if($activeSection) class="active" aria-current="page" @endif>
                    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span class="nav-label">{{ __($label) }}</span>
                    @if($studentSection === 'announcements' && !empty($announcementCount))
                        <span class="an-nav-count" aria-label="{{ __(':count announcements this week', ['count' => $announcementCount]) }}">{{ $announcementCount }}</span>
                    @endif
                </a>
            @endforeach
            <a href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.edit')) class="active" aria-current="page" @endif>
                <i class="bi bi-gear" aria-hidden="true"></i><span class="nav-label">{{ __('Account settings') }}</span>
            </a>
        @else
        <a class="{{ request()->routeIs('timetables.*') ? 'active' : '' }}" href="{{ route('timetables.index') }}"><i class="bi bi-calendar-week"></i><span class="nav-label"> {{ auth()->user()->role === 'teacher' ? __('Teacher schedule') : __('School Timetables') }}</span></a>
        <a class="{{ !$parentChildAttendance && request()->routeIs('students.*') && (!($myChildren ?? false) || auth()->user()->role === 'parent') ? 'active' : '' }}" href="{{ route('students.index') }}"><i class="bi bi-people"></i><span class="nav-label"> {{ auth()->user()->role === 'parent' ? __('My children') : (auth()->user()->role === 'student' ? __('My profile') : __('Students')) }} @if(auth()->user()->role === 'administrator')<span class="student-excuse-badge" data-excuse-count data-count-url="{{ route('students.excuses.count') }}" title="{{ __('Excuse requests submitted today') }}" hidden></span>@endif</span></a>
        @if(in_array(auth()->user()->role, \App\Models\User::STAFF_ROLES, true) && auth()->user()->children()->exists())
            <a class="{{ ($myChildren ?? false) ? 'active' : '' }}" href="{{ route('students.index', ['my_children' => 1]) }}"><i class="bi bi-person-heart" aria-hidden="true"></i><span class="nav-label">{{ __('My children') }}</span></a>
        @endif
        <div class="nav-group-caption">{{ __('Academics') }}</div>
        @foreach(\App\Support\SchoolModules::all() as $key => $module)
            @if(\App\Support\SchoolModules::canView(auth()->user(), $key))
                <a class="{{ ($key === 'classes' ? request()->routeIs('class-groups.*') : (request()->route('module') === $key || ($key === 'attendance' && $parentChildAttendance) || ($key === 'fees' && request()->routeIs('finance.*')) || ($key === 'teachers' && request()->routeIs('teacher-management.*', 'teachers.schedule*', 'teacher-attendance.*')))) ? 'active' : '' }}" href="{{ $key === 'classes' ? route('class-groups.index') : route('modules.index', $key) }}"><i class="bi bi-{{ $module['icon'] }}"></i><span class="nav-label">{{ __($module['title']) }}</span></a>
            @endif
        @endforeach
<div class="nav-group-caption">{{ __('Workspace') }}</div>
        <a class="{{ request()->routeIs('classrooms.*', 'classroom-bookings.*') ? 'active' : '' }}" href="{{ route('classrooms.index') }}"><i class="bi bi-door-open"></i><span class="nav-label">{{ auth()->user()->role === 'parent' ? __('My Class') : __('Classroom availability') }}</span></a>
        @unless(auth()->user()->role === 'parent')
            <a class="{{ request()->routeIs('school.map') ? 'active' : '' }}" href="{{ route('school.map') }}"><i class="bi bi-geo-alt" aria-hidden="true"></i><span class="nav-label">{{ __('School map') }}</span></a>
        @endunless
        @if(auth()->user()->role === 'administrator')
            @php($pendingAdvanceCount = $pendingAdvanceCount ?? \App\Models\TeacherAdvanceRequest::pending()->count())
            <a class="{{ request()->routeIs('employee-salaries.*') ? 'active' : '' }}" href="{{ route('employee-salaries.index') }}">
                <i class="bi bi-cash-stack" aria-hidden="true"></i>
                <span class="nav-label">{{ __('Employee salary') }}</span>
            </a>
            <a class="{{ request()->routeIs('reports') ? 'active' : '' }}" href="{{ route('reports') }}">
                <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                <span class="nav-label">{{ __('Reports') }}</span>
                <span class="badge rounded-pill text-bg-danger ms-auto flex-shrink-0" data-expense-live-count="sidebar" @if($pendingAdvanceCount > 0) data-pending-advance-count @else hidden @endif><span class="visually-hidden">{{ __('Pending salary advance requests') }} </span>{{ $pendingAdvanceCount }}</span>
            </a>
        @endif
        @endif
    </nav>
    @include('school.partials.windy-landscape', ['placement' => 'sidebar'])
    <div class="school-switch windy-original"><span class="school-icon"><i class="bi bi-buildings"></i></span><div><strong>Greenfield Academy</strong><small>{{ __('School management portal') }}</small></div></div>
    <div class="sidebar-footer"><span class="live-dot"></span> {{ __('Academic year') }} {{ now()->year }}–{{ now()->addYear()->format('y') }}<small>{{ __('Building brighter futures, together.') }}</small></div>
</aside>
<div class="workspace">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3"><button type="button" class="btn sidebar-toggle sidebar-toggle-desktop d-none d-lg-inline-flex" data-sidebar-toggle aria-controls="sidebar" aria-expanded="true" aria-label="{{ __('Collapse navigation') }}" title="{{ __('Collapse navigation') }}"><i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i></button><button type="button" class="btn btn-light d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="{{ __('Open navigation') }}"><i class="bi bi-list"></i></button>@if(auth()->user()->role !== 'student')<form class="portal-search" action="{{ route('students.index') }}" method="get" role="search">@if(($myChildren ?? false) && auth()->user()->role !== 'parent')<input type="hidden" name="my_children" value="1">@endif<i class="bi bi-search" aria-hidden="true"></i><input type="search" name="search" aria-label="{{ __('Search students') }}" placeholder="{{ __('Search students...') }}" value="{{ request()->routeIs('students.index') ? request('search') : '' }}"></form>@elseif(auth()->user()->role === 'student' && (isset($studentSessions) || in_array(request('section'), ['coursework', 'record'], true)))
            <form class="portal-search sd-search {{ in_array(request('section'), ['coursework', 'record'], true) ? 'sd-search-wide' : '' }}" action="{{ route('dashboard') }}" method="get" role="search">
                <input type="hidden" name="section" value="coursework">
                @if(request()->filled('assignment_filter'))<input type="hidden" name="assignment_filter" value="{{ request('assignment_filter') }}">@endif
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" name="search" maxlength="100" aria-label="{{ __('Search assignments, exams, or results') }}" placeholder="{{ __('Search assignments, exams, or results...') }}" value="{{ request('section') === 'coursework' ? request('search') : '' }}">
            </form>
        @elseif(auth()->user()->role === 'student' && request('section') === 'announcements')
            <form class="portal-search sd-search sd-search-wide" action="{{ route('dashboard') }}" method="get" role="search">
                <input type="hidden" name="section" value="announcements">
                @if(request()->filled('announcement_view'))<input type="hidden" name="announcement_view" value="all">@endif
                <i class="bi bi-search" aria-hidden="true"></i>
                <input type="search" name="search" maxlength="100" aria-label="{{ __('Search announcements') }}" placeholder="{{ __('Search announcements...') }}" value="{{ request('search') }}">
            </form>
        @endif<span class="breadcrumb-label">{{ __('Workspace') }} <i class="bi bi-chevron-right"></i> <strong>@yield('title', 'Overview')</strong></span></div>
        <div class="d-flex align-items-center gap-3"><button type="button" id="hover-menus-toggle" class="btn btn-outline-secondary" aria-pressed="true" title="Turn automatic menu opening on or off"><i class="bi bi-mouse me-1" aria-hidden="true"></i><span>Hover menus: On</span></button>@include('school.partials.language-picker')@include('school.partials.theme-picker')
@if(isset($studentSessions))
    @if($studentSessions->isNotEmpty())
        <details class="sd-session">
            <summary><i class="bi bi-clock" aria-hidden="true"></i> {{ __('Session') }} · {{ $studentSessions->pluck('session')->filter()->unique()->map(fn ($session) => __($session))->join(', ') }} <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
            <div>
                @foreach($studentSessions as $class)
                    <a href="{{ route('timetables.index', ['class' => $class->id]) }}">{{ $class->label }}</a>
                @endforeach
            </div>
        </details>
    @endif
@endif
@if(isset($announcementCount))
    <a class="sd-notifications" href="{{ route('dashboard', ['section' => 'announcements']) }}" aria-label="{{ __('Announcements this week') }} ({{ $announcementCount }})">
        <i class="bi bi-bell" aria-hidden="true"></i>@if($announcementCount)<span aria-hidden="true">{{ $announcementCount }}</span>@endif
    </a>
@endif
@if(auth()->user()->role === 'administrator')<a class="btn btn-outline-primary" href="{{ route('attendance-settings.edit') }}" title="Edit check-in session times" aria-label="Edit check-in session times"><i class="bi bi-clock" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">Session times</span></a>@endif
@if(in_array(auth()->user()->role, ['administrator', 'teacher'], true))
@php($scanState = auth()->user()->role === 'teacher' ? app(\App\Services\TeacherScanAttendance::class)->scanState(auth()->user()) : ['action' => 'check_in', 'label' => __('Start scan location')])
<a class="btn btn-primary teacher-scan-shortcut" href="{{ route('school.map', ['scan' => 1]) }}#attendance-check-in" data-scan-action="{{ $scanState['action'] }}" @if(auth()->user()->role === 'teacher') data-scan-state-url="{{ route('school.map.scan-state') }}" @endif aria-label="{{ $scanState['label'] }}" title="{{ $scanState['label'] }}"><i class="bi bi-crosshair" aria-hidden="true"></i><span class="d-none d-xl-inline">{{ $scanState['label'] }}</span></a>
@endif
@php($accountPhotoUrl = auth()->user()->account_photo_url)
<span class="term-badge d-none d-md-inline"><span class="live-dot"></span> {{ __('School portal') }}</span><a href="{{ route('profile.edit') }}" class="profile-link d-flex align-items-center gap-3" aria-label="{{ __('Edit your profile') }}" title="{{ __('Edit profile') }}">@if($accountPhotoUrl)<img class="user-avatar" src="{{ $accountPhotoUrl }}" alt="{{ __('Your profile photo') }}">@else<span class="user-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>@endif<div class="user-info d-none d-sm-block"><strong>{{ auth()->user()->name }}</strong><small>{{ __(ucfirst(auth()->user()->role)) }}</small></div></a><form action="{{ route('logout') }}" method="post">@csrf<button class="btn icon-button" aria-label="{{ __('Sign out') }}" title="{{ __('Sign out') }}"><i class="bi bi-box-arrow-right"></i></button></form></div>
    </header>
    <main class="main-content" id="main-content" tabindex="-1">
        @if(session('success'))<div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button></div>@endif
        @if($errors->any())<div class="alert alert-danger" role="alert"><strong>{{ __('Please check the following:') }}</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if(auth()->user()->role === 'teacher' && !($myChildren ?? false))<div class="mb-3 {{ request()->routeIs('dashboard') ? 'windy-original' : '' }}"><a class="btn btn-outline-secondary" href="{{ route('teachers.schedule', auth()->id()) }}">{{ __('My teaching schedule') }}</a></div>@endif
        @include('school.students.enrollment-accounts')
        @yield('content')
        <footer class="page-footer"><span>© {{ now()->year }} Scholar. Made for better school days.</span><span>Greenfield Academy <i class="bi bi-heart-fill text-success ms-1"></i></span></footer>
    </main>
</div>
@include('school.partials.confirm-dialog')
@if(in_array(auth()->user()->role, ['administrator', 'teacher'], true))
<link href="{{ asset('css/checkin-receipt.css') }}?v={{ filemtime(public_path('css/checkin-receipt.css')) }}" rel="stylesheet">
<dialog class="scan-dialog" data-scan-dialog data-timetable-url="{{ route('timetables.index') }}" aria-label="Attendance check-in">
    <button type="button" class="scan-dialog-close" data-close-scan aria-label="Close attendance result" autofocus>&times;</button>
    <button type="button" data-global-scan data-endpoint="{{ route('school.map.scan') }}" hidden></button>
    <div data-scan-dialog-result role="status" aria-live="polite"></div>
</dialog>
@include('school.partials.scan-loading')
<script src="{{ asset('js/school-map.js') }}?v={{ filemtime(public_path('js/school-map.js')) }}" defer></script>
@endif
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>

@if(auth()->user()->role === 'administrator' || request()->routeIs('students.index'))
    <script src="{{ asset('js/student-excuses.js') }}?v={{ filemtime(public_path('js/student-excuses.js')) }}" defer></script>
@endif
@stack('scripts')
@if(session()->has('student_draft_clear'))
<script>try { sessionStorage.removeItem(@json('student-draft-'.auth()->id().'-'.session('student_draft_clear'))); } catch (_) {}</script>
@endif
</body></html>
