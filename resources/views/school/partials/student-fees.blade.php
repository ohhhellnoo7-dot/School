@php
    $feeMetrics = [
        'billed' => ['Total Billed', 'wallet2'],
        'paid' => ['Total Paid', 'check-circle-fill'],
        'pending' => ['Pending', 'hourglass-split'],
        'balance' => ['Remaining Balance', 'wallet'],
    ];
    $feeTabs = [
        'invoices' => ['My Invoices', 'file-earmark-text'],
        'payments' => ['Payment History', 'clock'],
        'transactions' => ['Transactions', 'arrow-left-right'],
    ];
@endphp

<div class="student-timetable student-invoice student-fees">
    <header class="student-timetable-hero sf-hero">
        <span class="st-calendar-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
        <div>
            <h1>{{ __('My Fees') }}</h1>
            <p>{{ __('Manage your tuition fees, view invoices, and payment history.') }}</p>
        </div>
    </header>

    @unless($student)
        <p class="st-empty">{{ __('No student records are linked to your account. Contact your school if you need help.') }}</p>
    @endunless

    <div class="sf-metrics">
        @foreach($feeMetrics as $metric => [$label, $icon])
            <div class="si-card sf-metric" data-metric="{{ $metric }}">
                <span class="si-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                <div>
                    <span>{{ __($label) }}</span>
                    @forelse($totals as $currency => $amounts)
                        <strong>{{ \App\Support\Money::decimal($amounts[$metric]) }} <small>{{ $currency }}</small></strong>
                    @empty
                        <strong>&mdash;</strong>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <nav class="sf-tabs" aria-label="{{ __('My Fees') }}">
        @foreach($feeTabs as $tab => [$label, $icon])
            <a class="st-pill" href="{{ route('dashboard', ['section' => 'fees', 'fees_tab' => $tab]) }}" @if($feesTab === $tab) aria-current="page" @endif>
                <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ __($label) }}
            </a>
        @endforeach
    </nav>

    <section class="si-card si-panel" aria-labelledby="student-fees-title">
        <h2 id="student-fees-title"><i class="bi bi-{{ $feeTabs[$feesTab][1] }}" aria-hidden="true"></i>{{ __($feeTabs[$feesTab][0]) }}</h2>
        <ul class="sf-invoices">
            @forelse($fees as $fee)
                @php
                    $pending = $fee->checkout_submitted_at && $fee->balanceCents() > 0;
                    $status = $pending ? 'pending' : $fee->payment_status;
                @endphp
                <li class="sf-invoice" data-fee-status="{{ $status }}">
                    <div class="sf-description">
                        <span class="si-icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
                        <div>
                            <strong>{{ $fee->description }}</strong>
                            <div class="sf-meta">
                                <span>{{ __('Invoice') }} #{{ $fee->display_number }}</span>
                                @if($feesTab === 'invoices')
                                    <span>{{ __('Due: :date', ['date' => $fee->due_date?->translatedFormat('d M Y')]) }}</span>
                                @elseif($feesTab === 'transactions')
                                    <span>{{ \Illuminate\Support\Carbon::parse($fee->checkout_submitted_at)->translatedFormat('d M Y') }}</span>
                                    @if($fee->checkout_method)<span>{{ __(strtoupper($fee->checkout_method)) }}</span>@endif
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="sf-amount">
                        <span>{{ $feesTab === 'payments' ? __('Net Paid') : ($feesTab === 'transactions' ? __('Balance') : __('Amount')) }}</span>
                        <strong>{{ match ($feesTab) {
                            'payments' => $fee->paid,
                            'transactions' => $fee->balance,
                            default => \App\Support\Money::decimal($fee->netCents()),
                        } }} {{ $fee->currency }}</strong>
                    </div>
                    <div>
                        <span class="sf-status"><i class="bi bi-{{ in_array($status, ['paid', 'waived'], true) ? 'check-circle-fill' : 'clock' }}" aria-hidden="true"></i>{{ __(ucwords(str_replace('_', ' ', $status))) }}</span>
                    </div>
                    <div class="sf-actions">
                        <a class="st-pill" href="{{ route('finance.invoice', $fee) }}"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>{{ __('Invoice') }}</a>
                        @if($fee->balanceCents() > 0)
                            <a class="st-pill" href="{{ route('fees.pay', $fee) }}">{{ $pending ? __('View payment') : __('Pay') }}</a>
                        @endif
                    </div>
                    @if($feesTab === 'payments' && $fee->payments->isNotEmpty())
                        <ul class="sf-receipts">
                            @foreach($fee->payments as $payment)
                                <li><span>{{ $payment->paid_on->translatedFormat('d M Y') }} &middot; {{ $payment->amount }} {{ $fee->currency }}</span>
                                    <a href="{{ route('finance.receipt', $payment) }}">{{ __('Receipt') }} {{ $payment->receipt_number }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @empty
                <li class="st-empty">{{ match ($feesTab) {
                    'payments' => __('No payments recorded yet.'),
                    'transactions' => __('No payment submissions awaiting confirmation.'),
                    default => __('No invoices to show.'),
                } }}</li>
            @endforelse
        </ul>
        {{ $fees->links() }}
    </section>
</div>
