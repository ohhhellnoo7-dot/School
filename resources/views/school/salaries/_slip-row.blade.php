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
    <th scope="row" title="{{ $teacher->name }}">
        @if($isBatch)
            <a href="{{ route('employee-salaries.show', ['teacher' => $teacher, 'month' => $month->format('Y-m'), 'slip' => $slip]) }}" title="{{ __('Open printable report') }}">{{ $teacher->name }}</a>
        @else
            {{ $teacher->name }}
        @endif
    </th>
    <td>{{ $gender }}</td>
    @if($slip === 'salary')
        <td>{{ $work['sessions'] ?? '—' }}</td>
        <td>{{ $work['scheduledHours'] }}/{{ $work['weeklyHours'] ?? '—' }} ម៉ោង</td>
        <td>{{ $workingDays }}</td>
        @foreach(['present', 'late', 'absent', 'excused'] as $status)
            <td>{{ $counts->get($status, 0) }}</td>
        @endforeach
        <td class="payroll-strip-amount">{{ $money($work['fullSalaryCents']) }}</td>
        <td class="payroll-strip-amount">{{ $money($displaySalary['base_cents'] ?? null) }}</td>
        <td class="payroll-strip-amount">{{ $money($displaySalary['raise_cents'] ?? null) }}</td>
        @foreach(['absence_cents', 'late_cents', 'excused_cents'] as $field)
            <td>{{ $money($displaySalary && ($deductions->isNotEmpty() || !($displaySalary['deduction_cents'] ?? 0)) ? $deductions->sum($field) : null) }}</td>
        @endforeach
        <td>{{ $money($displaySalary['deduction_cents'] ?? null) }}</td>
        <td class="payroll-strip-total">{{ $money($displaySalary['total_cents'] ?? null) }}</td>
    @else
        <td>គ្រូបង្រៀន</td>
        <td>{{ $work['sessions'] ?? '—' }}</td>
        <td class="payroll-strip-amount">{{ $money($displaySalary['base_cents'] ?? null) }}</td>
        <td class="payroll-strip-amount">{{ $money($displaySalary['raise_cents'] ?? null) }}</td>
        <td class="payroll-strip-amount">{{ $money($displaySalary ? $displaySalary['base_cents'] + $displaySalary['raise_cents'] : null) }}</td>
        @foreach($leaveMonths as $number => $days)
            @php
                $period = $month->copy()->month($number)->startOfMonth();
                $started = $work['serviceStartedOn'];
                $today = today();
                [$state, $stateLabel] = match (true) {
                    ($number < ($segmentStart ?? 1) || $number > ($segmentEnd ?? 12)) => ['not-started', __('Outside this work period')],
                    $started && $period->copy()->endOfMonth()->lt($started) => ['not-started', 'មុនថ្ងៃចូលធ្វើការ'],
                    !$started || $period->gt($today) => ['future', 'មិនទាន់ដល់ខែ ឬមិនមានថ្ងៃចូលធ្វើការ'],
                    $period->isSameMonth($today) => ['in-progress', 'កំពុងដំណើរការ'],
                    in_array($number, $attendanceBonus['qualifyingMonths'] ?? [], true) => ['complete', 'ខែបានបញ្ចប់ និងទទួលបានប្រាក់រង្វាន់'],
                    default => ['no-bonus', 'ខែបានបញ្ចប់ដោយមិនមានប្រាក់រង្វាន់'],
                };
            @endphp
            <td class="payroll-leave-{{ $state }}" title="{{ $stateLabel }}">
                <span class="visually-hidden">{{ $stateLabel }}៖ </span>{{ $days ?: '—' }}
            </td>
        @endforeach
        <td>{{ $leaveMonths->sum() }}</td><td>{{ $remainingLeave ?? ($leaveMonths->count() - $leaveMonths->sum()) }}</td>
        <td class="payroll-strip-amount">{{ $money($attendanceBonus['annualMaximum'] ?? null) }}</td>
        <td @class(['payroll-strip-total', 'payroll-strip-short-service' => $work['belowBonusServiceMinimum']])>
            @if(isset($attendanceBonus['annualMaximum']))
                @if($isBatch && !isset($closedBy))
                    <button type="button" class="payroll-bonus-edit d-print-none" aria-haspopup="dialog" aria-controls="leave-bonus-dialog"
                        title="{{ __('Edit leave bonus eligibility') }}"
                        data-bonus-months="{{ $work['requiredBonusMonths'] }}">
                        {{ $money($attendanceBonus['year']) }}
                    </button>
                    <span class="d-none d-print-inline">{{ $money($attendanceBonus['year']) }}</span>
                @else
                    {{ $money($attendanceBonus['year']) }}
                @endif
                @if($work['belowBonusServiceMinimum'])
                    <small class="d-block">ធ្វើការមិនទាន់គ្រប់ {{ $work['requiredBonusMonths'] }} ខែ</small>
                @endif
            @else
                &mdash;
            @endif
        </td>
    @endif
</tr>
