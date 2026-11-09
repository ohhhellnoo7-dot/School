<section class="panel p-4 mb-4 teacher-attendance-filter"><form method="get" action="{{ $embedded ? route('modules.index', 'teachers').'#teacher-attendance' : route('teacher-attendance.index') }}" class="d-flex flex-wrap align-items-end gap-3">
<div><label class="form-label" for="teacher-date"><i class="bi bi-calendar3" aria-hidden="true"></i> Date</label><input id="teacher-date" class="form-control" type="date" name="{{ $prefix }}date" value="{{ $date }}" required></div>
<div><label class="form-label" for="teacher-session">Session</label><select id="teacher-session" class="form-select" name="{{ $prefix }}session"><option value="">All sessions</option>
@foreach(\App\Support\AttendanceSession::OPTIONS as $sessionOption)
<option value="{{ $sessionOption }}" @selected(($filters['session'] ?? '') === $sessionOption)>{{ $sessionOption }}</option>
@endforeach
</select></div>
<div><label class="form-label" for="teacher-program"><i class="bi bi-layers" aria-hidden="true"></i> Program</label><select id="teacher-program" class="form-select" name="{{ $prefix }}program"><option value="">All programs</option>
@foreach(\App\Models\Program::names() as $program)
<option value="{{ $program }}" @selected(($filters['program'] ?? '') === $program)>{{ $program === 'Japan' ? 'Japanese' : $program }}</option>
@endforeach
</select></div><div><label class="form-label" for="teacher-search"><i class="bi bi-person" aria-hidden="true"></i> Teacher name or email</label><input id="teacher-search" class="form-control" placeholder="Search teacher..." name="{{ $prefix }}search" value="{{ $filters['search'] ?? '' }}"></div><button class="btn btn-primary">View attendance</button><a class="btn btn-outline-secondary" href="{{ $embedded ? route('modules.index', 'teachers').'#teacher-attendance' : route('teacher-attendance.index') }}">Reset</a></form></section>
@if($insights ?? null)
@include('school.attendance._workspace')
@else
@include('school.attendance._teacher-chart')
@endif
