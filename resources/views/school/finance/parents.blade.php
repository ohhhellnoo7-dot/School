@extends('school.layout')
@section('title', 'Parent payment status')
@section('finance-title', 'Parent Payment Status')
@section('content')
@include('school.finance._nav')
@if(session('bulk_payment_run_id'))
<div class="mb-4"><a class="btn btn-outline-primary" href="{{ route('finance.bulk.show', session('bulk_payment_run_id')) }}"><i class="bi bi-receipt" aria-hidden="true"></i> View saved invoices &amp; receipts</a></div>
@endif

@if($bulkPlan)
<section class="panel p-4 mb-4" id="family-payment">
    <h2 class="h5">Pay school fees for all families</h2>
    <p>No invoices have been saved yet. Choose the period, review the total and confirm below to create paid invoices.</p>
    <div class="d-flex gap-2 flex-wrap mb-3">
    @foreach([1, 6, 12] as $months)
        <a class="btn {{ $bulkPlan['months'] === $months ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('finance.parents', ['pay_months' => $months]) }}#family-payment">{{ $months }} {{ $months === 1 ? 'month' : 'months' }}</a>
    @endforeach
    </div>
    <p>{{ $bulkPlan['parents_count'] }} families &middot; {{ $bulkPlan['students_count'] }} children</p>
    @foreach($bulkPlan['totals'] as $currency => $amount)
        <p class="h4">Total: {{ \App\Support\Money::display($amount) }} {{ $currency }}</p>
    @endforeach
    @if($bulkPlan['errors'])
        <div class="alert alert-danger"><strong>Payment cannot be saved yet:</strong><ul>
        @foreach($bulkPlan['errors'] as $error)
            <li>{{ $error }}</li>
        @endforeach
        </ul></div>
    @endif
</section>
@if(!$bulkPlan['cycle']['locked'])
    @include('school.finance._bulk-confirm', ['plan' => $bulkPlan])
@else
    <div class="alert alert-info">Payments are locked until {{ $bulkPlan['cycle']['next_on']->format('d F Y') }}.</div>
@endif
@endif
@php
    $labels = ['paid' => 'Fully paid', 'partially_paid' => 'Partly paid', 'unpaid' => 'Unpaid', 'waived' => 'Waived / no charge', 'no_invoices' => 'No invoices'];
    $periods = [
        1 => ['Paid 1 month', 'wallet2', 'mint', '#00ae80'],
        6 => ['Paid 6 months', 'calendar3', 'blue', '#139fe8'],
        12 => ['Paid 1 year', 'layers', 'violet', '#8257ef'],
        'not_paid' => ['No payment recorded', 'exclamation-triangle', 'rose', '#f1b914'],
    ];
    $chartPeriods = $periods + ['other' => ['Other payments', 'receipt', 'blue', '#8296ab']];
    $parentTotal = $chartCounts->sum();
    $lastMonth = $paymentYear === today()->year ? today()->month : 12;
    $months = collect(range(1, $lastMonth))->map(fn ($month) => \Carbon\CarbonImmutable::create($paymentYear, $month, 1));
    $monthLabels = $months->map(fn ($month) => $month->translatedFormat('M'));
