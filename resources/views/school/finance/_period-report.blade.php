@php
    $selectedMonth = \Illuminate\Support\Carbon::createFromFormat('!Y-m', $feeReport['month']);
    $reportLabel = match ($feeReport['scope']) {
        'month' => $selectedMonth->translatedFormat('F Y'),
        'year' => (string) $feeReport['year'],
        default => __('All time'),
    };
    $invoiceFilters = request()->only(['search', 'program', 'grade', 'academic_year', 'status', 'duration']);
    $lastMonth = today()->startOfMonth()->subMonth()->format('Y-m');
@endphp
<section id="fee-period-report" aria-labelledby="fee-period-heading">
    <div class="panel finance-report-toolbar">
        <div>
            <h2 id="fee-period-heading">{{ __('Fee collection report') }}</h2>
            <p class="mb-0 text-secondary">{{ $reportLabel }}</p>
        </div>
        <form method="get" action="{{ route('finance.index') }}#fee-period-report" class="finance-report-controls">
            @foreach($invoiceFilters as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <div><label for="report-month" class="form-label">{{ __('Month') }}</label><input class="form-control" id="report-month" name="report_month" type="month" value="{{ $feeReport['month'] }}" max="{{ today()->format('Y-m') }}" min="1900-01"></div>
            <button class="btn btn-outline-primary" name="report_scope" value="month">{{ __('Show month') }}</button>
            <a class="btn btn-outline-primary" href="{{ route('finance.index', $invoiceFilters + ['report_scope' => 'month', 'report_month' => $lastMonth]) }}#fee-period-report">{{ __('Last month') }}</a>
            <div><label for="report-year" class="form-label">{{ __('Year') }}</label><input class="form-control" id="report-year" name="report_year" type="number" min="1900" max="{{ today()->year }}" value="{{ $feeReport['year'] }}"></div>
            <button class="btn btn-outline-primary" name="report_scope" value="year">{{ __('Show year') }}</button>
            <button class="btn btn-primary" name="report_scope" value="all">{{ __('All-time totals') }}</button>
        </form>
    </div>
    @foreach($feeReport['currencies'] as $currency => $report)
        @php
            $metrics = [
                ['billed', __('Net billed'), 'database-fill', 'mint'],
                ['collected', __('Net payments collected'), 'wallet2', 'blue'],
                ['remaining_now', __('Remaining balance now'), 'exclamation-triangle-fill', 'amber'],
                ['overdue', __('Overdue for this period'), 'clock', 'rose'],
                $feeReport['scope'] === 'all'
                    ? ['opening', __('Opening payments'), 'receipt-cutoff', 'violet']
                    : ['balance', __('Unpaid for this period'), 'receipt-cutoff', 'violet'],
            ];
        @endphp
        <dl class="finance-metric-grid">
            @foreach($metrics as [$key, $label, $icon, $tone])
                <div class="finance-overview-metric" data-tone="{{ $tone }}">
                    <dt><i class="bi bi-{{ $icon }} finance-overview-icon" aria-hidden="true"></i>{{ $label }}</dt>
                    <dd>{{ \App\Support\Money::display($report[$key]) }} <small>{{ $currency }}</small></dd>
                </div>
            @endforeach
        </dl>
        <p class="form-text mb-3">{{ __('Collections follow payment and refund dates. Billed and unpaid amounts follow invoice due dates. Remaining balance now covers all current invoices matching your filters.') }}</p>
        @if($feeReport['scope'] !== 'month')
            <details class="panel finance-report-months" open>
                <summary>{{ __('Net collections by month') }} ({{ $currency }})</summary>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead><tr><th scope="col">{{ __('Month') }}</th><th scope="col" class="text-end">{{ __('Net collected') }}</th></tr></thead>
                        <tbody>
                            @forelse($report['monthly'] as $month => $amount)
                                <tr><td><a href="{{ route('finance.index', $invoiceFilters + ['report_scope' => 'month', 'report_month' => $month]) }}#fee-period-report">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month)->translatedFormat('F Y') }}</a></td><td class="text-end">{{ \App\Support\Money::display($amount) }} {{ $currency }}</td></tr>
                            @empty
                                <tr><td colspan="2">{{ __('No payments or refunds recorded in this period.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($report['opening'] && $feeReport['scope'] === 'all')
                    <p class="form-text mb-0">{{ __('Opening payments are included in all-time collections. They have no transaction month.') }}</p>
                @endif
            </details>
        @endif
    @endforeach
</section>
