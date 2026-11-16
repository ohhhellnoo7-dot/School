@extends('school.layout')
@section('title', 'Employee salary')
@section('content')
<div class="page-heading"><div><div class="eyebrow">PEOPLE &amp; PAYROLL</div><h1>Employee salary</h1><p>Monthly teacher salaries, service increases, and attendance in one place.</p></div><a class="btn btn-outline-primary" href="{{ route('teacher-attendance.monthly', ['month' => $month->format('Y-m')]) }}"><i class="bi bi-calendar-check me-2"></i>Teacher attendance</a></div>
<div class="salary-rate-grid">
    @foreach(['1 daytime session · 4 hours' => '125.00', '2 daytime sessions · 8 hours' => '250.00', '1 evening session' => '31.25', '2 evening sessions' => '62.50', 'All sessions' => '312.50'] as $label => $amount)
    <div class="panel salary-rate"><span>{{ $label }}</span><strong>${{ $amount }}<small>/ month</small></strong></div>
    @endforeach
</div>
<div class="salary-policy"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i><div><strong>A $5 increase every 3 completed months</strong><span>Added once to the total monthly salary from the service start date. Rates are monthly; attendance is shown without automatic deductions. Service increases are assessed at the start of the selected month.</span></div></div>
<section class="panel">
    <div class="panel-header"><h2>Teacher payroll <small class="text-secondary">· {{ $month->format('F Y') }}</small></h2><span class="text-secondary small">{{ $teachers->total() }} teachers</span></div>
    <form class="salary-filters" method="get"><div><label class="form-label" for="salary-month">Salary month</label><input class="form-control" id="salary-month" name="month" type="month" value="{{ $month->format('Y-m') }}"></div><div><label class="form-label" for="salary-search">Find employee</label><input class="form-control" id="salary-search" name="search" placeholder="Name or employee ID" value="{{ request('search') }}"></div><button class="btn btn-primary">View payroll</button></form>
    <div class="table-responsive"><table class="table salary-table"><thead><tr><th>Employee</th><th>Work sessions</th><th>Attendance days</th><th>Base salary</th><th>Service increase</th><th>Monthly total</th><th>Status</th><th></th></tr></thead><tbody>
    @forelse($rows as $row)
        <tr><td><strong>{{ $row['teacher']->name }}</strong><small>{{ $row['teacher']->profile_number ?: $row['teacher']->email }}</small></td><td>@if($row['profile']){{ $row['payment']?->day_sessions ?? $row['profile']->day_sessions }} daytime / {{ $row['payment']?->evening_sessions ?? $row['profile']->evening_sessions }} evening @else<span class="text-secondary">Not configured</span>@endif</td>
        <td><span class="text-success">{{ $row['attendance']->get('present', 0) }} present</span><small>{{ $row['attendance']->get('late', 0) }} late · {{ $row['attendance']->get('absent', 0) }} absent · {{ $row['attendance']->get('excused', 0) }} excused</small></td>
        <td>{{ $row['salary'] ? '$'.\App\Support\Money::decimal($row['salary']['base_cents']) : '—' }}</td><td>{{ $row['salary'] ? '+$'.\App\Support\Money::decimal($row['salary']['raise_cents']) : '—' }}</td><td><strong>{{ $row['salary'] ? '$'.\App\Support\Money::decimal($row['salary']['total_cents']) : '—' }}</strong></td><td><span class="status-badge {{ $row['payment'] ? 'paid' : 'pending' }}">{{ $row['payment'] ? 'Paid' : ($row['profile'] ? 'Not recorded' : 'Setup needed') }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('employee-salaries.show', ['teacher' => $row['teacher'], 'month' => $month->format('Y-m')]) }}">{{ $row['profile'] ? 'View details' : 'Set up salary' }}</a></td></tr>
    @empty<tr><td colspan="8" class="p-4 text-center">No teachers found.</td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $teachers->links() }}</div>
</section>
@endsection
