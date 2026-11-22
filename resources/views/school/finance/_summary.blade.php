@forelse($summary as $currency => $s)
    @php
        $metrics = [
            'billed' => ['Total net billed', 'database-fill', 'mint'],
            'paid' => ['Payments collected', 'wallet2', 'blue'],
            'balance' => ['Outstanding balance', 'exclamation-triangle-fill', 'amber'],
            'overdue' => ['Overdue balance', 'clock', 'rose'],
            'month' => ['Net receipts this month', 'receipt-cutoff', 'violet'],
        ];
        $currentMonth = today()->startOfMonth();
        $months = collect(range(0, $currentMonth->month - 1))->map(fn ($offset) => $currentMonth->copy()->startOfYear()->addMonths($offset));
        $labels = $months->map(fn ($month) => $month->translatedFormat('M'));
        $values = $months->map(fn ($month) => ($s['trend'][$month->format('Y-m')] ?? 0) / 100);
        $programColors = ['Khmer' => '#00a2aa', 'English' => '#249cfa', 'Chinese' => '#9169f2', 'Japan' => '#d65cf0', 'Legacy / unclassified' => '#8496ac'];
        $programAmounts = array_replace(array_fill_keys(array_keys($programColors), 0), $s['programs']);
        $programChart = collect($programAmounts)->filter(fn ($amount) => $amount > 0);
        $programTotal = $programChart->sum();
        $progress = $s['billed'] > 0 ? 100 * $s['paid'] / $s['billed'] : null;
    @endphp
    <section class="finance-overview" aria-label="{{ $currency }} overview">
        <dl class="finance-metric-grid">
            @foreach($metrics as $key => [$label, $icon, $tone])
                <div class="finance-overview-metric" data-tone="{{ $tone }}">
                    <dt><i class="bi bi-{{ $icon }} finance-overview-icon" aria-hidden="true"></i>{{ __($label) }}</dt>
                    <dd>{{ \App\Support\Money::display($s[$key]) }} <small>{{ $currency }}</small></dd>
                </div>
            @endforeach
        </dl>

        <div class="finance-collection-grid">
            <section class="panel finance-collection-panel" data-finance-trend>
                <header class="finance-collection-header">
                    <div class="finance-collection-title">
                        <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                        <div>
                            <h2>{{ __('Monthly Fee Collection') }} ({{ $currency }})</h2>
                            <p>{{ $months->count() > 1 ? $months->first()->translatedFormat('M').' – ' : '' }}{{ $currentMonth->translatedFormat('M Y') }}</p>
                        </div>
                    </div>
                    <div class="finance-chart-modes" role="group" aria-label="{{ __('Fee collection chart view') }}" hidden>
                        @foreach(['line' => 'Line', 'column' => 'Column', 'area' => 'Area'] as $mode => $label)
                            <button type="button" data-finance-mode="{{ $mode }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ __($label) }}</button>
                        @endforeach
                    </div>
                </header>
                <div class="finance-collection-chart">
                    <canvas data-finance-months data-currency="{{ $currency }}" data-year="{{ $currentMonth->year }}" data-labels='@json($labels)' data-values='@json($values)'
                        role="img" aria-label="{{ __('Monthly fee collection in :currency for :year', ['currency' => $currency, 'year' => $currentMonth->year]) }}"></canvas>
                </div>
                <details class="finance-collection-details">
                    <summary>{{ __('View monthly amounts') }}</summary>
                    <div class="finance-programs">
                        @foreach($months as $month)
                            <div><span>{{ $month->translatedFormat('M Y') }}</span><strong>{{ \App\Support\Money::display($s['trend'][$month->format('Y-m')] ?? 0) }} {{ $currency }}</strong></div>
                        @endforeach
                        @foreach(array_slice(array_filter($s['trend'], fn ($month) => $month < $currentMonth->format('Y').'-01', ARRAY_FILTER_USE_KEY), -12, null, true) as $month => $amount)
                            <div><span>{{ $month }}</span><strong>{{ \App\Support\Money::display($amount) }} {{ $currency }}</strong></div>
                        @endforeach
                    </div>
                    <p class="form-text mb-0">{{ __('Monthly totals are payments minus refunds. Opening payments are included in overall totals.') }}</p>
                </details>
            </section>

            <section class="panel finance-collection-panel">
                <header class="finance-collection-header">
                    <div class="finance-collection-title"><i class="bi bi-pie-chart" aria-hidden="true"></i><h2>{{ __('Collected by Program') }}</h2></div>
                </header>
                <div class="finance-program-chart-layout">
                    <div class="finance-program-donut">
                        <canvas data-finance-programs data-values='@json($programChart->values())'
                            data-colors='@json($programChart->keys()->map(fn ($program) => $programColors[$program] ?? "#8496ac")->values())'
                            aria-hidden="true"></canvas>
                        <div class="finance-program-donut-total">
                            <strong>{{ \App\Support\Money::display($s['paid']) }}</strong>
                            <span>{{ $currency }}</span>
                            <span>{{ __('Collected') }}</span>
                        </div>
                    </div>
                    <dl class="finance-program-legend">
                        @foreach($programAmounts as $program => $amount)
                            @continue($amount === 0 && ! in_array($program, ['Khmer', 'English', 'Chinese', 'Japan'], true))
                            <div style="--finance-program-color: {{ $programColors[$program] ?? '#8496ac' }}">
                                <dt>{{ __($program === 'Japan' ? 'Japanese' : $program) }}</dt>
                                <dd>{{ \App\Support\Money::display($amount) }} {{ $currency }}</dd>
                                <dd>{{ number_format($programTotal ? 100 * max(0, $amount) / $programTotal : 0, 1) }}%</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>
        </div>

        <section class="panel finance-progress">
            <header>
                <i class="bi bi-bullseye finance-overview-icon" aria-hidden="true"></i>
                <div><h2>{{ __('Collection Progress') }}</h2><p>{{ __('Fee collection for the selected invoices') }}</p></div>
            </header>
            <p class="finance-progress-amount"><strong>{{ \App\Support\Money::display($s['paid']) }}</strong> / {{ \App\Support\Money::display($s['billed']) }} {{ $currency }}</p>
            @if($progress !== null)
                <progress max="100" value="{{ min(100, max(0, $progress)) }}" aria-label="{{ __('Fee collection progress') }}"></progress>
                <strong class="finance-progress-percent">{{ number_format($progress, 2) }}%</strong>
            @else
                <p class="finance-progress-percent">{{ __('No net amount billed') }}</p>
            @endif
        </section>
    </section>
@empty
    <div class="panel p-4 mb-4">{{ __('No invoices match these filters.') }}</div>
@endforelse
