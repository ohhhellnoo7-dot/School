<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Overview') · Scholar School</title>
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
    <link href="{{ asset('css/workspace-refresh.css') }}?v={{ filemtime(public_path('css/workspace-refresh.css')) }}" rel="stylesheet">
    @if(request()->routeIs('modules.index') && request()->route('module') === 'subjects')<link href="{{ asset('css/subject-refresh.css') }}?v={{ filemtime(public_path('css/subject-refresh.css')) }}" rel="stylesheet">@endif
    <link href="{{ asset('css/scholar-hero.css') }}?v={{ filemtime(public_path('css/scholar-hero.css')) }}" rel="stylesheet">
    @if(request()->routeIs('classrooms.show'))<link href="{{ asset('css/room-detail-refresh.css') }}?v={{ filemtime(public_path('css/room-detail-refresh.css')) }}" rel="stylesheet">@endif
    @if(request()->routeIs('classrooms.index'))<link href="{{ asset('css/classroom-refresh.css') }}?v={{ filemtime(public_path('css/classroom-refresh.css')) }}" rel="stylesheet">@endif
@if(request()->routeIs('dashboard', 'teacher-attendance.*') || request()->route('module') === 'teachers')
<link href="{{ asset('css/teacher-attendance.css') }}?v={{ filemtime(public_path('css/teacher-attendance.css')) }}" rel="stylesheet">
@endif
<script src="{{ asset('js/hover-menus.js') }}?v={{ filemtime(public_path('js/hover-menus.js')) }}"></script>
<style>
html[data-hover-menus=off] .stat-breakdown:not(.is-open) .stat-breakdown-menu,
html[data-hover-menus=off] .building-picker:not(.is-open) .building-options,
html[data-hover-menus=off] .program-picker:not(.is-open) .program-options { display:none!important; }
.stat-card .stat-breakdown.is-open .stat-breakdown-menu { display:block; }
a.attendance-status-tile { text-decoration:none; color:inherit; }
a.attendance-status-tile:hover, a.attendance-status-tile:focus-visible { outline:2px solid currentColor; outline-offset:2px; }
</style>
</head>
<body class="scholar-workspace {{ request()->routeIs('modules.index') && request()->route('module') === 'subjects' ? 'subjects-page' : '' }} {{ request()->routeIs('class-groups.*') ? 'classes-page' : '' }} {{ request()->routeIs('classrooms.*', 'classroom-bookings.*') ? 'classrooms-page' : '' }} {{ request()->routeIs('modules.index') && request()->route('module') === 'teachers' ? 'teachers-page' : '' }} {{ request()->routeIs('modules.index') && request()->route('module') === 'parents' ? 'parents-page' : '' }} {{ request()->routeIs('students.index') ? 'students-page' : '' }}">
<a href="#main-content" class="skip-link">{{ __('Skip to content') }}</a>
<aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-label="{{ __('Main navigation') }}">
    <button class="btn sidebar-close d-lg-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="{{ __('Close navigation') }}"><i class="bi bi-x-lg"></i></button>
    <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark"><i class="bi bi-mortarboard-fill"></i></span><span class="brand-name">scholar<small>{{ __('School Portal') }}</small></span><span class="brand-dot">.</span></a>

    <div class="nav-caption">{{ __('Main menu') }}</div>
    <nav class="school-nav">
        <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-grid-1x2"></i><span class="nav-label"> {{ __('Overview') }}</span></a>
        <a class="{{ request()->routeIs('timetables.*') ? 'active' : '' }}" href="{{ route('timetables.index') }}"><i class="bi bi-calendar-week"></i><span class="nav-label"> {{ __('School Timetables') }}</span></a>
        <a class="{{ request()->routeIs('students.*') ? 'active' : '' }}" href="{{ route('students.index') }}"><i class="bi bi-people"></i><span class="nav-label"> {{ auth()->user()->role === 'parent' ? __('My children') : (auth()->user()->role === 'student' ? __('My profile') : __('Students')) }}</span></a>
        <div class="nav-group-caption">{{ __('Academics') }}</div>
        @foreach(\App\Support\SchoolModules::all() as $key => $module)
            @if(\App\Support\SchoolModules::canView(auth()->user(), $key))
                <a class="{{ ($key === 'classes' ? request()->routeIs('class-groups.*') : (request()->route('module') === $key || ($key === 'fees' && request()->routeIs('finance.*')) || ($key === 'teachers' && request()->routeIs('teacher-management.*', 'teachers.schedule*', 'teacher-attendance.*')))) ? 'active' : '' }}" href="{{ $key === 'classes' ? route('class-groups.index') : route('modules.index', $key) }}"><i class="bi bi-{{ $module['icon'] }}"></i><span class="nav-label">{{ __($module['title']) }}</span></a>
            @endif
        @endforeach
        @if(auth()->user()->role === 'teacher')
