@forelse($summary as $currency => $s)
@php
$programs = collect(['Khmer','English','Chinese','Japan','Legacy / unclassified']);
$programLabels = $programs->map(fn ($program) => $program === 'Japan' ? 'Japanese' : $program);
$programValues = $programs->map(fn ($program) => ($s['programs'][$program] ?? 0) / 100);
$months = collect(range(11,0))->map(fn ($offset) => today()->startOfMonth()->subMonths($offset));
$collectionLabels = $months->map(fn ($month) => $month->format('M Y'));
$collectionValues = $months->map(fn ($month) => ($s['trend'][$month->format('Y-m')] ?? 0) / 100);
$statuses = $reportDetails[$currency]['statuses'];
$statusTotal = array_sum($statuses);
@endphp
<div class="reports-section-title"><h2><i class="bi bi-stack"></i> {{ $currency }} Overview</h2><span>Currencies are reported separately</span></div>
<div class="reports-metrics">
@foreach(['billed'=>['Total Net Billed','file-earmark-text','violet'],'paid'=>['Payments Collected','wallet2','mint'],'balance'=>['Outstanding Balance','hourglass-split','amber'],'overdue'=>['Overdue Balance','exclamation-triangle','rose'],'month'=>['Net Receipts This Month','receipt','sky']] as $key=>$metric)
<section class="reports-metric {{ $metric[2] }}"><span class="reports-metric-icon"><i class="bi bi-{{ $metric[1] }}"></i></span><div><h3>{{ $metric[0] }}</h3><strong>{{ $currency === 'USD' ? '$' : '' }}{{ \App\Support\Money::display($s[$key]) }}</strong><small>{{ $currency }} &middot; {{ $key === 'month' ? today()->format('F Y') : 'Current invoice totals' }}</small></div></section>
@endforeach
</div>
@php
$cash = $cashReport['totals'][$currency] ?? ['receipts' => 0, 'refunds' => 0, 'expenses' => 0, 'remaining' => 0];
@endphp
<div class="reports-section-title"><h2><i class="bi bi-wallet2"></i> Money remaining &middot; {{ $cashReport['month']->format('F Y') }}</h2><span>All academic years &middot; {{ $currency }}</span></div>
<div class="reports-metrics reports-cash-metrics">
    <section class="reports-metric rose"><span class="reports-metric-icon"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></span><div><h3>Money taken out</h3><strong>{{ $currency === 'USD' ? '$' : '' }}{{ \App\Support\Money::display($cash['expenses']) }}</strong><small>Recorded expenses this month</small><a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('reports.expenses.create', ['currency' => $currency]) }}">Add expense &amp; reason</a></div><a class="reports-expense-view" href="{{ route('reports.expenses.index', ['currency' => $currency, 'month' => $cashReport['month']->format('Y-m')]) }}" aria-label="View expense details in {{ $currency }}" title="View expense details"><i class="bi bi-eye" aria-hidden="true"></i></a></section>
    <section class="reports-metric mint"><span class="reports-metric-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span><div><h3>Money remaining</h3><strong>{{ $currency === 'USD' ? '$' : '' }}{{ \App\Support\Money::display($cash['remaining']) }}</strong><small>Net receipts minus money taken out</small></div></section>
