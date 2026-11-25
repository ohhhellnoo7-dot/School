@extends('school.layout')
@section('title', __('Teacher reports'))

@section('content')
<div class="page-heading d-print-none">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <div><h1>{{ __('Teacher reports') }}</h1><p>{{ __('Salary slips and annual leave records') }}</p></div>
        <nav class="d-flex flex-wrap gap-2" aria-label="{{ __('Report type') }}">
            @foreach(['salary' => __('Salary slip'), 'leave' => __('Leave record'), 'all' => __('Show all')] as $type => $label)
                <a @class(['btn', 'btn-primary' => $filters['report'] === $type, 'btn-outline-primary' => $filters['report'] !== $type])
                    href="{{ route('employee-salaries.teacher-reports', array_replace(request()->except(['page', 'year', 'month']), ['report' => $type], $type === 'leave' ? ['year' => $month->year] : ['month' => $month->format('Y-m')])) }}" data-teacher-report-type="{{ $type }}"
                    @if($filters['report'] === $type) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#outstanding-advances-modal" aria-haspopup="dialog">
                <i class="bi bi-wallet2 me-1" aria-hidden="true"></i>{{ __('Outstanding advances') }}
            </button>
            <button type="button" class="btn btn-outline-primary" data-edit-salary-rates>
                <i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('Edit salary') }}
            </button>
        </nav>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary" data-print-teacher-reports>
            <i class="bi bi-printer me-1" aria-hidden="true"></i>{{ __('Print') }}
        </button>
        <a class="btn btn-outline-primary" data-report-attendance-link href="{{ route('teacher-attendance.monthly', array_filter(['month' => $month->format('Y-m'), 'program' => $filters['program'] ?? null, 'session' => $filters['session']])) }}">{{ __('Monthly attendance') }}</a>
    </div>
</div>
<form class="panel salary-report-filters d-print-none" action="{{ route('employee-salaries.teacher-reports') }}" method="get" role="search" data-teacher-report-search>
    <input type="hidden" name="report" value="{{ $filters['report'] }}">
    <div>
        <label class="form-label" for="report-search">{{ __('Search teachers') }}</label>
        <input class="form-control" id="report-search" name="search" type="search" maxlength="255" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search by teacher name...') }}" autocomplete="off">
    </div>
    <div data-report-month-field @if($filters['report'] === 'leave') hidden @endif>
        <label class="form-label" for="report-month">{{ __('Salary month') }}</label>
        <input class="form-control" id="report-month" type="month" name="month" value="{{ $month->format('Y-m') }}" required @disabled($filters['report'] === 'leave')>
    </div>
    <div data-report-year-field @if($filters['report'] !== 'leave') hidden @endif>
        <label class="form-label" for="report-year">{{ __('Leave record year') }}</label>
        <input class="form-control" id="report-year" type="number" name="year" min="1900" max="9999" step="1" value="{{ $month->year }}" required @disabled($filters['report'] !== 'leave')>
    </div>
    <div>
        <label class="form-label" for="report-teacher">{{ __('Teacher') }}</label>
        <select class="form-select" id="report-teacher" name="teacher">
            <option value="">{{ __('All teachers') }}</option>
            @foreach($teacherOptions as $option)
                <option value="{{ $option->id }}" @selected((string) ($filters['teacher'] ?? '') === (string) $option->id)>{{ $option->name }}@if($option->profile_number) &middot; {{ $option->profile_number }}@endif</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label" for="report-program">{{ __('Program') }}</label>
        <select class="form-select" id="report-program" name="program">
            <option value="">{{ __('All programs') }}</option>
            @foreach(\App\Models\Program::names() as $program)
                <option value="{{ $program }}" @selected(($filters['program'] ?? null) === $program)>{{ $program }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label" for="report-session">{{ __('Session') }}</label>
        <select class="form-select" id="report-session" name="session">
            <option value="">{{ __('All sessions') }}</option>
            @foreach(\App\Support\AttendanceSession::OPTIONS as $session)
                <option value="{{ $session }}" @selected($filters['session'] === $session)>{{ $session }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
</form>
<div class="d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3 d-print-none">
    <span class="small text-secondary" role="status" data-teacher-report-status>{{ __('Refreshes about every 30 seconds while open.') }}</span>
    <button class="btn btn-sm btn-outline-primary" type="button" data-refresh-teacher-reports><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i>{{ __('Refresh') }}</button>
</div>
@fragment('teacher-payroll-reports')
<div id="teacher-payroll-report-results" data-reference-month="{{ $month->format('Y-m') }}">
    @if($reports->isEmpty())
        <p class="panel p-4">{{ $filters['report'] === 'leave' ? __('No leave records match these filters for :year.', ['year' => $month->year]) : __('No teachers match these filters.') }}</p>
    @else
        <div class="panel salary-report-book">
            <header class="salary-report-print-header d-none d-print-block mb-3">
                <h1 class="h4">{{ config('finance.school_name') }} &middot; {{ __('Teacher reports') }}</h1>
                <p class="mb-0">{{ $filters['report'] === 'leave' ? $month->year : $month->translatedFormat('F Y') }} &middot;
                    {{ ['salary' => __('Salary slips'), 'leave' => __('Annual leave records'), 'all' => __('Salary slips and annual leave records')][$filters['report']] }}
                </p>
            </header>
            @foreach($reports as $report)
                <section class="salary-report-teacher" aria-label="{{ $report['teacher']->name }}">
                    @if($filters['report'] !== 'leave')
                        @include('school.salaries._slip-table', ['reportRows' => [$report], 'slip' => 'salary'])
                    @endif
                    @if($filters['report'] !== 'salary')
                        @foreach($report['leaveSegments'] ?? [$report] as $leaveSegment)
                            @include('school.salaries._leave-segment-note', ['segment' => $leaveSegment])
                            @include('school.salaries._slip-table', ['reportRows' => [$leaveSegment], 'slip' => 'leave'])
                        @endforeach
                    @endif
                </section>
            @endforeach
            @if($filters['report'] !== 'salary')
                @include('school.salaries._leave-note')
            @endif
        </div>
    @endif
    <div class="d-print-none">{{ $teachers->links() }}</div>
</div>
@endfragment
@isset($outstandingAdvances)
    @include('school.salaries._outstanding-advances')
@endisset
@include('school.salaries._edit-rates')
<dialog id="leave-bonus-dialog" class="salary-calculator leave-bonus-dialog d-print-none" aria-labelledby="leave-bonus-title">
    <form method="post" action="{{ route('employee-salaries.leave-bonus-eligibility.update') }}" class="salary-calculator-form">
        @csrf
        @method('put')
        <h2 class="h5 mb-0" id="leave-bonus-title">{{ __('Leave bonus eligibility') }}</h2>
        <p class="text-secondary mb-0">{{ __('Applies to all teachers.') }}</p>
        <div>
            <label class="form-label" for="bonus-service-months">{{ __('Minimum months of service') }}</label>
            <input class="form-control" id="bonus-service-months" name="bonus_service_months" type="number" min="1" max="1200" step="1" required autofocus>
        </div>
        <div class="salary-calculator-actions">
            <button class="btn btn-outline-secondary" type="button" data-cancel-bonus-edit>{{ __('Cancel') }}</button>
            <button class="btn btn-primary" type="submit">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>
@endsection

@push('scripts')
<script src="{{ asset('js/teacher-payroll-reports.js') }}?v={{ filemtime(public_path('js/teacher-payroll-reports.js')) }}" defer></script>
@endpush
