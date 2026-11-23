@extends('school.layout')
@section('title', __('Teacher reports'))

@section('content')
<div class="page-heading d-print-none">
    <div class="d-flex flex-wrap align-items-center gap-3">
        <div><h1>{{ __('Teacher reports') }}</h1><p>{{ __('Salary slips and annual leave records') }}</p></div>
        <nav class="d-flex flex-wrap gap-2" aria-label="{{ __('Report type') }}">
            @foreach(['salary' => __('Salary slip'), 'leave' => __('Leave record'), 'all' => __('Show all')] as $type => $label)
                <a @class(['btn', 'btn-primary' => $filters['report'] === $type, 'btn-outline-primary' => $filters['report'] !== $type])
                    href="{{ request()->fullUrlWithQuery(['report' => $type, 'page' => null]) }}" data-teacher-report-type="{{ $type }}"
                    @if($filters['report'] === $type) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
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
    <div>
        <label class="form-label" for="report-month">{{ __('Salary month') }}</label>
        <input class="form-control" id="report-month" type="month" name="month" value="{{ $month->format('Y-m') }}" required>
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
<p class="visually-hidden" role="status" data-teacher-report-status></p>
@fragment('teacher-payroll-reports')
<div id="teacher-payroll-report-results">
    @if($reports->isEmpty())
        <p class="panel p-4">{{ __('No teachers match these filters.') }}</p>
    @else
        <div class="panel salary-report-book">
            <header class="salary-report-print-header d-none d-print-block mb-3">
                <h1 class="h4">{{ config('finance.school_name') }} &middot; {{ __('Teacher reports') }}</h1>
                <p class="mb-0">{{ $month->translatedFormat('F Y') }} &middot;
                    {{ ['salary' => __('Salary slips'), 'leave' => __('Annual leave records'), 'all' => __('Salary slips and annual leave records')][$filters['report']] }}
                </p>
            </header>
            @foreach($reports as $report)
                <section class="salary-report-teacher" aria-label="{{ $report['teacher']->name }}">
                    @if($filters['report'] !== 'leave')
                        @include('school.salaries._slip-table', ['reportRows' => [$report], 'slip' => 'salary'])
                    @endif
                    @if($filters['report'] !== 'salary')
                        @include('school.salaries._slip-table', ['reportRows' => [$report], 'slip' => 'leave'])
                    @endif
                </section>
            @endforeach
            @if($filters['report'] !== 'salary')
            <p class="mb-0 text-secondary">{{ __('Salary shown for :month. Monthly leave totals use excused attendance. Remaining leave and payout are unconfigured.', ['month' => $month->translatedFormat('F Y')]) }}</p>
            @endif
        </div>
    @endif
    <div class="d-print-none">{{ $teachers->links() }}</div>
</div>
@endfragment
@endsection

@push('scripts')
<script src="{{ asset('js/teacher-payroll-reports.js') }}?v={{ filemtime(public_path('js/teacher-payroll-reports.js')) }}" defer></script>
@endpush