</div>
<div class="reports-chart-grid">
<section class="panel reports-collected"><div class="panel-header"><h2><i class="bi bi-stack"></i> Collected by Program</h2><a href="{{ route('finance.index', array_filter(['academic_year'=>request('academic_year')])) }}#invoice-list" aria-label="View invoices" title="View invoices"><i class="bi bi-arrow-up-right"></i></a></div><div class="reports-donut-layout"><div class="reports-donut"><canvas data-program-collection data-labels='@json($programLabels)' data-values='@json($programValues)' role="img" aria-label="Collected fees by program in {{ $currency }}"></canvas><div class="reports-donut-center"><strong>{{ $currency === 'USD' ? '$' : '' }}{{ \App\Support\Money::display($s['paid']) }}</strong><small>Total Collected</small></div></div><div class="reports-legend">
@foreach($programs as $index=>$program)
<div><span class="reports-dot color-{{ $index }}"></span><span>{{ $programLabels[$index] }}</span><strong>{{ \App\Support\Money::display($s['programs'][$program] ?? 0) }}</strong><small>{{ $s['paid'] > 0 ? number_format(($s['programs'][$program] ?? 0) / $s['paid'] * 100,1) : '0.0' }}%</small></div>
@endforeach
</div></div><p class="reports-footnote">{{ $currency }} &middot; Net collected after refunds. Opening balances included.</p></section>
<section class="panel"><div class="panel-header"><h2><i class="bi bi-graph-up"></i> Monthly Fee Collection &middot; {{ $currency }}</h2><span class="reports-pill">Last 12 months</span></div><div class="reports-line"><canvas data-fee-collection data-currency="{{ $currency }}" data-labels='@json($collectionLabels)' data-values='@json($collectionValues)' role="img" aria-label="Monthly fee collection in {{ $currency }}"></canvas></div><p class="reports-footnote">Payments minus refunds by transaction month. Opening balances excluded.</p><details class="reports-values"><summary>View monthly amounts</summary>
@foreach($months as $month)
<div class="d-flex justify-content-between"><span>{{ $month->format('M Y') }}</span><strong>{{ \App\Support\Money::display($s['trend'][$month->format('Y-m')] ?? 0) }} {{ $currency }}</strong></div>
@endforeach
</details></section>
<section class="panel"><div class="panel-header"><h2><i class="bi bi-envelope-paper"></i> Payment Status (Invoices)</h2><span class="reports-pill">{{ $statusTotal }} Invoices</span></div><div class="reports-donut-layout"><div class="reports-donut"><canvas data-invoice-status data-values='@json(array_values($statuses))' role="img" aria-label="Invoice payment status"></canvas><div class="reports-donut-center"><strong>{{ $statusTotal ? round($statuses['paid'] / $statusTotal * 100) : 0 }}%</strong><small>Fully settled</small></div></div><div class="reports-status-list">
@foreach(['paid'=>['Fully Paid / Waived','check-circle-fill'],'partial'=>['Partly Paid','clock-fill'],'unpaid'=>['Unpaid','x-circle-fill']] as $key=>$label)
<div><i class="bi bi-{{ $label[1] }} status-{{ $key }}"></i><span>{{ $label[0] }}</span><strong>{{ $statuses[$key] }}</strong><small>{{ $statusTotal ? number_format($statuses[$key] / $statusTotal * 100,1) : '0.0' }}%</small></div>
@endforeach
</div></div></section>
<section class="panel"><div class="panel-header"><h2><i class="bi bi-layers"></i> Collected vs Outstanding by Program</h2><span class="reports-bar-key"><b></b> Collected <b></b> Outstanding</span></div><div class="reports-program-bars">
@foreach($programs as $index=>$program)
@php
$collected = max(0, $s['programs'][$program] ?? 0);
$outstanding = $reportDetails[$currency]['outstanding'][$program] ?? 0;
$barMax = max(1, ...$programs->map(fn ($name) => max(0, $s['programs'][$name] ?? 0) + ($reportDetails[$currency]['outstanding'][$name] ?? 0))->all());
@endphp
@if($program !== 'Legacy / unclassified' || $collected || $outstanding)
<div class="reports-program-bar"><span>{{ $programLabels[$index] }}</span><div class="reports-bar-track"><span class="collected" style="width:{{ $collected / $barMax * 100 }}%"></span><span class="outstanding" style="width:{{ $outstanding / $barMax * 100 }}%"></span></div><strong>{{ \App\Support\Money::display($collected) }}<small>{{ \App\Support\Money::display($outstanding) }} due</small></strong></div>
@endif
@endforeach
</div><p class="reports-footnote">{{ $currency }} &middot; Current balances for issued invoices.</p></section>
</div>
@empty
<section class="panel p-4 mb-4">No invoices for this academic year. <a href="{{ route('finance.index') }}">View invoices &amp; payments</a></section>
@endforelse
