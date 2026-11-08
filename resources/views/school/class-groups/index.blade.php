@extends('school.layout')
@section('title', 'Classes')

@section('content')
<div class="class-groups-workspace">
<x-scholar-hero tag="section" class="page-heading class-page-heading class-groups-hero" aria-labelledby="class-groups-heading">
    <div>
        <div class="eyebrow">Academic structure</div>
        <h1 id="class-groups-heading">Class <span>Groups</span><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></h1>
        <p>One class identity can study in several sessions and use different physical classrooms.</p>
    </div>
    <div class="class-hero-actions d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('classrooms.index') }}"><i class="bi bi-door-open me-1" aria-hidden="true"></i> {{ __('Classroom availability') }}</a>
        @if(auth()->user()->role === 'administrator')
            <a class="btn btn-primary" href="{{ route('class-groups.create') }}"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Add class group</a>
        @endif
    </div>
</x-scholar-hero>

<section class="class-summary-grid" aria-label="Class group totals">
    @foreach(\App\Models\Program::names() as $program)
        @php
            [$icon, $tone] = match ($program) {
                'Khmer' => ['translate', 'summary-khmer'],
                'English' => ['alphabet-uppercase', 'summary-english'],
                'Chinese' => ['chat-square-text', 'summary-chinese'],
                'Japan' => ['globe-asia-australia', 'summary-japan'],
                default => ['book', 'summary-program'],
            };
        @endphp
        <a class="class-summary-card {{ $tone }}" href="{{ route('class-groups.index', ['program' => $program]) }}">
            <span class="summary-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
            <span class="summary-copy"><small>{{ $program }} classes</small><strong>{{ $programCounts[$program] ?? 0 }}</strong></span>
            <svg class="class-summary-wave" viewBox="0 0 240 40" preserveAspectRatio="none" aria-hidden="true"><path d="M0 34 Q55 0 115 25 T240 14 V40 H0Z" fill="currentColor"/></svg><i class="bi bi-people summary-corner" aria-hidden="true"></i>
        </a>
    @endforeach
    <div class="class-summary-card summary-total">
        <span class="summary-icon"><i class="bi bi-collection" aria-hidden="true"></i></span>
        <span class="summary-copy"><small>Total class groups</small><strong>{{ $totalClassGroups }}</strong></span><svg class="class-summary-wave" viewBox="0 0 240 40" preserveAspectRatio="none" aria-hidden="true"><path d="M0 34 Q55 0 115 25 T240 14 V40 H0Z" fill="currentColor"/></svg><i class="bi bi-people summary-corner" aria-hidden="true"></i>
    </div>
    <a class="class-summary-card summary-rooms" href="{{ route('classrooms.index') }}">
        <span class="summary-icon"><i class="bi bi-building" aria-hidden="true"></i></span>
        <span class="summary-copy"><small>Physical classrooms</small><strong>{{ $physicalClassrooms }}</strong></span><svg class="class-summary-wave" viewBox="0 0 240 40" preserveAspectRatio="none" aria-hidden="true"><path d="M0 34 Q55 0 115 25 T240 14 V40 H0Z" fill="currentColor"/></svg><i class="bi bi-people summary-corner" aria-hidden="true"></i>
    </a>
</section>

<nav class="program-selector" aria-label="Filter class groups by academic program">
    <a class="{{ request('program') ? '' : 'active' }}" href="{{ route('class-groups.index') }}"><i class="bi bi-grid" aria-hidden="true"></i>{{ __('All programs') }}</a>
    @foreach(\App\Models\Program::names() as $program)
        <a class="{{ request('program') === $program ? 'active' : '' }}" href="{{ route('class-groups.index', ['program' => $program]) }}"><i class="bi bi-{{ match ($program) { 'Khmer' => 'book', 'English' => 'alphabet-uppercase', 'Chinese' => 'chat-square-text', 'Japan' => 'globe-asia-australia', default => 'collection' } }}" aria-hidden="true"></i>{{ $program }}</a>
    @endforeach
</nav>

<section class="panel class-directory-panel">
    <div class="panel-header">
        <div><h2>Class directory <span class="badge text-bg-light">{{ $classGroups->total() }}</span></h2><p>Counts are class identities, not program/session entries.</p></div>
    </div>
    <form class="class-group-filters" method="get" action="{{ route('class-groups.index') }}">
        <div class="filter-search">
            <label class="form-label" for="class-search">Search</label>
            <div class="class-search-input"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control" id="class-search" name="search" type="search" value="{{ request('search') }}" placeholder="Class or teacher name..."></div>
        </div>
        <div>
            <label class="form-label" for="class-program">{{ __('Program') }}</label>
            <select class="form-select" id="class-program" name="program">
                <option value="">{{ __('All programs') }}</option>
                @foreach(\App\Models\Program::names() as $program)<option value="{{ $program }}" @selected(request('program') === $program)>{{ $program }}</option>@endforeach
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
            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1" aria-hidden="true"></i> Apply</button>
            <a class="btn btn-light" href="{{ route('class-groups.index') }}"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>{{ __('Reset') }}</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table class-groups-table">
            <thead><tr>
                <th scope="col">#</th><th>Class name</th><th>{{ __('Grade') }}</th><th>Section</th><th>{{ __('Program') }}</th><th>Sessions</th><th>Classroom</th><th>{{ __('Teacher') }}</th><th>{{ __('Students') }}</th><th>Capacity</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
            @forelse($classGroups as $classGroup)
                @php($rooms = $classGroup->bookings->pluck('classroom')->filter()->unique('id')->sortBy('room_number'))
                <tr>
                    <td><span class="class-avatar">{{ $classGroups->firstItem() + $loop->index }}</span></td>
                    <td><a class="class-name-link" href="{{ route('class-groups.show', $classGroup) }}"><i class="bi bi-mortarboard class-row-icon" aria-hidden="true"></i><strong>{{ $classGroup->display_name }}</strong></a></td>
                    <td>Grade {{ $classGroup->grade }}</td>
                    <td>{{ $classGroup->section ?: '—' }}</td>
                    <td><span class="program-badge program-{{ strtolower($classGroup->program) }}">{{ $classGroup->program }}</span></td>
                    <td><div class="session-badges">@foreach($classGroup->sessions as $session)<span class="session-{{ strtolower($session->session) }}" title="{{ substr((string) $session->starts_at, 0, 5) }}–{{ substr((string) $session->ends_at, 0, 5) }}"><i class="bi bi-{{ $session->session === 'Evening' ? 'moon-stars' : 'sun' }}" aria-hidden="true"></i>{{ $session->session }}</span>@endforeach</div></td>
                    <td>@forelse($rooms as $room)<a class="room-chip" href="{{ route('classrooms.show', $room) }}"><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ $room->room_number }}</a>@empty<span class="text-secondary">Unassigned</span>@endforelse</td>
                    <td><i class="bi bi-person class-row-icon me-2" aria-hidden="true"></i>{{ $classGroup->teacher?->name ?? 'Unassigned' }}</td>
                    <td><i class="bi bi-people class-row-icon me-2" aria-hidden="true"></i><strong>{{ $classGroup->students_count }}</strong></td>
                    <td><i class="bi bi-layers class-row-icon me-2" aria-hidden="true"></i>{{ $classGroup->capacity }}</td>
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
                <tr><td colspan="12"><div class="empty-state"><i class="bi bi-search" aria-hidden="true"></i>No class groups match these filters.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="directory-pagination">{{ $classGroups->links() }}</div>
</section>
</div>
@endsection
