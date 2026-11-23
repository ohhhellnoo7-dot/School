@php($money = fn ($cents) => \App\Support\Money::display($cents))
<section class="student-payment-invoice" id="student-payment-invoice" aria-labelledby="student-payment-invoice-title">
    <header class="student-payment-identity">
        @include('school.partials.avatar', ['person' => $student, 'size' => 76])
        <div>
            <div class="student-payment-identity-name"><h2>{{ $student->name }}</h2><span>{{ $student->student_number }}</span></div>
            <p>{{ __('Student Payment Details') }}</p>
        </div>
        <img src="{{ asset('images/student-fees-banner.png') }}" alt="">
    </header>
    <header class="student-payment-invoice-banner">
        <span class="student-payment-invoice-seal"><i class="bi bi-check-lg" aria-hidden="true"></i></span>
        <div>
            <h3 id="student-payment-invoice-title">{{ $receipts->isNotEmpty() ? __('Payment Recorded') : __('Tuition Coverage Recorded') }}</h3>
            @if($receipts->isNotEmpty())
                <p>{{ __('Receipt generated successfully.') }}</p>
                <span class="student-payment-invoice-date"><i class="bi bi-calendar3" aria-hidden="true"></i> {{ $student->name }} · {{ $receipts->max('paid_on')->translatedFormat('F j, Y') }}</span>
            @endif
        </div>
        @foreach($receipts->groupBy('currency') as $currency => $payments)
            <div class="student-payment-invoice-total"><small>{{ __('Payment Received') }}</small><strong>{{ $money($payments->sum(fn ($payment) => \App\Support\Money::cents($payment->amount))) }} <span>{{ $currency }}</span></strong></div>
        @endforeach
    </header>
    <div class="student-payment-invoice-grid">
        @foreach($invoices as $invoice)
            @php($settled = $invoice->balanceCents() <= 0)
            <article class="student-payment-invoice-document">
                <header>
                    <span class="student-payment-document-icon"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></span>
                    <div><h4>{{ $invoice->program ? __($invoice->program).' · '.__('Tuition Invoice') : __('Tuition Invoice') }}</h4><small>{{ $invoice->display_number }}</small></div>
                    @if($settled)
                        <span class="student-payment-paid"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> {{ $invoice->netCents() > 0 ? __('Paid in Full') : __('No Balance Due') }}</span>
                    @endif
                </header>
                <dl class="student-payment-invoice-items">
                        @foreach($invoice->items as $item)
                            <div><dt><i class="bi bi-mortarboard" aria-hidden="true"></i> {{ $item->description }}</dt><dd>{{ $money(\App\Support\Money::cents($item->amount)) }} {{ $invoice->currency }}</dd></div>
                        @endforeach
                    <div class="student-payment-invoice-balance" @if($settled) data-settled @endif><dt><i class="bi bi-database" aria-hidden="true"></i> {{ __('Balance') }}</dt><dd>{{ $money($invoice->balanceCents()) }} {{ $invoice->currency }}</dd></div>
                </dl>
                <footer>
                    <a class="btn btn-primary" href="{{ route('finance.invoice', $invoice) }}"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>{{ __('Open invoice') }}</a>
                </footer>
            </article>
        @endforeach
    </div>
    @if($receipts->isNotEmpty())
        <a class="btn btn-primary mt-3" href="{{ route('finance.receipt', $receipts->sortBy('id')->first()) }}"><i class="bi bi-receipt me-1" aria-hidden="true"></i>{{ __('View receipt') }}</a>
    @endif
</section>
