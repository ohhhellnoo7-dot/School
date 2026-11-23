@php
    $chartMax = max(5000, (int) ceil($salaryRates->max() / 5000) * 5000);
    $paid = (int) $recorded->paid_count;
    $paidPercent = $teacherCount ? round(100 * $paid / $teacherCount, 1) : 0;
@endphp
<div class="payroll-overview" id="salary-payroll-overview">
    <section class="payroll-surface payroll-chart-card" aria-labelledby="payroll-overview-heading">
        <div class="payroll-heading-group">
            <i class="bi bi-people-fill payroll-icon" aria-hidden="true"></i>
            <div class="payroll-section-heading">
                <h2 id="payroll-overview-heading">Payroll Overview</h2>
                <p>{{ $month->format('F Y') }} <span class="visually-hidden">monthly base salary in USD by weekly hours</span></p>
            </div>
        </div>
        <div class="payroll-bar-chart">
            <div class="payroll-chart-axis" aria-hidden="true">
                @for($tick = $chartMax; $tick >= 0; $tick -= 5000)
                    <span>{{ $tick / 100 }}</span>
                @endfor
            </div>
            <div class="payroll-chart-bars" style="--chart-intervals: {{ $chartMax / 5000 }}">
                @foreach($salaryRates as $hours => $amount)
                    <div class="payroll-chart-column">
                        <span class="payroll-chart-bar" style="--bar-height: {{ $amount / $chartMax * 100 }}%" aria-hidden="true"></span>
                        <span class="payroll-chart-label">{{ $hours }}h <small>(${{ \App\Support\Money::decimal($amount) }})</small></span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="payroll-surface payroll-status-chart" aria-label="Payroll status for {{ $month->format('F Y') }}">
        <div class="payroll-status-ring {{ $teacherCount ? '' : 'is-empty' }}" style="--paid: {{ $paidPercent }}%">
            <div class="payroll-status-ring-center">
                <i class="bi bi-people-fill" aria-hidden="true"></i>
                <strong>{{ $teacherCount }}</strong>
                <span>Teachers</span>
            </div>
        </div>
        <dl class="payroll-status-key">
            <div><dt><span class="payroll-status-dot is-paid"></span>Paid</dt><dd><strong>{{ $paid }}</strong> ({{ $paidPercent }}%)</dd></div>
            <div><dt><span class="payroll-status-dot is-unrecorded"></span>Not recorded</dt><dd><strong>{{ $teacherCount - $paid }}</strong> ({{ $teacherCount ? 100 - $paidPercent : 0 }}%)</dd></div>
        </dl>
    </section>
</div>
