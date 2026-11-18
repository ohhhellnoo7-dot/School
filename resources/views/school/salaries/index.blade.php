@extends('school.layout')
@section('title', 'Employee salary')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">PEOPLE &amp; PAYROLL</div><h1>Employee salary</h1><p>Monthly teacher salaries, salary increases, and attendance in one place.</p></div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary" type="button" data-open-salary-calculator aria-haspopup="dialog" aria-controls="salary-calculator"><i class="bi bi-calculator me-2" aria-hidden="true"></i>Salary Calculator</button>
        <a class="btn btn-outline-primary" href="{{ route('teacher-attendance.monthly', ['month' => $month->format('Y-m')]) }}"><i class="bi bi-calendar-check me-2" aria-hidden="true"></i>Teacher attendance</a>
    </div>
</div>
@include('school.salaries._calculator')
<div class="salary-rate-grid">
    @foreach([5 => '31.25', 10 => '62.50', 20 => '125.00', 37 => '231.25', 40 => '250.00', 50 => '312.50'] as $hours => $amount)
    <div class="panel salary-rate"><span>{{ $hours }} assigned hours per week</span><strong>${{ $amount }}</strong><small class="text-secondary">Monthly base salary</small></div>
    @endforeach
</div>
<section class="panel">
    <div class="panel-header"><h2>Teacher payroll <small class="text-secondary">· {{ $month->format('F Y') }}</small></h2><span class="text-secondary small" id="salary-teacher-count">{{ $teachers->total() }} teachers</span></div>
    <form class="salary-filters" method="get" data-salary-search><div><label class="form-label" for="salary-month">Salary month</label><input class="form-control" id="salary-month" name="month" type="month" value="{{ $month->format('Y-m') }}"></div><div><label class="form-label" for="salary-search">Search teacher by name</label><input class="form-control" id="salary-search" type="search" name="search" autocomplete="off" placeholder="Name or employee ID" value="{{ request('search') }}"></div><a class="btn btn-primary" href="{{ route('employee-salaries.attendance') }}"><i class="bi bi-calendar-check me-2"></i>Teacher attendance</a></form>
    <div id="salary-search-results" aria-live="polite"><div class="table-responsive"><table class="table salary-table"><thead><tr><th>Employee</th><th>Work sessions</th><th>Attendance days</th><th>Base salary</th><th>Salary increase</th><th>Deductions</th><th>Net monthly salary</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($rows as $row)
        <tr><td><strong>{{ $row['teacher']->name }}</strong><small>{{ $row['teacher']->profile_number ?: $row['teacher']->email }}</small></td><td>
            @if($row['profile']){{ $row['payment']?->day_sessions ?? $row['profile']->day_sessions }} daytime / {{ $row['payment']?->evening_sessions ?? $row['profile']->evening_sessions }} evening @else<span class="text-secondary">Not configured</span>@endif
            <small><i class="bi bi-clock me-1" aria-hidden="true"></i><strong>{{ round($row['weekly_hours'], 2) }}/{{ round($row['weekly_limit'], 2) }} h</strong> {{ $row['weekly_work_hours'] === null ? 'scheduled / teaching limit' : 'scheduled / work week' }}</small>
        </td>
        <td><span class="text-success">{{ $row['attendance']->get('present', 0) }} present</span><small>{{ $row['attendance']->get('late', 0) }} late · {{ $row['attendance']->get('absent', 0) }} absent · {{ $row['attendance']->get('excused', 0) }} excused</small></td>
        <td>{{ $row['salary'] ? '$'.\App\Support\Money::decimal($row['salary']['base_cents']) : '—' }}</td><td>{{ $row['salary'] ? '+$'.\App\Support\Money::decimal($row['salary']['raise_cents']) : '—' }}</td><td>{{ $row['salary'] ? '-$'.\App\Support\Money::decimal($row['salary']['deduction_cents'] ?? 0) : '—' }}</td>
        <td class="text-nowrap">
            <strong>{{ $row['salary'] ? '$'.\App\Support\Money::decimal($row['salary']['total_cents']) : '—' }}</strong>
            @if($row['salary'] && $row['full_salary_cents'] !== null)
                <span class="text-secondary" title="Full monthly salary before raises and deductions">/ ${{ \App\Support\Money::decimal($row['full_salary_cents']) }}</span>
            @endif
        </td>
        <td><span class="status-badge {{ $row['payment'] ? 'paid' : 'pending' }}">{{ $row['payment'] ? 'Paid' : ($row['profile'] ? 'Not recorded' : 'Setup needed') }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('employee-salaries.show', ['teacher' => $row['teacher'], 'month' => $month->format('Y-m')]) }}">{{ $row['profile'] ? 'View details' : 'Set up salary' }}</a></td></tr>
    @empty<tr><td colspan="9" class="p-4 text-center">No teachers found.</td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $teachers->links() }}</div></div>
</section>
@endsection

@push('scripts')<script src="{{ asset('js/employee-salaries.js') }}?v={{ filemtime(public_path('js/employee-salaries.js')) }}" defer></script>@endpush
