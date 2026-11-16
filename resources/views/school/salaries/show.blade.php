@extends('school.layout')
@section('title', 'Salary · '.$teacher->name)
@section('content')
<div class="page-heading"><div><div class="eyebrow">EMPLOYEE SALARY</div><h1>{{ $teacher->name }}</h1><p>{{ $teacher->profile_number ?: $teacher->email }} · {{ $teacher->program ?: 'Teacher' }}</p></div><a class="btn btn-outline-primary" href="{{ route('employee-salaries.index', ['month' => $month->format('Y-m')]) }}">Back to payroll</a></div>
<form class="panel salary-month-select" method="get"><label for="detail-month" class="form-label">Salary month</label><input id="detail-month" class="form-control" type="month" name="month" value="{{ $month->format('Y-m') }}"><button class="btn btn-primary">View month</button></form>
@php($displaySalary = $payment ? $payment->only(['base_cents', 'raise_cents', 'total_cents']) : $salary)
<div class="salary-summary-grid">
    <div class="panel salary-metric"><span>Base monthly salary</span><strong>{{ $displaySalary ? '$'.\App\Support\Money::decimal($displaySalary['base_cents']) : 'Not set' }}</strong></div>
    <div class="panel salary-metric"><span>Service increase</span><strong>{{ $displaySalary ? '+$'.\App\Support\Money::decimal($displaySalary['raise_cents']) : '—' }}</strong><small>$5 every 3 completed months</small></div>
    <div class="panel salary-metric salary-metric-total"><span>{{ $month->format('F Y') }} salary</span><strong>{{ $displaySalary ? '$'.\App\Support\Money::decimal($displaySalary['total_cents']) : 'Not set' }}</strong><small>{{ $payment ? 'Paid on '.$payment->paid_on->format('d M Y') : 'Payment not recorded' }}</small></div>
</div>
<div class="salary-two-columns">
    <section class="panel p-4"><div class="salary-section-title"><i class="bi bi-sliders" aria-hidden="true"></i><h2>Work sessions &amp; salary</h2></div>
        <form method="post" action="{{ route('employee-salaries.update', $teacher) }}">@csrf @method('put')
            <label class="form-label" for="day-sessions">Daytime sessions per workday</label><select id="day-sessions" class="form-select mb-3" name="day_sessions">@foreach([0 => 'None — $0', 1 => '1 session · 4 hours — $125/month', 2 => '2 sessions · 8 hours — $250/month'] as $value => $label)<option value="{{ $value }}" @selected((int) old('day_sessions', $profile?->day_sessions ?? 0) === $value)>{{ $label }}</option>@endforeach</select>
            <label class="form-label" for="evening-sessions">Evening sessions per workday</label><select id="evening-sessions" class="form-select mb-3" name="evening_sessions">@foreach([0 => 'None — $0', 1 => '1 evening session — $31.25/month', 2 => '2 evening sessions — $62.50/month'] as $value => $label)<option value="{{ $value }}" @selected((int) old('evening_sessions', $profile?->evening_sessions ?? 0) === $value)>{{ $label }}</option>@endforeach</select>
            <label class="form-label" for="service-start">Service start date</label><input class="form-control mb-3" id="service-start" name="service_started_on" type="date" value="{{ old('service_started_on', ($profile?->service_started_on ?? $teacher->hire_date)?->toDateString()) }}" required>
            <p class="form-text">All sessions total $312.50/month before service increases. Each completed 3-month period adds $5 to the total salary, assessed on {{ $asOf->format('d M Y') }}. Attendance does not automatically reduce salary.</p>
            @if($salary)<p class="small text-secondary">Next service increase: <strong>{{ $salary['next_raise_on']->format('d M Y') }}</strong></p>@endif
            <button class="btn btn-primary">Save salary settings</button>
        </form>
    </section>
    <section class="panel p-4"><div class="salary-section-title"><i class="bi bi-wallet2" aria-hidden="true"></i><h2>Salary payment</h2></div>
        @if($payment)<div class="salary-paid"><i class="bi bi-check-circle" aria-hidden="true"></i><h3>Salary recorded as paid</h3><p>${{ \App\Support\Money::decimal($payment->total_cents) }} · {{ $payment->paid_on->format('d F Y') }}</p><p>{{ $payment->reference ?: 'No payment reference' }}</p></div>
        @elseif($salary && $salary['total_cents'] > 0 && $month->lte(today()))
        <form method="post" action="{{ route('employee-salaries.pay', $teacher) }}">@csrf<input type="hidden" name="month" value="{{ $month->format('Y-m') }}"><input type="hidden" name="expected_total_cents" value="{{ $salary['total_cents'] }}">
            <p class="salary-payment-amount">${{ \App\Support\Money::decimal($salary['total_cents']) }} <small>USD · {{ $month->format('F Y') }}</small></p>
            <label class="form-label" for="salary-paid-on">Payment date</label><input id="salary-paid-on" class="form-control mb-3" type="date" name="paid_on" min="{{ $month->toDateString() }}" max="{{ today()->toDateString() }}" value="{{ old('paid_on', today()->toDateString()) }}" required>
            <label class="form-label" for="salary-reference">Payment reference / note</label><input id="salary-reference" class="form-control mb-3" name="reference" maxlength="255" value="{{ old('reference') }}" placeholder="Cash, bank transfer, or receipt reference">
            <label class="d-flex gap-2 mb-3"><input type="checkbox" name="confirm" value="1" required><span>I confirm this salary has been paid.</span></label>
            <p class="form-text">Records the payment already made. The amount and work sessions are saved permanently for this month.</p><button class="btn btn-primary">Record salary payment</button>
        </form>
        @else<p class="text-secondary">Save salary settings to record a payment for an eligible current or past month.</p>@endif
    </section>
