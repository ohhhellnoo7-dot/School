<section class="panel mb-4 finance-document invoice-sheet">
    <header class="invoice-header">
        <div class="invoice-brand"><span class="invoice-brand-icon"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span><div><strong>{{ config('finance.school_name') }}</strong><small>{{ config('finance.school_address') }} {{ config('finance.school_phone') }}</small></div></div>
        <button class="btn btn-outline-primary finance-screen-only" onclick="window.print()"><i class="bi bi-printer me-2" aria-hidden="true"></i>Print / Save as PDF</button>
    </header>
    <div class="invoice-heading"><div><div class="eyebrow">TUITION &amp; SCHOOL FEES</div><h1>Student invoice</h1><p class="finance-number">{{ $fee->display_number }}</p></div><span class="status-badge {{ $fee->payment_status }}">{{ ucwords(str_replace('_',' ', $fee->payment_status)) }}</span></div>
    <div class="invoice-overview">
        <div class="invoice-balance"><span>Remaining balance</span><strong>{{ $fee->balance }} <small>{{ $fee->currency }}</small></strong><p>{{ $fee->balanceCents() <= 0 ? 'This invoice is fully settled.' : 'Payment due '.$fee->due_date->format('d F Y') }}</p></div>
        <div class="invoice-next-payment"><span><i class="bi bi-calendar2-check me-2" aria-hidden="true"></i>Next payment due</span><strong>{{ $nextPaymentDate?->format('d F Y') ?? 'No next date scheduled' }}</strong><p>{{ $nextPaymentDate ? 'Next tuition payment · '.$nextPaymentDate->format('F Y') : 'No further payment date is available for this billing calendar.' }}</p>@if($fee->balanceCents() > 0)<small>Current invoice balance is still due {{ $fee->due_date->format('d M Y') }}.</small>@endif</div>
    </div>
    <dl class="invoice-details">
        <div><dt>Billed to</dt><dd>{{ $fee->student->name }}</dd><small>{{ $fee->student->student_number }}</small></div>
        <div><dt>Program &amp; session</dt><dd>{{ $fee->program === 'Japan' ? 'Japanese' : ($fee->program ?: 'Legacy fee') }} {{ $fee->grade ? 'Grade '.$fee->grade : '' }}</dd><small>{{ $fee->session ?: 'Session not recorded' }} · {{ $fee->academic_year ?: 'Historical invoice' }}</small></div>
        <div><dt>Billing plan</dt><dd>{{ $fee->payment_plan ? ucfirst($fee->payment_plan).' plan' : 'Original record retained' }}</dd><small>Invoice due {{ $fee->due_date->format('d M Y') }}</small></div>
    </dl>
    <div class="invoice-section-heading"><h2>Invoice details</h2><span>{{ $fee->currency }}</span></div>
    <div class="table-responsive"><table class="table invoice-items"><thead><tr><th>Description</th><th class="text-end">Amount ({{ $fee->currency }})</th></tr></thead><tbody>
        @forelse($fee->items as $item)<tr><td>{{ $item->description }}</td><td class="text-end">{{ $item->amount }}</td></tr>@empty<tr><td>{{ $fee->description }}</td><td class="text-end">{{ $fee->amount }}</td></tr>@endforelse
        @foreach($fee->adjustments as $adjustment)<tr><td>{{ ucfirst($adjustment->kind) }}: {{ $adjustment->reason }}<br><small>{{ $adjustment->created_at->format('d M Y H:i') }}</small></td><td class="text-end">{{ in_array($adjustment->kind, ['discount','waiver','credit']) ? '-' : '' }}{{ $adjustment->amount }} {{ $adjustment->kind === 'refund' ? '(returned payment)' : '' }}</td></tr>@endforeach
    </tbody></table></div>
    <div class="invoice-totals-layout"><div class="invoice-payment-note"><i class="bi bi-shield-check" aria-hidden="true"></i><div><strong>Payment records in one place</strong><p>Keep your receipt as confirmation of payment.</p>@if($fee->balanceCents() > 0 && $fee->currency === 'USD')<a class="btn btn-sm btn-outline-primary finance-screen-only" href="{{ route('fees.pay', $fee) }}">School payment QR <i class="bi bi-arrow-up-right ms-2" aria-hidden="true"></i></a>@endif</div></div>
        <dl class="invoice-totals"><div><dt>Original invoice</dt><dd>{{ $fee->amount }} {{ $fee->currency }}</dd></div><div><dt>Net billed</dt><dd>{{ \App\Support\Money::decimal($fee->netCents()) }} {{ $fee->currency }}</dd></div><div><dt>Net paid</dt><dd>{{ $fee->paid }} {{ $fee->currency }}</dd></div><div class="invoice-total-final"><dt>Remaining balance</dt><dd>{{ $fee->balance }} {{ $fee->currency }}</dd></div></dl>
    </div>
    <div class="invoice-section-heading"><h2>Payment history</h2><span>{{ $fee->payments->count() }} recorded {{ \Illuminate\Support\Str::plural('payment', $fee->payments->count()) }}</span></div>
    @if(\App\Support\Money::cents($fee->opening_paid ?? '0.00') > 0)<p>Historical opening payments: {{ $fee->opening_paid }} {{ $fee->currency }}. Preserved from the original fee record; receipt details are unavailable.</p>@endif
    <div class="table-responsive"><table class="table invoice-history"><thead><tr><th>Date</th><th>Method / reference</th><th>Amount</th><th>Receipt</th></tr></thead><tbody>
        @forelse($fee->payments as $payment)<tr><td class="text-nowrap">{{ $payment->paid_on->format('d M Y') }}</td><td><strong>{{ ucwords(str_replace('_',' ', $payment->method)) }}</strong>@if($payment->reference)<small class="d-block text-secondary">{{ $payment->reference }}</small>@endif</td><td class="text-nowrap">{{ $payment->amount }} {{ $payment->currency }}</td><td><a class="finance-number" href="{{ route('finance.receipt', $payment) }}">{{ $payment->receipt_number }}</a></td></tr>@empty<tr><td colspan="4" class="py-4 text-center text-secondary">No ledger payments recorded.</td></tr>@endforelse
    </tbody></table></div>
    <footer class="invoice-footer">Issued by {{ config('finance.school_name') }}. This invoice is not proof of payment. Receipts are issued only for recorded payments.</footer>
</section>
