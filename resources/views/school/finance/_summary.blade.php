@forelse($summary as $currency => $s)
<section class="mb-4" aria-label="{{ $currency }} financial summary">
<div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5">{{ $currency }} overview</h2><span class="form-text">Currencies are reported separately</span></div>
<div class="row g-3 mb-3">@foreach(['billed' => 'Total net billed', 'paid' => 'Payments collected', 'balance' => 'Outstanding balance', 'overdue' => 'Overdue balance', 'month' => 'Net receipts this month'] as $key => $label)<div class="col-sm-6 col-xl"><div class="stat-card finance-metric"><div class="stat-top">{{ $label }}</div><div class="stat-value">{{ \App\Support\Money::display($s[$key]) }}</div><small>{{ $currency }}</small></div></div>@endforeach</div>
<div class="finance-summary-grid"><section class="panel p-3"><h3 class="h6">Collected by program</h3><div class="finance-programs">@foreach(['Khmer', 'English', 'Chinese', 'Japan', 'Legacy / unclassified'] as $program)<div><span>{{ $program === 'Japan' ? 'Japanese' : $program }}</span><strong>{{ \App\Support\Money::display($s['programs'][$program] ?? 0) }} {{ $currency }}</strong></div>@endforeach</div><p class="form-text mb-0">{{ count($s['unpaid_students']) }} students with unpaid invoices. Legacy amounts remain unclassified until reconciled.</p></section>
<section class="panel p-3"><h3 class="h6">Monthly fee collection &middot; {{ $currency }}</h3>
@if(request()->routeIs('reports'))
@php
$collectionMonths = collect(range(11, 0))->map(fn ($offset) => today()->startOfMonth()->subMonths($offset));
$collectionLabels = $collectionMonths->map(fn ($month) => $month->format('M Y'));
$collectionValues = $collectionMonths->map(fn ($month) => ($s['trend'][$month->format('Y-m')] ?? 0) / 100);
@endphp
<div style="position:relative;height:280px"><canvas data-fee-collection data-currency="{{ $currency }}" data-labels='@json($collectionLabels)' data-values='@json($collectionValues)' role="img" aria-label="Monthly fee collection in {{ $currency }} for the last 12 months"></canvas></div>
<p class="form-text">Last 12 months &middot; Payments received minus refunds, by transaction month.</p>
<details><summary>View monthly amounts</summary><div class="finance-programs">
@foreach($collectionMonths as $month)
<div><span>{{ $month->format('M Y') }}</span><strong>{{ \App\Support\Money::display($s['trend'][$month->format('Y-m')] ?? 0) }} {{ $currency }}</strong></div>
@endforeach
</div></details>
@else
<div class="finance-trend">@forelse(array_slice($s['trend'], -12, null, true) as $month => $amount)<div><span>{{ $month }}</span><meter min="0" max="{{ max(1, ...array_values($s['trend'])) }}" value="{{ max(0, $amount) }}" aria-label="{{ $month }} receipts"></meter><strong>{{ \App\Support\Money::display($amount) }}</strong></div>@empty<p class="form-text">No dated ledger payments yet.</p>@endforelse</div>
@endif
<p class="form-text mb-0">Opening payments: {{ \App\Support\Money::display($s['opening']) }} {{ $currency }}. These are included in totals but excluded from trends because their transaction details are unverified.</p></section></div>
</section>
@empty<div class="panel p-4 mb-4">No invoices match these filters.</div>@endforelse
