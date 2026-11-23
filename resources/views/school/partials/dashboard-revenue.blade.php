<section class="panel dashboard-revenue h-100" aria-labelledby="dashboard-revenue-title">
    @php
        $revenue = collect($charts['revenue']);
        $peak = $revenue->max();
        $latest = $revenue->last();
        $previous = $revenue->get($revenue->count() - 2, 0);
        $change = $previous > 0 ? ($latest - $previous) / $previous * 100 : null;
        $difference = $latest - $previous;
    @endphp
    <div class="panel-header">
        <div class="dashboard-panel-title">
            <i class="dashboard-revenue-icon bi bi-coin" aria-hidden="true"></i>
            <div>
                <h2 id="dashboard-revenue-title">Monthly revenue</h2>
                <p>Net fee payments &middot; USD</p>
            </div>
        </div>
        <span class="dashboard-revenue-period"><i class="bi bi-calendar3" aria-hidden="true"></i> Last {{ $revenue->count() }} months</span>
    </div>
    <div class="dashboard-revenue-body">
        <div class="chart-wrap">
            <canvas id="revenueChart" role="img" aria-label="Monthly net fee payments in USD, {{ $revenue->map(fn ($amount, $index) => $charts['labels'][$index].' '.number_format($amount, 2))->implode(', ') }}"></canvas>
        </div>
        <dl class="dashboard-revenue-summary">
            <div>
                <dt><i class="bi bi-wallet2" aria-hidden="true"></i> Total revenue</dt>
                <dd>${{ number_format($revenue->sum(), 2) }}</dd>
            </div>
            <div>
                <dt><i class="bi bi-graph-up" aria-hidden="true"></i> vs. previous month</dt>
                <dd>
                    @if($change !== null)
                        {{ $change > 0 ? '+' : '' }}{{ number_format($change, 1) }}%
                    @else
                        {{ $difference > 0 ? '+' : ($difference < 0 ? '?' : '') }}${{ number_format(abs($difference), 2) }}
                    @endif
                </dd>
            </div>
            <div>
                <dt><i class="bi bi-calendar-check" aria-hidden="true"></i> Highest month</dt>
                <dd>{{ $revenue->contains(fn ($amount) => $amount != 0) ? $charts['labels'][$revenue->search($peak, true)].' · $'.number_format($peak, 2) : '—' }}</dd>
            </div>
        </dl>
    </div>
</section>
