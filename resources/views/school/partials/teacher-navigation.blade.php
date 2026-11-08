<nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Teacher sections">
    @if(\App\Support\SchoolModules::canView(auth()->user(), 'teachers'))
        <a class="btn {{ request()->routeIs('teacher-attendance.*') ? 'btn-outline-primary' : 'btn-primary' }}" href="{{ route('modules.index', 'teachers') }}" @if(!request()->routeIs('teacher-attendance.*')) aria-current="page" @endif><i class="bi bi-people me-1" aria-hidden="true"></i>Teacher list</a>
    @endif
    <a class="btn {{ request()->routeIs('teacher-attendance.*') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('teacher-attendance.index') }}" @if(request()->routeIs('teacher-attendance.*')) aria-current="page" @endif><i class="bi bi-person-check me-1" aria-hidden="true"></i>Attendance</a>
</nav>