@endphp
<div class="finance-overview">
    <div class="finance-metric-grid parent-finance-metrics">
        @foreach($periods as $duration => [$label, $icon, $tone])
            <a class="finance-overview-metric parent-finance-metric" data-tone="{{ $tone }}"
                href="{{ route('finance.parents', array_filter(['search' => request('search'), 'year' => $paymentYear, 'duration' => $duration])) }}#family-balances"
                @if((string) request('duration') === (string) $duration) aria-current="true" @endif>
                <i class="bi bi-{{ $icon }} finance-overview-icon" aria-hidden="true"></i>
                <span>{{ __($label) }}</span>
                <strong>{{ number_format($durationCounts[$duration]) }}</strong>
                <small>{{ __('Parents') }} &middot; {{ $paymentYear }}</small>
                <span class="parent-finance-share">{{ number_format($parentTotal ? 100 * $durationCounts[$duration] / $parentTotal : 0, 1) }}%</span>
            </a>
        @endforeach
    </div>

    <div class="parent-finance-charts">
        <section class="panel finance-collection-panel" data-finance-trend>
            @forelse($familyFinance as $currency => $totals)
                <header class="finance-collection-header">
                    <div class="finance-collection-title">
                        <i class="bi bi-bar-chart-line" aria-hidden="true"></i>
                        <div>
                            <h2>{{ __('Monthly Fee Collection') }} ({{ $currency }})</h2>
                            <p>{{ __('Payments minus refunds in :year', ['year' => $paymentYear]) }}</p>
                        </div>
                    </div>
                </header>
                @php($monthValues = $months->map(fn ($month) => ($totals['trend'][$month->format('Y-m')] ?? 0) / 100))
                <div class="finance-collection-chart">
                    <canvas data-finance-months data-mode="column" data-currency="{{ $currency }}" data-year="{{ $paymentYear }}"
                        data-labels='@json($monthLabels)' data-values='@json($monthValues)'
                        role="img" aria-label="{{ __('Monthly fee collection in :currency for :year', ['currency' => $currency, 'year' => $paymentYear]) }}"></canvas>
                </div>
                <details class="finance-collection-details">
                    <summary>{{ __('View monthly amounts') }}</summary>
                    <div class="finance-programs">
                        @foreach($months as $month)
                            <div><span>{{ $month->translatedFormat('M Y') }}</span><strong>{{ \App\Support\Money::display($totals['trend'][$month->format('Y-m')] ?? 0) }} {{ $currency }}</strong></div>
                        @endforeach
                    </div>
                </details>
            @empty
                <h2 class="h6">{{ __('Monthly Fee Collection') }}</h2>
                <p class="form-text">{{ __('No fee records for these families.') }}</p>
            @endforelse
        </section>
        <section class="panel finance-collection-panel">
            <header class="finance-collection-header">
                <div class="finance-collection-title"><i class="bi bi-pie-chart" aria-hidden="true"></i><h2>{{ __('Payment Status (Parents)') }}</h2></div>
            </header>
            <div class="finance-program-chart-layout parent-status-layout">
                <div class="finance-program-donut">
                    <canvas data-finance-programs data-values='@json(collect($chartPeriods)->keys()->map(fn ($key) => $chartCounts->get($key, 0)))'
                        data-colors='@json(array_column($chartPeriods, 3))' aria-hidden="true"></canvas>
                    <div class="finance-program-donut-total">
                        <strong>{{ number_format($parentTotal) }}</strong>
                        <span>{{ __('Parents') }}</span>
                        <span>({{ $paymentYear }})</span>
                    </div>
                </div>
                <dl class="finance-program-legend">
                    @foreach($chartPeriods as $key => [$label, $icon, $tone, $color])
                        @php($count = $chartCounts->get($key, 0))
                        @continue($key === 'other' && ! $count)
                        <div style="--finance-program-color: {{ $color }}">
                            <dt>{{ __($key === 'not_paid' ? 'No payment' : $label) }}</dt>
                            <dd>{{ number_format($count) }}</dd>
                            <dd>{{ number_format($parentTotal ? 100 * $count / $parentTotal : 0, 1) }}%</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <p class="form-text mb-0">{{ __('Each parent is grouped by their longest settled period in :year.', ['year' => $paymentYear]) }}</p>
        </section>

        <section class="panel finance-collection-panel parent-balance-panel">
            <header class="finance-collection-header">
                <div class="finance-collection-title"><i class="bi bi-bank" aria-hidden="true"></i><h2>{{ __('Fee Balance Overview') }}</h2></div>
            </header>
            @forelse($familyFinance as $currency => $totals)
                <dl class="parent-balance-overview">
                    @foreach(['billed' => 'Total Billed', 'paid' => 'Net Paid', 'balance' => 'Outstanding', 'overdue' => 'Overdue'] as $key => $label)
                        <div data-balance="{{ $key }}">
                            <dt>{{ __($label) }}</dt>
                            <dd>
                                <progress max="{{ max(1, $totals['billed'], $totals[$key]) }}" value="{{ max(0, $totals[$key]) }}" aria-hidden="true"></progress>
                                <strong>{{ \App\Support\Money::display($totals[$key]) }} {{ $currency }}</strong>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @empty
                <p class="form-text">{{ __('No invoice balances for these families.') }}</p>
            @endforelse
            <p class="form-text mb-0">{{ __('Balances cover all years.') }}</p>
        </section>
    </div>
    <details class="finance-collection-details">
        <summary>{{ __('How payment counts are calculated') }}</summary>
        <p class="mb-0">Counts use payment dates in {{ $paymentYear }} and settled invoices. Each parent is counted once per duration; a parent can appear in more than one duration. No payment recorded includes families without invoices. Balances below cover all years.</p>
    </details>
