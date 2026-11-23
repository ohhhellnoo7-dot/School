@php
    $displaySalary = $payment ? $payment->only(['base_cents', 'raise_cents', 'deduction_cents', 'total_cents']) : $salary;
    $deductions = collect($payment ? $payment->deduction_details : ($salary['deduction_details'] ?? []));
    $money = fn ($cents) => $cents === null ? '—' : '$'.\App\Support\Money::decimal($cents).' USD';
    $gender = match (strtolower($teacher->gender ?? '')) {
        'male' => 'ប្រុស',
        'female' => 'ស្រី',
        default => '—',
    };
@endphp
<tr>
    <th scope="row">
        @if($isBatch)
            <a href="{{ route('employee-salaries.show', ['teacher' => $teacher, 'month' => $month->format('Y-m'), 'slip' => $slip]) }}" title="{{ __('Open printable report') }}">{{ $teacher->name }}</a>
        @else
            {{ $teacher->name }}
        @endif
    </th>
    <td>{{ $gender }}</td>
    @if($slip === 'salary')
        @foreach(['present', 'late', 'absent', 'excused'] as $status)
            <td>{{ $counts->get($status, 0) }}</td>
        @endforeach
        <td class="payroll-strip-amount">{{ $money($displaySalary['base_cents'] ?? null) }}</td>
        <td class="payroll-strip-amount">{{ $money($displaySalary['raise_cents'] ?? null) }}</td>
        @foreach(['absence_cents', 'late_cents', 'excused_cents'] as $field)
            <td>{{ $money($displaySalary && ($deductions->isNotEmpty() || !($displaySalary['deduction_cents'] ?? 0)) ? $deductions->sum($field) : null) }}</td>
        @endforeach
        <td>{{ $money($displaySalary['deduction_cents'] ?? null) }}</td>
        <td class="payroll-strip-total">{{ $money($displaySalary['total_cents'] ?? null) }}</td>
    @else
        <td>គ្រូបង្រៀន</td>
        <td class="payroll-strip-amount">{{ $money($displaySalary['base_cents'] ?? null) }}</td>
        <td class="payroll-strip-amount">{{ $money($displaySalary['raise_cents'] ?? null) }}</td>
        <td class="payroll-strip-amount">{{ $money($displaySalary ? $displaySalary['base_cents'] + $displaySalary['raise_cents'] : null) }}</td>
        @foreach($leaveMonths as $days)<td>{{ $days ?: '—' }}</td>@endforeach
        <td>{{ $leaveMonths->sum() }}</td><td>—</td><td>—</td>
    @endif
</tr>
