@php
    $payments = $fees->flatMap->payments->sortByDesc('paid_on')->take(8);
@endphp
<div class="family-fee-grid">
    @forelse($fees->groupBy('currency') as $currency => $invoices)
        @php
            $due = $invoices->filter(fn ($fee) => $fee->balanceCents() > 0)->sortBy('due_date')->first()?->due_date;
        @endphp
        <article class="family-fee-summary">
            <h3>{{ $currency }}</h3>
            <dl class="family-facts">
                <div><dt>{{ __('Total billed') }}</dt><dd>{{ \App\Support\Money::display($invoices->sum(fn ($fee) => $fee->netCents())) }}</dd></div>
                <div><dt>{{ __('Total paid') }}</dt><dd>{{ \App\Support\Money::display($invoices->sum(fn ($fee) => \App\Support\Money::cents($fee->paid))) }}</dd></div>
                <div><dt>{{ __('Outstanding') }}</dt><dd>{{ \App\Support\Money::display($invoices->sum(fn ($fee) => max(0, $fee->balanceCents()))) }}</dd></div>
                <div><dt>{{ __('Next payment due') }}</dt><dd>{{ $due?->translatedFormat('M j, Y') ?? '—' }}</dd></div>
            </dl>
        </article>
    @empty
        <p>{{ __('No school fee invoices recorded.') }}</p>
    @endforelse
</div>
<h3>{{ __('Invoice history') }}</h3>
<ul class="family-records">
    @forelse($fees as $fee)
        <li>
            <div><strong>{{ $fee->description }}</strong><small>{{ $fee->display_number }} · {{ __('Due') }} {{ $fee->due_date->translatedFormat('M j, Y') }}</small></div>
            <span class="family-pill">{{ $fee->balance }} {{ $fee->currency }} {{ __('due') }}</span>
            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('finance.invoice', ['fee' => $fee, 'family' => 1]) }}">
                <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>{{ __('Invoice') }}
            </a>
        </li>
    @empty
        <li>{{ __('No invoices yet.') }}</li>
    @endforelse
</ul>
<h3 class="mt-4">{{ __('Recent payments') }}</h3>
<ul class="family-records">
    @forelse($payments as $payment)
        <li>
            <div class="text-break"><strong>{{ $payment->receipt_number }}</strong><small>{{ $payment->paid_on->translatedFormat('M j, Y') }} · {{ __(\Illuminate\Support\Str::headline($payment->method)) }}</small></div>
            <span class="family-pill">{{ $payment->amount }} {{ $payment->currency }}</span>
            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('finance.receipt', ['payment' => $payment, 'family' => 1]) }}">
                <i class="bi bi-receipt me-1" aria-hidden="true"></i>{{ __('Receipt') }}
            </a>
        </li>
    @empty
        <li>{{ __('No payments recorded.') }}</li>
    @endforelse
</ul>