</div>
<section class="panel mt-4"><div class="panel-header"><h2>Teacher attendance · {{ $month->format('F Y') }}</h2><a class="btn btn-sm btn-outline-primary" href="{{ route('teacher-attendance.monthly', ['month' => $month->format('Y-m')]) }}">All teacher attendance</a></div>
    <div class="salary-attendance-summary">@foreach(['present', 'late', 'absent', 'excused'] as $status)<span class="status-badge {{ $status }}">{{ $attendance->where('status', $status)->count() }} {{ ucfirst($status) }}</span>@endforeach<span class="small text-secondary">Daily records · session scans shown below</span></div>
    <details class="salary-attendance-editor"><summary>Record daily attendance</summary><form action="{{ route('teacher-attendance.store') }}" method="post" class="salary-filters">@csrf<input type="hidden" name="records[0][teacher_id]" value="{{ $teacher->id }}"><div><label class="form-label" for="attendance-date">Date</label><input id="attendance-date" class="form-control" name="date" type="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div><div><label class="form-label" for="attendance-status">Status</label><select id="attendance-status" class="form-select" name="records[0][status]">@foreach(['present', 'late', 'absent', 'excused'] as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></div><button class="btn btn-primary">Save attendance</button></form></details>
    <div class="table-responsive salary-attendance-scroll"><table class="table salary-table"><thead><tr><th>Date</th><th>Daily status</th><th>Session attendance / scans</th><th>Notes</th></tr></thead><tbody>
    @foreach(\Carbon\CarbonPeriod::create($month, $month->copy()->endOfMonth()) as $date)
        @php($record = $attendance->get($date->toDateString()))
        <tr><td>{{ $date->format('d M Y') }}<small>{{ $date->format('l') }}</small></td><td>@if($record)<span class="status-badge {{ $record->status }}">{{ ucfirst($record->status) }}</span>@else<span class="text-secondary">{{ $date->isFuture() ? 'Upcoming' : 'Not recorded' }}</span>@endif</td><td>@forelse($scans->get($date->toDateString(), collect()) as $scan)<div class="salary-scan"><strong>{{ $scan->session }}</strong> · {{ ucfirst($scan->status) }}<small>In {{ $scan->scanned_at?->format('H:i') ?? '—' }} / Out {{ $scan->checked_out_at?->format('H:i') ?? '—' }}</small></div>@empty<span class="text-secondary">—</span>@endforelse</td><td>{{ $record?->notes ?: '—' }}</td></tr>
    @endforeach
    </tbody></table></div>
</section>
<section class="panel mt-4"><div class="panel-header"><h2>Salary payment history</h2><span class="text-secondary small">Latest 12 payments</span></div><div class="table-responsive"><table class="table salary-table"><thead><tr><th>Salary month</th><th>Paid on</th><th>Base salary</th><th>Service increase</th><th>Total paid (USD)</th><th>Reference</th></tr></thead><tbody>@forelse($history as $paid)<tr><td>{{ $paid->salary_month->format('F Y') }}</td><td>{{ $paid->paid_on->format('d M Y') }}</td><td>${{ \App\Support\Money::decimal($paid->base_cents) }}</td><td>${{ \App\Support\Money::decimal($paid->raise_cents) }}</td><td><strong>${{ \App\Support\Money::decimal($paid->total_cents) }}</strong></td><td>{{ $paid->reference ?: '—' }}</td></tr>@empty<tr><td colspan="6" class="p-4 text-center text-secondary">No salary payments recorded yet.</td></tr>@endforelse</tbody></table></div></section>
@endsection
