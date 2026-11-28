@php
    $latestInvoicePayment = $fee->payments->sortBy([['paid_on', 'desc'], ['id', 'desc']])->first();
    $settled = $fee->balanceCents() <= 0;
@endphp
<section class="mb-4 finance-document invoice-sheet scholar-invoice" id="invoice-document">
    <header class="scholar-invoice-hero">
        @include('school.finance._document-switch')
        <div class="invoice-heading">
            <div><div class="eyebrow">TUITION &amp; SCHOOL FEES</div><h1>Student Invoice</h1><p class="finance-number">{{ $fee->display_number }}</p></div>
            <span class="status-badge {{ $fee->payment_status }}"><i class="bi bi-{{ $fee->payment_status === 'paid' ? 'check-lg' : 'info-circle' }} me-1" aria-hidden="true"></i>{{ ucwords(str_replace('_', ' ', $fee->payment_status)) }}</span>
        </div>
    </header>
    <div class="invoice-overview">
        <div class="invoice-balance">
            <img src="{{ asset('images/student-fees-banner.png') }}" alt="">
            <div><span>Remaining balance</span><strong>{{ $fee->balance }} <small>{{ $fee->currency }}</small></strong><p><i class="bi bi-{{ $settled ? 'check-circle' : 'clock' }} me-1" aria-hidden="true"></i>{{ $settled ? 'This invoice is fully settled.' : 'Payment due '.$fee->due_date->format('d F Y') }}</p></div>
        </div>
        <div class="invoice-next-payment">
            <div><span><i class="bi bi-calendar2-check me-2" aria-hidden="true"></i>Next payment due</span><strong>{{ $nextPaymentDate?->format('d F Y') ?? 'No next date scheduled' }}</strong><p>{{ $nextPaymentDate ? 'Next tuition payment · '.$nextPaymentDate->format('F Y') : 'No further payment date is available for this billing calendar.' }}</p></div>
            @if($nextPaymentDate)<div class="scholar-invoice-calendar" aria-hidden="true"><span>{{ $nextPaymentDate->translatedFormat('M') }}</span><strong>{{ $nextPaymentDate->format('j') }}</strong></div>@endif
        </div>
    </div>
    <dl class="invoice-details">
        <div><dt><i class="bi bi-person" aria-hidden="true"></i> Billed to</dt><dd>{{ $fee->student->name }}<small>{{ $fee->student->student_number }}</small></dd></div>
        <div><dt><i class="bi bi-mortarboard" aria-hidden="true"></i> Program &amp; session</dt><dd>{{ $fee->program === 'Japan' ? 'Japanese' : ($fee->program ?: 'Legacy fee') }} {{ $fee->grade ? 'Grade '.$fee->grade : '' }}<small>{{ $fee->session ?: 'Session not recorded' }} · {{ $fee->academic_year ?: 'Historical invoice' }}</small></dd></div>
        <div><dt><i class="bi bi-layers" aria-hidden="true"></i> Billing plan</dt><dd>{{ $fee->payment_plan ? ucfirst($fee->payment_plan).' plan' : 'Original record retained' }}<small>Invoice due {{ $fee->due_date->format('d M Y') }}</small></dd></div>
    </dl>
    <section class="scholar-invoice-details-panel">
        <div class="invoice-section-heading"><h2><i class="bi bi-file-earmark-text me-2" aria-hidden="true"></i>Invoice Details</h2><span>Currency: {{ $fee->currency }}</span></div>
        <div class="table-responsive scholar-invoice-items"><table class="table invoice-items"><thead><tr><th>Description</th><th class="text-end">Amount ({{ $fee->currency }})</th></tr></thead><tbody>
            @forelse($fee->items as $item)<tr><td><i class="bi bi-mortarboard me-2" aria-hidden="true"></i>{{ $item->description }}</td><td class="text-end">{{ $item->amount }}</td></tr>@empty<tr><td>{{ $fee->description }}</td><td class="text-end">{{ $fee->amount }}</td></tr>@endforelse
            @foreach($fee->adjustments as $adjustment)<tr><td>{{ ucfirst($adjustment->kind) }}: {{ $adjustment->reason }}<br><small>{{ $adjustment->created_at->format('d M Y H:i') }}</small></td><td class="text-end">{{ in_array($adjustment->kind, ['discount','waiver','credit']) ? '-' : '' }}{{ $adjustment->amount }} {{ $adjustment->kind === 'refund' ? '(returned payment)' : '' }}</td></tr>@endforeach
        </tbody></table></div>
        <div class="scholar-invoice-payment">
            <div><h3><i class="bi bi-credit-card me-2" aria-hidden="true"></i>Payment Record</h3>
                @if($latestInvoicePayment)<p data-settled="{{ $settled ? 'yes' : 'no' }}"><i class="bi bi-check-circle-fill me-1" aria-hidden="true"></i> Payment received on {{ $latestInvoicePayment->paid_on->format('d F Y') }}</p>
                @elseif(\App\Support\Money::cents($fee->opening_paid ?? '0.00') > 0)<p>Historical payment recorded</p>
                @else<p>No payment recorded</p>@endif
                @if(!$settled && $fee->currency === 'USD')<a class="btn btn-sm btn-outline-primary finance-screen-only" href="{{ route('fees.pay', $fee) }}">School payment QR <i class="bi bi-arrow-up-right ms-2" aria-hidden="true"></i></a>@endif
            </div>
            <div class="scholar-invoice-amount"><span>Total Amount</span><strong>{{ \App\Support\Money::display($fee->netCents()) }} {{ $fee->currency }}</strong></div>
            <div class="finance-screen-only scholar-invoice-print" role="group" aria-label="Print / Save as PDF">
                <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-2" aria-hidden="true"></i>Print</button>
                <button type="button" class="btn btn-primary" onclick="window.print()" title="Choose Save as PDF in the print dialog"><i class="bi bi-download me-2" aria-hidden="true"></i>Save as PDF</button>
            </div>
        </div>
    </section>
    <section class="scholar-invoice-details-panel">
        <div class="invoice-section-heading"><h2>Payment history</h2><span>{{ $fee->payments->count() }} recorded {{ \Illuminate\Support\Str::plural('payment', $fee->payments->count()) }}</span></div>
        @if(\App\Support\Money::cents($fee->opening_paid ?? '0.00') > 0)<p>Historical opening payments: {{ $fee->opening_paid }} {{ $fee->currency }}. Preserved from the original fee record.</p>@endif
        <div class="table-responsive"><table class="table invoice-history"><thead><tr><th>Date</th><th>Method / reference</th><th>Amount</th><th>Receipt</th></tr></thead><tbody>
            @forelse($fee->payments as $payment)<tr><td class="text-nowrap">{{ $payment->paid_on->format('d M Y') }}</td><td><strong>{{ ucwords(str_replace('_', ' ', $payment->method)) }}</strong>@if($payment->reference)<small class="d-block text-secondary">{{ $payment->reference }}</small>@endif</td><td class="text-nowrap">{{ $payment->amount }} {{ $fee->currency }}</td><td><a class="finance-number" href="{{ route('finance.receipt', ['payment' => $payment] + ($documentContext ?? [])) }}">{{ $payment->receipt_number }}</a></td></tr>@empty<tr><td colspan="4" class="py-4 text-center text-secondary">No ledger payments recorded.</td></tr>@endforelse
        </tbody></table></div>
        <dl class="scholar-invoice-ledger"><div><dt>Original invoice</dt><dd>{{ $fee->amount }} {{ $fee->currency }}</dd></div><div><dt>Net paid</dt><dd>{{ $fee->paid }} {{ $fee->currency }}</dd></div></dl>
        <footer class="invoice-footer">Issued by {{ config('finance.school_name') }}. @if(config('finance.school_address') || config('finance.school_phone')){{ config('finance.school_address') }} {{ config('finance.school_phone') }}. @endif Receipts are issued for recorded payments.</footer>
    </section>
</section>
