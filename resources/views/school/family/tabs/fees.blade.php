@php
    use App\Support\Money;
@endphp
<header class="family-fees-heading">
    <span class="family-fees-icon" aria-hidden="true"><i class="bi bi-database"></i></span>
    <div>
        <h2 id="family-tab-heading">{{ __('School Fees') }}</h2>
        <p>{{ __('Manage tuition fees, view payments, and download invoices.') }}</p>
    </div>
    <img src="{{ asset('images/student-fees-banner.png') }}" alt="" width="2172" height="724">
</header>
<div class="family-fees-content">
    @forelse($fees->groupBy('currency') as $currency => $invoices)
        @php
            $billed = $invoices->sum(fn ($fee) => $fee->netCents());
            $paid = $invoices->sum(fn ($fee) => Money::cents($fee->paid));
            $outstanding = $invoices->sum(fn ($fee) => max(0, $fee->balanceCents()));
            $paymentTotal = $paid + $outstanding;
            $percent = $paymentTotal > 0 ? (int) round(100 * $paid / $paymentTotal) : 0;
            $dueInvoice = $invoices->filter(fn ($fee) => $fee->balanceCents() > 0)->sortBy('due_date')->first();
            $invoice = $invoices->sortByDesc('id')->first();
            $receipt = $payments->firstWhere('currency', $currency);
            $canPay = $dueInvoice && $currency === 'USD' && in_array($guardian->role, ['parent', 'administrator'], true);
        @endphp
        <article class="family-fees-summary" aria-label="{{ __('Payment summary') }} {{ $currency }}">
            <div>
                <dl class="family-fees-metrics">
                    @foreach([
                        ['billed', 'wallet2', __('Total Billed'), Money::display($billed).' '.$currency],
                        ['paid', 'check-circle', __('Total Paid'), Money::display($paid).' '.$currency],
                        ['outstanding', 'hourglass-split', __('Outstanding'), Money::display($outstanding).' '.$currency],
                    ] as [$tone, $icon, $label, $value])
                        <div class="family-fees-metric" data-tone="{{ $tone }}">
                            <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                            <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        </div>
                    @endforeach
                </dl>
                <div class="family-fees-actions">
                    <dl class="family-fees-metric" data-tone="due">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        <div><dt>{{ __('Next Payment Due') }}</dt><dd>{{ $dueInvoice?->due_date->translatedFormat('M j, Y') ?? '—' }}</dd></div>
                    </dl>
                    @if($canPay)
                        <a class="btn family-fees-pay" href="{{ route('fees.pay', $dueInvoice) }}"><i class="bi bi-credit-card" aria-hidden="true"></i> {{ __('Pay Now') }}</a>
                    @elseif($dueInvoice)
                        <a class="btn family-fees-pay" href="{{ route('finance.invoice', ['fee' => $dueInvoice, 'family' => 1]) }}">{{ __('View Balance') }}</a>
                    @else
                        <button type="button" class="btn family-fees-pay" disabled><i class="bi bi-credit-card" aria-hidden="true"></i> {{ __('Pay Now') }}</button>
                    @endif
                    <a class="btn btn-outline-primary" href="{{ route('finance.invoice', ['fee' => $invoice, 'family' => 1]) }}"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> {{ __('Invoice') }}</a>
                    @if($receipt)
                        <a class="btn btn-outline-primary" href="{{ route('finance.receipt', ['payment' => $receipt, 'family' => 1]) }}"><i class="bi bi-receipt" aria-hidden="true"></i> {{ __('Receipt') }}</a>
                    @else
                        <button type="button" class="btn btn-outline-primary" disabled><i class="bi bi-receipt" aria-hidden="true"></i> {{ __('Receipt') }}</button>
                    @endif
                </div>
            </div>
            <section class="family-fees-status">
                <h3>{{ __('Payment Status') }}</h3>
                <div class="family-fees-status-body">
                    <div class="family-fees-ring" style="--fee-paid: {{ $percent }}%" aria-hidden="true">
                        <div><strong>{{ $paymentTotal > 0 ? $percent.'%' : '—' }}</strong><span>{{ __('Paid') }}</span></div>
                    </div>
                    <dl class="family-fees-key">
                        <div><dt>{{ __('Paid') }}</dt><dd>{{ Money::display($paid) }} {{ $currency }} ({{ $percent }}%)</dd></div>
                        <div><dt>{{ __('Outstanding') }}</dt><dd>{{ Money::display($outstanding) }} {{ $currency }} ({{ $paymentTotal > 0 ? 100 - $percent : 0 }}%)</dd></div>
                    </dl>
                </div>
            </section>
        </article>
    @empty
        <p>{{ __('No school fee invoices recorded.') }}</p>
    @endforelse

    <section class="family-fees-history">
        <h3><i class="bi bi-file-earmark-text" aria-hidden="true"></i> {{ __('Invoice history') }}</h3>
        <div class="table-responsive" role="region" aria-label="{{ __('Invoice history') }}" tabindex="0">
            <table class="table family-fees-table">
                <thead><tr>
                    <th scope="col">{{ __('Invoice No.') }}</th><th scope="col">{{ __('Description') }}</th>
                    <th scope="col">{{ __('Amount') }}</th><th scope="col">{{ __('Paid') }}</th>
                    <th scope="col">{{ __('Due Date') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Action') }}</th>
                </tr></thead>
                <tbody>
                    @forelse($fees as $fee)
                        <tr>
                            <th scope="row"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> {{ $fee->display_number }}</th>
                            <td>{{ $fee->description }}</td>
                            <td>{{ Money::display($fee->netCents()) }} {{ $fee->currency }}</td>
                            <td class="family-fees-paid">{{ $fee->paid }} {{ $fee->currency }}</td>
                            <td>{{ $fee->due_date->translatedFormat('M j, Y') }}</td>
                            <td><span class="family-fees-state" data-state="{{ $fee->payment_status }}">{{ __(\Illuminate\Support\Str::headline($fee->payment_status)) }}</span></td>
                            <td><a class="btn btn-sm btn-outline-primary rounded-circle" href="{{ route('finance.invoice', ['fee' => $fee, 'family' => 1]) }}" aria-label="{{ __('View invoice') }} {{ $fee->display_number }}"><i class="bi bi-eye" aria-hidden="true"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ __('No invoices yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="family-fees-history">
        <h3><i class="bi bi-receipt" aria-hidden="true"></i> {{ __('Recent payments') }}</h3>
        <div class="table-responsive" role="region" aria-label="{{ __('Recent payments') }}" tabindex="0">
            <table class="table family-fees-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Receipt No.') }}</th>
                        <th scope="col">{{ __('Payment date') }}</th>
                        <th scope="col">{{ __('Payment method') }}</th>
                        <th scope="col">{{ __('Amount') }}</th>
                        <th scope="col">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments->take(8) as $payment)
                        <tr>
                            <th scope="row"><i class="bi bi-receipt me-1" aria-hidden="true"></i>{{ $payment->receipt_number }}</th>
                            <td>{{ $payment->paid_on->translatedFormat('M j, Y') }}</td>
                            <td><span class="family-fees-state">{{ __(\Illuminate\Support\Str::headline($payment->method)) }}</span></td>
                            <td class="family-fees-paid">{{ $payment->amount }} {{ $payment->currency }}</td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('finance.receipt', ['payment' => $payment, 'family' => 1]) }}" aria-label="{{ __('View receipt') }} {{ $payment->receipt_number }}">
                                    <i class="bi bi-eye me-1" aria-hidden="true"></i>{{ __('Receipt') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ __('No payments recorded.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
