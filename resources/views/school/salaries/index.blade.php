@extends('school.layout')
@section('title', 'Employee salary')
@section('content')
<div class="payroll-page">
<header class="payroll-hero">
    <div class="payroll-hero-copy">
        <h1>Employee Salary</h1>
        <p>Manage teacher pay, working hours, and attendance.</p>
        <div class="payroll-actions">
            <button class="payroll-action payroll-action-primary" type="button" data-open-salary-calculator aria-haspopup="dialog" aria-controls="salary-calculator"><i class="bi bi-calculator" aria-hidden="true"></i> Salary Calculator</button>
            <a class="payroll-action" id="salary-monthly-attendance" href="{{ route('teacher-attendance.monthly', ['month' => $month->format('Y-m')]) }}"><i class="bi bi-calendar-check" aria-hidden="true"></i> Monthly Attendance</a>
        </div>
    </div>
    <div class="payroll-hero-art" aria-hidden="true">
        <span class="payroll-hero-tile"><i class="bi bi-receipt"></i></span>
        <span class="payroll-hero-tile payroll-hero-tile-check"><i class="bi bi-check2"></i></span>
    </div>
</header>
@include('school.salaries._calculator')
<section class="payroll-surface payroll-rates" aria-labelledby="payroll-rates-heading">
    <div class="payroll-heading-group">
        <i class="bi bi-stack payroll-icon" aria-hidden="true"></i>
        <div class="payroll-section-heading">
            <h2 id="payroll-rates-heading">Monthly base salary</h2>
            <p>Monthly pay by weekly schedule</p>
        </div>
    </div>
    <dl class="payroll-rate-list">
    @foreach([5 => '31.25', 10 => '62.50', 20 => '125.00', 40 => '250.00', 50 => '312.50'] as $hours => $amount)
        <div>
            <i class="bi bi-{{ ['stopwatch', 'alarm', 'briefcase-fill', 'award-fill', 'lightning-charge-fill'][$loop->index] }} payroll-icon" aria-hidden="true"></i>
            <div>
                <dt>{{ $hours }} h each week</dt>
                <dd>${{ $amount }} <span class="payroll-rate-period">per month</span></dd>
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
        ['cash-stack', 'Total recorded payroll', '$'.\App\Support\Money::decimal($recorded->paid_cents)],
    ] as [$icon, $label, $value])
        <div>
            <i class="bi bi-{{ $icon }} payroll-icon" aria-hidden="true"></i>
            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
        </div>
    @endforeach
</dl>
<section class="payroll-surface payroll-register" aria-labelledby="payroll-register-heading">
    <div class="payroll-register-heading">
        <div class="payroll-heading-group">
            <i class="bi bi-people-fill payroll-icon" aria-hidden="true"></i>
            <div class="payroll-section-heading">
                <h2 id="payroll-register-heading">Teacher payroll</h2>
                <p id="salary-payroll-month">{{ $month->format('F Y') }}</p>
            </div>
        </div>
        <span class="payroll-count" id="salary-teacher-count">{{ $teachers->total() }} teachers</span>
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
    </form>
    <div id="salary-search-results" aria-live="polite"><div class="table-responsive" role="region" aria-labelledby="payroll-register-heading" tabindex="0"><table class="table salary-table"><thead><tr><th scope="col">#</th><th scope="col">Employee</th><th scope="col">Work sessions</th><th scope="col">Attendance days</th><th scope="col">Base salary</th><th scope="col">Salary increase</th><th scope="col">Deductions</th><th scope="col">Net monthly salary</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
    @forelse($rows as $row)
        <tr><td><span class="payroll-row-number">{{ $teachers->firstItem() + $loop->index }}</span></td><td><div class="payroll-person">
            @if($row['teacher']->profile_photo_url)
                <img class="payroll-avatar" src="{{ $row['teacher']->profile_photo_url }}" alt="" loading="lazy">
            @else
                <span class="payroll-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($row['teacher']->name, 0, 1)) }}</span>
            @endif
            <div><strong>{{ $row['teacher']->name }}</strong><small>{{ $row['teacher']->profile_number ?: $row['teacher']->email }}</small></div>
        </div></td><td>
            @if($row['profile']){{ $row['payment']?->day_sessions ?? $row['profile']->day_sessions }} daytime / {{ $row['payment']?->evening_sessions ?? $row['profile']->evening_sessions }} evening @else<span class="text-secondary">Not configured</span>@endif
            <small><i class="bi bi-clock me-1" aria-hidden="true"></i><strong>{{ round($row['weekly_hours'], 2) }}/{{ round($row['weekly_limit'], 2) }} h</strong> {{ $row['weekly_work_hours'] === null ? 'scheduled / teaching limit' : 'scheduled / work week' }}</small>
        </td>
        <td><span class="text-success">{{ $row['attendance']->get('present', 0) }} present</span><small>{{ $row['attendance']->get('late', 0) }} late · {{ $row['attendance']->get('absent', 0) }} absent · {{ $row['attendance']->get('excused', 0) }} excused</small></td>
        <td>{{ $row['salary'] ? '$'.\App\Support\Money::decimal($row['salary']['base_cents']) : '—' }}</td><td>{{ $row['salary'] ? '+$'.\App\Support\Money::decimal($row['salary']['raise_cents']) : '—' }}</td><td>{{ $row['salary'] ? '-$'.\App\Support\Money::decimal($row['salary']['deduction_cents'] ?? 0) : '—' }}</td>
        <td class="text-nowrap">
            <strong class="payroll-net">{{ $row['salary'] ? '$'.\App\Support\Money::decimal($row['salary']['total_cents']) : '—' }}</strong>
            @if($row['salary'] && $row['full_salary_cents'] !== null)
                <span class="text-secondary" title="Full monthly salary before raises and deductions">/ ${{ \App\Support\Money::decimal($row['full_salary_cents']) }}</span>
            @endif
        </td>
        <td><span class="status-badge {{ $row['payment'] ? 'paid' : 'pending' }}">{{ $row['payment'] ? 'Paid' : ($row['profile'] ? 'Not recorded' : 'Setup needed') }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('employee-salaries.show', ['teacher' => $row['teacher'], 'month' => $month->format('Y-m')]) }}">{{ $row['profile'] ? 'View details' : 'Set up salary' }}</a></td></tr>
    @empty<tr><td colspan="10" class="p-4 text-center">No teachers found.</td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $teachers->links() }}</div></div>
</section>
</div>
@endsection

@push('scripts')<script src="{{ asset('js/employee-salaries.js') }}?v={{ filemtime(public_path('js/employee-salaries.js')) }}" defer></script>@endpush