</div>
<section class="panel parent-family-balances" id="family-balances"><div class="panel-header"><div class="finance-collection-title"><i class="bi bi-people-fill" aria-hidden="true"></i><h2>Family Fee Balances</h2></div></div><div class="panel-body"><form method="get" action="{{ route('finance.parents') }}#family-balances" class="finance-filters"><div><label class="form-label" for="parent-search">Parent, child or student number</label><input id="parent-search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search families"></div><div><label class="form-label" for="payment-year">Payment year</label><input class="form-control" id="payment-year" name="year" type="number" min="2000" max="2100" value="{{ $paymentYear }}"></div><div><label class="form-label" for="payment-duration">Paid period</label><select class="form-select" id="payment-duration" name="duration"><option value="">All periods / parents</option>
@foreach(['1' => 'Paid 1 month', '6' => 'Paid 6 months', '12' => 'Paid 1 year', 'not_paid' => 'No payment recorded'] as $duration => $label)
<option value="{{ $duration }}" @selected((string) request('duration') === (string) $duration)>{{ $label }}</option>
@endforeach
</select></div><div><label class="form-label" for="family-status">Payment status</label><select id="family-status" name="status" class="form-select"><option value="">All parents</option>@foreach($labels as $status => $label)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>@endforeach</select></div><div class="d-flex align-items-end gap-2"><button class="btn btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> Apply</button><a class="btn btn-light" href="{{ route('finance.parents') }}#family-balances"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Reset</a></div></form><p class="form-text mb-0">Status covers all issued fees for all linked children, across programs and years. It identifies the responsible family, not who physically made a payment. Fully paid means every invoice is settled; waived charges and families without invoices are shown separately. USD and KHR are never combined.</p>
@if($unlinked)<p class="form-text mt-2 mb-0">{{ $unlinked }} students have invoices but no linked parent account. <a href="{{ route('students.index') }}">Link their parent in Students</a> to include them here.</p>@endif</div>
<div class="table-responsive"><table class="table"><thead><tr><th>Parent / contact</th><th>Children / invoices</th><th>Status</th><th>Net billed</th><th>Net paid</th><th>Outstanding</th><th>Overdue</th><th>Next payment date</th><th>View</th></tr></thead><tbody>@forelse($families as $family)
<tr><td><strong>{{ $family['parent']->name }}</strong><br><small>{{ $family['parent']->email }}</small><br><small>{{ $family['parent']->phone }}</small></td><td>@forelse($family['parent']->children as $child)<div>{{ $child->name }} <small>{{ $child->student_number }}</small><details><summary>{{ $child->fees->count() }} invoices</summary>@foreach($child->fees as $fee)<div><a href="{{ route('finance.invoice', $fee) }}">{{ $fee->display_number }}</a><br><small>{{ $fee->program ?: 'Legacy fee' }} &middot; {{ $fee->balance }} {{ $fee->currency }} remaining</small></div>@endforeach</details></div>@empty<span class="form-text">No linked children</span>@endforelse</td><td><span class="status-badge {{ $family['status'] }}">{{ $labels[$family['status']] }}</span></td>
@foreach(['billed','paid','balance'] as $key)<td>@forelse($family['currencies'] as $currency => $totals)<div>{{ \App\Support\Money::display($totals[$key]) }} {{ $currency }}</div>@empty&mdash;@endforelse</td>@endforeach
<td>@forelse($family['currencies'] as $currency => $totals)<div>{{ \App\Support\Money::display($totals['overdue']) }} {{ $currency }} overdue</div>@empty&mdash;@endforelse</td>
@php($schedule = $paymentSchedules[$family['parent']->id])
<td data-parent-schedule="{{ $family['parent']->id }}">@if($schedule['due'])<div><strong>{{ \Carbon\Carbon::parse($schedule['due'])->format('d F Y') }}</strong><br><small>Unpaid invoice due{{ $schedule['due'] < today()->toDateString() ? ' (overdue)' : '' }}</small></div>@endif
@if($schedule['scheduled'])<div class="mt-2"><strong>{{ \Carbon\Carbon::parse($schedule['scheduled'])->format('d F Y') }}</strong><br><small>{{ $schedule['scheduled_source'] === 'school' ? 'School-set next payment' : 'Next scheduled tuition - invoice not issued' }}</small></div>@elseif(!$schedule['due'])<span class="form-text">No next payment date configured</span>@endif</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-payment-detail="{{ route('finance.parents.details', $family['parent']) }}" aria-controls="parent-payment-drawer" aria-label="View payment dates for {{ $family['parent']->name }}" title="View payment dates" aria-haspopup="dialog"><i class="bi bi-eye" aria-hidden="true"></i></button></td></tr>
@empty<tr><td colspan="9" class="p-4 text-center">No parents match these filters.</td></tr>@endforelse</tbody></table></div><div class="p-3">{{ $families->links() }}</div></section>
<aside class="offcanvas offcanvas-end pd-drawer" tabindex="-1" id="parent-payment-drawer" aria-labelledby="parent-drawer-title" data-bs-scroll="false"><div id="parent-payment-content" class="pd-mount"><h2 id="parent-drawer-title" class="visually-hidden">Parent Payment Overview</h2></div></aside>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chart/chart.umd.min.js') }}" defer></script>
<script src="{{ asset('js/finance-overview.js') }}?v={{ filemtime(public_path('js/finance-overview.js')) }}" defer></script>
<script src="{{ asset('js/parent-payment-drawer.js') }}?v={{ filemtime(public_path('js/parent-payment-drawer.js')) }}" defer></script>
@endpush
