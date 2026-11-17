@extends('school.layout')
@section('title', 'Employee salary')
@section('content')
<div class="page-heading"><div><div class="eyebrow">PEOPLE &amp; PAYROLL</div><h1>Employee salary</h1><p>Monthly teacher salaries, salary increases, and attendance in one place.</p></div><a class="btn btn-outline-primary" href="{{ route('teacher-attendance.monthly', ['month' => $month->format('Y-m')]) }}"><i class="bi bi-calendar-check me-2"></i>Teacher attendance</a></div>
<div class="salary-rate-grid">
    @foreach(['4 daytime hours per day' => '125.00', '8 daytime hours per day' => '250.00', '1 evening session per day' => '31.25', '2 evening sessions per day' => '62.50', '8 daytime hours + 2 evening sessions per day' => '312.50'] as $label => $amount)
    <div class="panel salary-rate"><span>{{ $label }}</span><strong>${{ $amount }}</strong><small class="text-secondary">Total salary for one month</small></div>
    @endforeach
</div>
<section class="panel">
    <div class="panel-header"><h2>Teacher payroll <small class="text-secondary">· {{ $month->format('F Y') }}</small></h2><span class="text-secondary small" id="salary-teacher-count">{{ $teachers->total() }} teachers</span></div>
    <form class="salary-filters" method="get" data-salary-search><div><label class="form-label" for="salary-month">Salary month</label><input class="form-control" id="salary-month" name="month" type="month" value="{{ $month->format('Y-m') }}"></div><div><label class="form-label" for="salary-search">Search teacher by name</label><input class="form-control" id="salary-search" type="search" name="search" autocomplete="off" placeholder="Name or employee ID" value="{{ request('search') }}"></div><a class="btn btn-primary" href="{{ route('employee-salaries.attendance') }}"><i class="bi bi-calendar-check me-2"></i>Teacher attendance</a></form>
    <div id="salary-search-results" aria-live="polite"><div class="table-responsive"><table class="table salary-table"><thead><tr><th>Employee</th><th>Work sessions</th><th>Attendance days</th><th>Base salary</th><th>Salary increase</th><th>Deductions</th><th>Net monthly salary</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($rows as $row)
        <tr><td><strong>{{ $row['teacher']->name }}</strong><small>{{ $row['teacher']->profile_number ?: $row['teacher']->email }}</small></td><td>@if($row['profile']){{ $row['payment']?->day_sessions ?? $row['profile']->day_sessions }} daytime / {{ $row['payment']?->evening_sessions ?? $row['profile']->evening_sessions }} evening @else<span class="text-secondary">Not configured</span>@endif</td>
        <td><span class="text-success">{{ $row['attendance']->get('present', 0) }} present</span><small>{{ $row['attendance']->get('late', 0) }} late · {{ $row['attendance']->get('absent', 0) }} absent · {{ $row['attendance']->get('excused', 0) }} excused</small></td>
        <td>{{ $row['salary'] ? '$'.\App\Support\Money::decimal($row['salary']['base_cents']) : '—' }}</td><td>{{ $row['salary'] ? '+$'.\App\Support\Money::decimal($row['salary']['raise_cents']) : '—' }}</td><td>{{ $row['salary'] ? '-$'.\App\Support\Money::decimal($row['salary']['deduction_cents'] ?? 0) : '—' }}</td><td><strong>{{ $row['salary'] ? '$'.\App\Support\Money::decimal($row['salary']['total_cents']) : '—' }}</strong></td><td><span class="status-badge {{ $row['payment'] ? 'paid' : 'pending' }}">{{ $row['payment'] ? 'Paid' : ($row['profile'] ? 'Not recorded' : 'Setup needed') }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('employee-salaries.show', ['teacher' => $row['teacher'], 'month' => $month->format('Y-m')]) }}">{{ $row['profile'] ? 'View details' : 'Set up salary' }}</a></td></tr>
    @empty<tr><td colspan="9" class="p-4 text-center">No teachers found.</td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $teachers->links() }}</div></div>
</section>
@endsection

@push('scripts')<script src="{{ asset('js/employee-salaries.js') }}?v={{ filemtime(public_path('js/employee-salaries.js')) }}" defer></script>@endpush
