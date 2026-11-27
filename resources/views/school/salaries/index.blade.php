@extends('school.layout')
@section('title', 'Employee salary')
@section('content')
<div class="payroll-page">
<header class="payroll-hero">
    <svg class="payroll-hero-waves" viewBox="0 0 1400 170" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0 0h378c111 3 121 77 230 48s166-13 231 35 109 58 231 22 207-80 330-105v170H0z"/>
        <path d="M0 170c195-33 278-91 449-53 129 28 193 58 278 4s164-72 250-46 124 44 224 10 158-40 199-30v115z"/>
        <path d="M0 170c218-39 313-18 446-11 117 6 200-9 310-29 122-22 188-32 340-13 126 15 205 30 304 53z"/>
    </svg>
    <div class="payroll-hero-copy">
        <div class="payroll-heading-group">
            <i class="bi bi-wallet2 payroll-hero-icon" aria-hidden="true"></i>
            <div>
                <h1>Employee Salary</h1>
                <p>Manage teacher salaries, work hours, and payroll efficiently</p>
            </div>
        </div>
        <div class="payroll-actions">
            <button class="payroll-action payroll-action-primary" type="button" data-open-salary-calculator aria-haspopup="dialog" aria-controls="salary-calculator"><i class="bi bi-calculator" aria-hidden="true"></i> Salary Calculator</button>
            <a class="payroll-action" id="salary-monthly-attendance" href="{{ route('teacher-attendance.monthly', ['month' => $month->format('Y-m')]) }}"><i class="bi bi-calendar-check" aria-hidden="true"></i> Monthly Attendance</a>
            <button type="button" class="payroll-action" data-edit-salary-rates aria-haspopup="dialog"><i class="bi bi-pencil" aria-hidden="true"></i> {{ __('Edit salary') }}</button>
        </div>
    </div>
    <img class="payroll-hero-art" src="{{ asset('images/payroll-calendar.png') }}" width="860" height="358" alt="" decoding="async">
</header>
@include('school.salaries._calculator')
@include('school.salaries._edit-rates')
<section class="payroll-surface payroll-rates" aria-labelledby="payroll-rates-heading">
    <div class="payroll-heading-group">
        <i class="bi bi-stack payroll-icon" aria-hidden="true"></i>
        <div class="payroll-section-heading">
            <h2 id="payroll-rates-heading">Monthly base salary</h2>
            <p>Monthly pay by weekly schedule</p>
        </div>
    </div>
    <dl class="payroll-rate-list">
    @foreach($salaryRates as $hours => $amount)
        <div>
            <i class="bi bi-{{ ['stopwatch', 'alarm', 'briefcase-fill', 'award-fill', 'lightning-charge-fill'][$loop->index] }} payroll-icon" aria-hidden="true"></i>
            <div>
                <dt>{{ $hours }} h each week</dt>
                <dd>${{ \App\Support\Money::decimal($amount) }} <span class="payroll-rate-period">per month</span></dd>
            </div>
        </div>
    @endforeach
    </dl>
</section>
<dl class="payroll-metrics" id="salary-payroll-metrics" aria-label="Monthly payroll summary">
    @foreach([
        ['people-fill', 'Total teachers', $teacherCount],
        ['check-lg', 'Paid this month', $recorded->paid_count],
        ['clock', 'Not recorded', $teacherCount - $recorded->paid_count],
        ['wallet2', 'Total recorded payroll', '$'.\App\Support\Money::decimal($recorded->paid_cents)],
    ] as [$icon, $label, $value])
        <div>
            <i class="bi bi-{{ $icon }} payroll-icon" aria-hidden="true"></i>
            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            <i class="bi bi-{{ ['people-fill', 'coins', 'clipboard2-check', 'cash-coin'][$loop->index] }} payroll-metric-art" aria-hidden="true"></i>
        </div>
    @endforeach
