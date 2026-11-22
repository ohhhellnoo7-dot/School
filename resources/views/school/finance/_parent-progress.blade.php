<div class="parent-fees-overview">
    <div class="parent-fees-progress-stack">
        @forelse($summary as $currency => $totals)
            @php
                $percent = $totals['billed'] > 0 ? min(100, max(0, round(100 * $totals['paid'] / $totals['billed'], 2))) : null;
                $amounts = [
                    ['Paid', $totals['paid'], 'check-circle-fill', 'paid'],
                    ['Pending', max(0, $totals['balance'] - $totals['overdue']), 'hourglass-split', 'pending'],
                    ['Overdue', $totals['overdue'], 'x-circle-fill', 'overdue'],
                ];
            @endphp
            <section class="parent-fees-progress" aria-label="{{ __('Fee Collection Progress') }} {{ $currency }}">
                <header>
                    <span class="parent-fees-section-icon"><i class="bi bi-bullseye" aria-hidden="true"></i></span>
                    <div><h2>{{ __('Fee Collection Progress') }}</h2><p>{{ __('Payments toward your children’s invoices') }}</p></div>
                </header>
                <div class="parent-fees-progress-body">
                    <div class="parent-fees-ring" style="--fee-progress: {{ $percent ?? 0 }}%">
                        <div>
                            <i class="bi bi-{{ $percent === 100.0 || $percent === 100 ? 'check-circle-fill' : 'wallet2' }}" aria-hidden="true"></i>
                            <strong>{{ $percent === null ? '—' : $percent.'%' }}</strong>
                            <span>{{ __('Collected') }}</span>
                        </div>
                    </div>
                    <div class="parent-fees-progress-details">
                        <p class="parent-fees-progress-total"><strong>{{ \App\Support\Money::display($totals['paid']) }}</strong> / {{ \App\Support\Money::display($totals['billed']) }} {{ $currency }}</p>
                        <dl>
                            @foreach($amounts as [$label, $amount, $icon, $tone])
                                <div data-tone="{{ $tone }}">
                                    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                                    <dt>{{ __($label) }}</dt>
                                    <dd>{{ \App\Support\Money::display($amount) }} <small>{{ $currency }}</small></dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                    <img src="{{ asset('images/parent-fees-invoice.png') }}" width="210" height="210" alt="">
                </div>
            </section>
        @empty
            <section class="parent-fees-progress">
                <h2>{{ __('Fee Collection Progress') }}</h2>
                <p class="mb-0">{{ __('No invoices match these filters.') }}</p>
            </section>
        @endforelse
    </div>

    <section class="parent-fees-quick">
        <header><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i><h2>{{ __('Quick Actions') }}</h2></header>
        <div class="parent-fees-quick-grid">
            <a data-tone="teal" href="#invoice-list"><i class="bi bi-credit-card" aria-hidden="true"></i>{{ __('View Invoices') }}</a>
            <a data-tone="blue" href="{{ $exportInvoicesUrl }}"><i class="bi bi-download" aria-hidden="true"></i>{{ __('Export Invoices') }}</a>
            @if($latestReceipt)
                <a data-tone="purple" href="{{ route('finance.receipt', $latestReceipt) }}"><i class="bi bi-printer" aria-hidden="true"></i>{{ __('Print Receipt') }}</a>
            @endif
            <a data-tone="sky" href="{{ route('students.index') }}"><i class="bi bi-people" aria-hidden="true"></i>{{ __('My Children') }}</a>
        </div>
    </section>
</div>
