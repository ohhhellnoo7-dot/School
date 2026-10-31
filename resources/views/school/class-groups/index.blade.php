@extends('school.layout')
@section('title', 'Classes')

@section('content')
<div class="page-heading class-page-heading">
    <div>
        <div class="eyebrow">Academic structure</div>
        <h1>Class groups</h1>
        <p>One class identity can study in several sessions and use different physical classrooms.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('classrooms.index') }}"><i class="bi bi-door-open me-1" aria-hidden="true"></i> {{ __('Classroom availability') }}</a>
        @if(auth()->user()->role === 'administrator')
            <a class="btn btn-primary" href="{{ route('class-groups.create') }}"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Add class group</a>
        @endif
    </div>
</div>

<section class="class-summary-grid" aria-label="Class group totals">
    @foreach([
        ['Khmer', 'Khmer classes', 'translate', 'summary-khmer'],
        ['English', 'English classes', 'alphabet-uppercase', 'summary-english'],
        ['Chinese', 'Chinese classes', 'chat-square-text', 'summary-chinese'],
    ] as [$program, $label, $icon, $tone])
        <a class="class-summary-card {{ $tone }}" href="{{ route('class-groups.index', ['program' => $program]) }}">
            <span class="summary-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
            <span><small>{{ $label }}</small><strong>{{ $programCounts[$program] ?? 0 }}</strong></span>
        </a>
    @endforeach
    <div class="class-summary-card summary-total">
        <span class="summary-icon"><i class="bi bi-collection" aria-hidden="true"></i></span>
        <span><small>Total class groups</small><strong>{{ $totalClassGroups }}</strong></span>
    </div>
    <a class="class-summary-card summary-rooms" href="{{ route('classrooms.index') }}">
        <span class="summary-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
        <span><small>Physical classrooms</small><strong>{{ $physicalClassrooms }}</strong></span>
    </a>
</section>

<nav class="program-selector" aria-label="Filter class groups by academic program">
    <a class="{{ request('program') ? '' : 'active' }}" href="{{ route('class-groups.index') }}">{{ __('All programs') }}</a>
    @foreach(['Khmer', 'English', 'Chinese'] as $program)
        <a class="{{ request('program') === $program ? 'active' : '' }}" href="{{ route('class-groups.index', ['program' => $program]) }}">{{ $program }}</a>
    @endforeach
</nav>

<section class="panel class-directory-panel">
    <div class="panel-header">
        <div><h2>Class directory <span class="badge text-bg-light">{{ $classGroups->total() }}</span></h2><p>Counts are class identities, not program/session entries.</p></div>
    </div>
    <form class="class-group-filters" method="get" action="{{ route('class-groups.index') }}">
        <div class="filter-search">
            <label class="form-label" for="class-search">Search</label>
            <input class="form-control" id="class-search" name="search" type="search" value="{{ request('search') }}" placeholder="Class or teacher name">
        </div>
        <div>
            <label class="form-label" for="class-program">{{ __('Program') }}</label>
            <select class="form-select" id="class-program" name="program">
                <option value="">{{ __('All programs') }}</option>
                @foreach(['Khmer', 'English', 'Chinese'] as $program)<option value="{{ $program }}" @selected(request('program') === $program)>{{ $program }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="class-grade">{{ __('Grade') }}</label>
            <select class="form-select" id="class-grade" name="grade">
                <option value="">{{ __('All grades') }}</option>
                @for($grade = 1; $grade <= 12; $grade++)<option value="{{ $grade }}" @selected((string) request('grade') === (string) $grade)>Grade {{ $grade }}</option>@endfor
            </select>
        </div>
        <div>
            <label class="form-label" for="class-session">{{ __('Session') }}</label>
            <select class="form-select" id="class-session" name="session">
                <option value="">{{ __('All sessions') }}</option>
                @foreach(['Morning', 'Afternoon', 'Evening'] as $session)<option value="{{ $session }}" @selected(request('session') === $session)>{{ $session }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="class-status">{{ __('Status') }}</label>
            <select class="form-select" id="class-status" name="status">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-light" href="{{ route('class-groups.index') }}">{{ __('Reset') }}</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table class-groups-table">
            <thead><tr>
                <th>Class name</th><th>{{ __('Grade') }}</th><th>Section</th><th>{{ __('Program') }}</th><th>Sessions</th><th>Classroom</th><th>{{ __('Teacher') }}</th><th>{{ __('Students') }}</th><th>Capacity</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
            @forelse($classGroups as $classGroup)
                @php($rooms = $classGroup->bookings->pluck('classroom')->filter()->unique('id')->sortBy('room_number'))
                <tr>
                    <td><a class="class-name-link" href="{{ route('class-groups.show', $classGroup) }}"><span class="class-avatar">{{ $classGroup->grade }}{{ $classGroup->section }}</span><strong>{{ $classGroup->display_name }}</strong></a></td>
                    <td>Grade {{ $classGroup->grade }}</td>
                    <td>{{ $classGroup->section ?: '—' }}</td>
                    <td><span class="program-badge program-{{ strtolower($classGroup->program) }}">{{ $classGroup->program }}</span></td>
                    <td><div class="session-badges">@foreach($classGroup->sessions as $session)<span title="{{ substr((string) $session->starts_at, 0, 5) }}–{{ substr((string) $session->ends_at, 0, 5) }}">{{ $session->session }}</span>@endforeach</div></td>
                    <td>@forelse($rooms as $room)<a class="room-chip" href="{{ route('classrooms.show', $room) }}">{{ $room->room_number }}</a>@empty<span class="text-secondary">Unassigned</span>@endforelse</td>
                    <td>{{ $classGroup->teacher?->name ?? 'Unassigned' }}</td>
                    <td><strong>{{ $classGroup->students_count }}</strong></td>
                    <td>{{ $classGroup->capacity }}</td>
                    <td><span class="record-status {{ $classGroup->status }}"><i class="bi bi-{{ $classGroup->status === 'active' ? 'check-circle-fill' : 'pause-circle-fill' }}" aria-hidden="true"></i> {{ ucfirst($classGroup->status) }}</span></td>
                    <td><div class="table-actions">
                        <a class="btn btn-sm btn-light" href="{{ route('class-groups.show', $classGroup) }}" aria-label="View {{ $classGroup->display_name }}"><i class="bi bi-eye" aria-hidden="true"></i></a>
                        @if(auth()->user()->role === 'administrator')
                            <a class="btn btn-sm btn-light" href="{{ route('class-groups.edit', $classGroup) }}" aria-label="Edit {{ $classGroup->display_name }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                            <form method="post" action="{{ route('class-groups.destroy', $classGroup) }}" data-confirm="Delete this class group? Linked records are protected.">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" aria-label="Delete {{ $classGroup->display_name }}"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
                        @endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="11"><div class="empty-state"><i class="bi bi-search" aria-hidden="true"></i>No class groups match these filters.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="directory-pagination">{{ $classGroups->links() }}</div>
</section>
@endsection