</dl>
@include('school.salaries._overview')
@if($withdrawalTotals)
    <section class="payroll-surface p-4 mb-3" id="payday-withdrawal" aria-labelledby="payday-withdrawal-title">
        <h2 class="h4" id="payday-withdrawal-title">{{ __('Withdraw money for employees') }}</h2>
        <p class="mb-1">{{ __('Payday :payday · Attendance through :end', [
            'payday' => $month->translatedFormat('j F Y'),
            'end' => $month->copy()->subDay()->translatedFormat('j F Y'),
        ]) }}</p>
        <p class="mb-0"><strong>{{ __('Total still to pay') }} ${{ \App\Support\Money::decimal($withdrawalTotals['remaining']) }} USD</strong></p>
        <p class="mb-0">{{ __('Paid to employees') }} ${{ \App\Support\Money::decimal($withdrawalTotals['paid']) }} USD</p>
        <small class="text-secondary">{{ __('Recorded salaries and early payouts are excluded.') }}</small>
    </section>
@endif
<section class="payroll-surface payroll-register" aria-labelledby="payroll-register-heading">
    <div class="payroll-register-heading">
        <div class="payroll-heading-group">
            <i class="bi bi-people-fill payroll-icon" aria-hidden="true"></i>
            <div class="payroll-section-heading">
                <h2 id="payroll-register-heading">Teacher Payroll</h2>
                <p id="salary-payroll-month">{{ $month->format('F Y') }}</p>
            </div>
        </div>
        <form class="payroll-filters" method="get" role="search" aria-label="Search teacher payroll" data-salary-search>
            <div>
                <label class="form-label" for="salary-month">Salary month</label>
                <input class="form-control" id="salary-month" name="month" type="month" value="{{ $month->format('Y-m') }}">
            </div>
            <div>
                <label class="form-label" for="salary-search">Search teachers</label>
                <div class="payroll-search-input">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input class="form-control" id="salary-search" type="search" name="search" autocomplete="off" maxlength="255" aria-controls="salary-search-results" placeholder="Search by name or employee ID..." value="{{ request('search') }}">
                </div>
            </div>
            <noscript><button class="payroll-action" type="submit">Apply filters</button></noscript>
        </form>
        <div class="payroll-register-actions">
            <span class="payroll-count" id="salary-teacher-count"><i class="bi bi-people-fill me-1" aria-hidden="true"></i> {{ $teachers->total() }} teachers</span>
            <a class="payroll-action payroll-action-primary" id="salary-export" href="{{ route('employee-salaries.index', ['month' => $month->format('Y-m'), 'search' => request('search'), 'export' => 'csv']) }}"><i class="bi bi-download" aria-hidden="true"></i> Export <span class="visually-hidden">CSV</span></a>
        </div>
    </div>
    <div id="salary-search-results" aria-live="polite"><div class="table-responsive" role="region" aria-labelledby="payroll-register-heading" tabindex="0"><table class="table salary-table"><thead><tr><th scope="col">#</th><th scope="col">Employee</th><th scope="col">Work sessions</th><th scope="col">Attendance days</th><th scope="col">Base salary</th><th scope="col">Salary increase</th><th scope="col">Deductions</th><th scope="col">Net monthly salary</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
    @forelse($rows as $row)
        <tr><td><span class="payroll-row-number">{{ $teachers->firstItem() + $loop->index }}</span></td><td><div class="payroll-person">
            @include('school.partials.avatar', ['person' => $row['teacher'], 'avatarClass' => 'payroll-avatar', 'teacherIcon' => true])
            <div><strong>{{ $row['teacher']->name }}</strong><small>{{ $row['teacher']->profile_number ?: $row['teacher']->email }}</small></div>
        </div></td><td>
            @if($row['profile'])
                <div class="payroll-count-grid">
                    <span>{{ $row['payment']?->day_sessions ?? $row['profile']->day_sessions }} daytime</span>
                    <span>{{ $row['payment']?->evening_sessions ?? $row['profile']->evening_sessions }} evening</span>
                </div>
            @else
                <span class="text-secondary text-nowrap">Not configured</span>
            @endif
            <small class="payroll-weekly-hours" title="{{ $row['weekly_work_hours'] === null ? 'Scheduled hours / weekly teaching limit' : 'Scheduled hours / configured weekly work hours' }}">
                <i class="bi bi-clock me-1" aria-hidden="true"></i><strong>{{ round($row['weekly_hours'], 2) }}/{{ round($row['weekly_limit'], 2) }} h/week</strong>
            </small>
        </td>
        <td>
            <div class="payroll-count-grid text-secondary">
                @foreach(['present', 'late', 'absent', 'excused'] as $status)
                    <span @class(['text-success fw-semibold' => $status === 'present'])>{{ $row['attendance']->get($status, 0) }} {{ $status }}</span>
                @endforeach
            </div>
        </td>
        <td>{{ $row['salary'] ? '$'.\App\Support\Money::decimal($row['salary']['base_cents']) : '—' }}</td><td class="payroll-increase">{{ $row['salary'] ? '+$'.\App\Support\Money::decimal($row['salary']['raise_cents']) : '—' }}</td><td class="payroll-deduction">{{ $row['salary'] ? '-$'.\App\Support\Money::decimal($row['salary']['deduction_cents'] ?? 0) : '—' }}</td>
        <td class="text-nowrap">
            <strong class="payroll-net">{{ $row['salary'] ? '$'.\App\Support\Money::decimal($row['salary']['total_cents']) : '—' }}</strong>
            @if($row['salary'] && $row['full_salary_cents'] !== null)
                <span class="text-secondary" title="Full monthly salary before raises and deductions">/ ${{ \App\Support\Money::decimal($row['full_salary_cents']) }}</span>
            @endif
            @if($row['salary']['combined_class_cents'] ?? 0)
                <small class="d-block text-secondary">{{ __('Combined-class pay') }} +${{ \App\Support\Money::decimal($row['salary']['combined_class_cents']) }}</small>
            @endif
        </td>
        <td>
            @if($row['payment'] || $row['paid_early'])
                <span class="status-badge paid">{{ $row['paid_early'] ? __('Paid early') : __('Paid') }}</span>
            @else
                <span class="status-badge pending">{{ $row['profile'] ? __('Not recorded') : __('Setup needed') }}</span>
                @if($payday && ($row['salary']['gross_cents'] ?? 0) > 0)
                    <form method="post" action="{{ route('employee-salaries.payday', $row['teacher']) }}" class="mt-2">
                        @csrf
                        <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                        <input type="hidden" name="paid_on" value="{{ $month->toDateString() }}">
                        <input type="hidden" name="confirm" value="1">
                        <input type="hidden" name="expected_total_cents" value="{{ $row['salary']['total_cents'] }}">
                        <input type="hidden" name="expected_advance_cents" value="{{ $row['salary']['advance_cents'] }}">
                        <button class="btn btn-sm btn-primary" type="submit" aria-label="{{ __('Record payment for :name', ['name' => $row['teacher']->name]) }}">{{ __('Pay') }}</button>
                    </form>
                @endif
            @endif
        </td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('employee-salaries.show', ['teacher' => $row['teacher'], 'month' => $month->format('Y-m')]) }}"><i class="bi bi-eye me-1" aria-hidden="true"></i>{{ $row['profile'] ? 'View details' : 'Set up salary' }}</a></td></tr>
    @empty<tr><td colspan="10" class="p-4 text-center">No teachers found.</td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $teachers->links() }}</div></div>
</section>
</div>
@endsection

@push('scripts')<script src="{{ asset('js/employee-salaries.js') }}?v={{ filemtime(public_path('js/employee-salaries.js')) }}" defer></script>@endpush