<a class="{{ request()->routeIs('teacher-attendance.*') ? 'active' : '' }}" href="{{ route('teacher-attendance.index') }}"><i class="bi bi-person-check"></i><span class="nav-label">{{ __('Teachers') }}</span></a>
@endif
<div class="nav-group-caption">{{ __('Workspace') }}</div>
        <a class="{{ request()->routeIs('classrooms.*', 'classroom-bookings.*') ? 'active' : '' }}" href="{{ route('classrooms.index') }}"><i class="bi bi-door-open"></i><span class="nav-label">{{ __('Classroom availability') }}</span></a>
        <a class="{{ request()->routeIs('school.map') ? 'active' : '' }}" href="{{ route('school.map') }}"><i class="bi bi-geo-alt" aria-hidden="true"></i><span class="nav-label">{{ __('School map') }}</span></a>
        @if(auth()->user()->role === 'administrator')<a class="{{ request()->routeIs('reports') ? 'active' : '' }}" href="{{ route('reports') }}"><i class="bi bi-bar-chart-line"></i><span class="nav-label"> {{ __('Reports') }}</span></a>@endif
    </nav>
    <div class="school-switch"><span class="school-icon"><i class="bi bi-buildings"></i></span><div><strong>Greenfield Academy</strong><small>{{ __('School management portal') }}</small></div></div>
    <div class="sidebar-footer"><span class="live-dot"></span> {{ __('Academic year') }} {{ now()->year }}–{{ now()->addYear()->format('y') }}<small>{{ __('Building brighter futures, together.') }}</small></div>
</aside>
<div class="workspace">
    <header class="topbar">
        <div class="d-flex align-items-center gap-3"><button type="button" class="btn sidebar-toggle sidebar-toggle-desktop d-none d-lg-inline-flex" data-sidebar-toggle aria-controls="sidebar" aria-expanded="true" aria-label="{{ __('Collapse navigation') }}" title="{{ __('Collapse navigation') }}"><i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i></button><button type="button" class="btn btn-light d-lg-none" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="{{ __('Open navigation') }}"><i class="bi bi-list"></i></button><form class="portal-search" action="{{ route('students.index') }}" method="get" role="search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" name="search" aria-label="{{ __('Search students') }}" placeholder="{{ __('Search students...') }}" value="{{ request()->routeIs('students.index') ? request('search') : '' }}"></form><span class="breadcrumb-label">{{ __('Workspace') }} <i class="bi bi-chevron-right"></i> <strong>@yield('title', 'Overview')</strong></span></div>
        <div class="d-flex align-items-center gap-3"><button type="button" id="hover-menus-toggle" class="btn btn-outline-secondary" aria-pressed="true" title="Turn automatic menu opening on or off"><i class="bi bi-mouse me-1" aria-hidden="true"></i><span>Hover menus: On</span></button>@include('school.partials.language-picker')@include('school.partials.theme-picker')<span class="term-badge d-none d-md-inline"><span class="live-dot"></span> {{ __('School portal') }}</span><a href="{{ route('profile.edit') }}" class="profile-link d-flex align-items-center gap-3" aria-label="{{ __('Edit your profile') }}" title="{{ __('Edit profile') }}">@if(auth()->user()->profile_photo_path)<img class="user-avatar" src="{{ route('profile.photo') }}" alt="{{ __('Your profile photo') }}">@else<span class="user-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>@endif<div class="user-info d-none d-sm-block"><strong>{{ auth()->user()->name }}</strong><small>{{ __(ucfirst(auth()->user()->role)) }}</small></div></a><form action="{{ route('logout') }}" method="post">@csrf<button class="btn icon-button" aria-label="{{ __('Sign out') }}" title="{{ __('Sign out') }}"><i class="bi bi-box-arrow-right"></i></button></form></div>
    </header>
    <main class="main-content" id="main-content" tabindex="-1">
        @if(session('success'))<div class="alert alert-success alert-dismissible fade show" role="status">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button></div>@endif
        @if($errors->any())<div class="alert alert-danger" role="alert"><strong>{{ __('Please check the following:') }}</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if(auth()->user()->role === 'teacher')<div class="mb-3"><a class="btn btn-outline-secondary" href="{{ route('teachers.schedule', auth()->id()) }}">{{ __('My teaching schedule') }}</a></div>@endif
        @include('school.students.enrollment-accounts')
        @yield('content')
        <footer class="page-footer"><span>© {{ now()->year }} Scholar. Made for better school days.</span><span>Greenfield Academy <i class="bi bi-heart-fill text-success ms-1"></i></span></footer>
    </main>
</div>
@include('school.partials.confirm-dialog')
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>

@stack('scripts')
</body></html>
