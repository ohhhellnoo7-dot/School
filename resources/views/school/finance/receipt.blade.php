@extends('school.layout')
@section('title', __('Payment Receipt'))
@section('content')
@include('school.finance._family-fees-back')
@php
    $combined = $allocations->isNotEmpty();
    $entries = ($combined ? $allocations : collect([['payment' => $payment, 'fee' => $fee]]))
        ->map(function ($entry) {
            $entry['refund'] = $entry['fee']->adjustments->where('kind', 'refund')->where('fee_payment_id', $entry['payment']->id)
                ->sum(fn ($adjustment) => \App\Support\Money::cents($adjustment->amount));
            return $entry;
        });
    $displayAmounts = fn ($amounts) => $amounts->map(fn ($amount, $currency) => \App\Support\Money::display($amount).' '.$currency)->join(' + ');
    $byCurrency = $entries->groupBy('payment.currency');
    $received = $byCurrency->map(fn ($group) => $group->sum(fn ($entry) => \App\Support\Money::cents($entry['payment']->amount)));
    $refunds = $byCurrency->map(fn ($group) => $group->sum('refund'));
    $balances = $entries->unique('fee.id')->groupBy('fee.currency')->map(fn ($group) => $group->sum(fn ($entry) => $entry['fee']->balanceCents()));
    $fullyRefunded = $entries->every(fn ($entry) => $entry['refund'] >= \App\Support\Money::cents($entry['payment']->amount));
    $settled = $entries->every(fn ($entry) => $entry['fee']->balanceCents() <= 0);
    $status = match (true) {
        $fullyRefunded => __('Payment Refunded'),
        $entries->every(fn ($entry) => $entry['fee']->payment_status === 'waived') => __('Invoice Waived'),
        $settled => __('Paid in Full'),
        default => __('Balance Due'),
    };
    $positive = $settled && ! $fullyRefunded;
    $program = $fee->program === 'Japan' ? __('Japanese') : ($fee->program ?: __('Historical fee'));
    $classInfo = collect([$program, $fee->grade ? __('Grade :grade', ['grade' => $fee->grade]) : null])->filter()->join(' ');
    $rows = [
        ['book', __('Charge'), $combined ? __('Tuition for multiple programs') : $fee->description, ''],
        ['database', __('Amount received'), $displayAmounts($received), 'amount'],
        ['calendar3', __('Payment date'), $payment->paid_on->translatedFormat('d M Y'), ''],
        ['calendar-event', $combined ? __('Invoice due dates') : __('Invoice due date'), $entries
            ->pluck('fee.due_date')->sortBy(fn ($date) => $date->toDateString())
            ->map(fn ($date) => $date->translatedFormat('d M Y'))->unique()->join(', '), ''],
        ['credit-card', __('Payment method'), __(ucwords(str_replace('_', ' ', $payment->method))), 'method'],
        ['tag', __('Reference'), $payment->reference ?: __('Not provided'), $payment->reference ? '' : 'missing'],
        ['person', __('Recorded by'), \App\Models\User::find($payment->recorded_by)?->name ?? '—', ''],
        ['arrow-return-left', __('Refunded from this payment'), $displayAmounts($refunds), ''],
        ['calculator', __('Invoice balance now'), $displayAmounts($balances), 'balance'],
    ];
@endphp
<article class="finance-document scholar-payment-receipt" aria-labelledby="payment-receipt-title">
    <header class="scholar-receipt-hero">
        <span class="scholar-receipt-hero-icon"><i class="bi bi-receipt-cutoff" aria-hidden="true"></i></span>
        <div>
            <div class="eyebrow">{{ config('finance.school_name') }}</div>
            <h1 id="payment-receipt-title">{{ __('Payment Receipt') }}</h1>
            <p>{{ __('Official record of tuition payment.') }}</p>
            @if(config('finance.school_address') || config('finance.school_phone'))
                <p class="scholar-receipt-contact">{{ config('finance.school_address') }} {{ config('finance.school_phone') }}</p>
            @endif
            @unless($combined) @include('school.finance._document-switch') @endunless
        </div>
    </header>
    <section class="scholar-receipt-summary" aria-label="{{ __('Receipt and invoice information') }}">
        <div class="scholar-receipt-toolbar">
            <p>{{ __('Receipt No.') }} <strong>{{ $payment->receipt_number }}</strong></p>
            <div class="finance-screen-only scholar-receipt-actions">
                <button type="button" class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1" aria-hidden="true"></i> {{ __('Print') }}</button>
                <button type="button" class="btn btn-primary" onclick="window.print()" title="{{ __('Choose Save as PDF in the print dialog') }}"><i class="bi bi-download me-1" aria-hidden="true"></i> {{ __('Save as PDF') }}</button>
            </div>
        </div>
        <div class="scholar-receipt-identity">
            <div>
                @include('school.partials.avatar', ['person' => $fee->student, 'size' => 66])
                <div><h2>{{ $fee->student->name }}</h2><span class="scholar-receipt-tag"><i class="bi bi-person me-1" aria-hidden="true"></i>{{ $fee->student->student_number }}</span></div>
            </div>
            @if($combined)
            <div><i class="bi bi-mortarboard scholar-receipt-identity-icon" aria-hidden="true"></i><strong>{{ __('Combined Tuition Receipt') }}</strong></div>
            <div><i class="bi bi-file-earmark-text scholar-receipt-identity-icon" aria-hidden="true"></i><strong>{{ trans_choice(':count invoice|:count invoices', $entries->unique('fee.id')->count()) }}</strong></div>
            @else
            <div>
                <i class="bi bi-mortarboard scholar-receipt-identity-icon" aria-hidden="true"></i>
                <div><strong>{{ $classInfo }}{{ $fee->session ? ' / '.$fee->session : '' }}</strong>@if($fee->academic_year)<span class="d-block">{{ $fee->academic_year }}</span>@endif</div>
            </div>
            <div>
                <i class="bi bi-file-earmark-text scholar-receipt-identity-icon" aria-hidden="true"></i>
                <div><strong class="d-block">{{ __('Invoice No.') }}</strong><a class="scholar-receipt-tag" href="{{ route('finance.invoice', ['fee' => $fee] + $documentContext) }}">{{ $fee->display_number }}</a></div>
            </div>
            @endif
        </div>
    </section>
    <section class="scholar-receipt-details" aria-label="{{ __('Payment details') }}">
        <div class="scholar-receipt-details-grid">
            <div class="scholar-receipt-charges">
            @if($combined)
                <div class="table-responsive scholar-receipt-programs mb-3">
                    <table class="table mb-0">
                        <caption class="caption-top fw-bold"><i class="bi bi-book me-1" aria-hidden="true"></i> {{ __('Tuition covered by this receipt') }}</caption>
                        <thead><tr><th scope="col">{{ __('Program / Charge') }}</th><th scope="col">{{ __('Invoice') }}</th><th scope="col" class="text-nowrap">{{ __('Invoice due date') }}</th><th scope="col" class="text-end">{{ __('Amount received') }}</th></tr></thead>
                        <tbody>
                            @foreach($entries as $entry)
                                <tr>
                                    <td>{{ $entry['fee']->description }}</td>
                                    <td><a href="{{ route('finance.invoice', ['fee' => $entry['fee'], 'receipt' => $entry['payment']->id] + $documentContext) }}#invoice-document">{{ $entry['fee']->display_number }}</a></td>
                                    <td class="text-nowrap"><time datetime="{{ $entry['fee']->due_date->toDateString() }}">{{ $entry['fee']->due_date->translatedFormat('d M Y') }}</time></td>
                                    <td class="text-end text-nowrap">{{ \App\Support\Money::display(\App\Support\Money::cents($entry['payment']->amount)) }} {{ $entry['payment']->currency }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <dl class="scholar-receipt-fields" data-settled="{{ $settled ? 'yes' : 'no' }}">
                @foreach($rows as [$icon, $label, $value, $tone])
                    @continue($combined && $icon === 'book')
                    <div data-tone="{{ $tone }}"><dt><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ $label }}</dt><dd><span>{{ $value }}</span></dd></div>
                @endforeach
            </dl>
            </div>
            <aside class="scholar-receipt-status" data-settled="{{ $positive ? 'yes' : 'no' }}" aria-label="{{ __('Payment status') }}">
                <div class="scholar-receipt-status-stage">
                    <div class="scholar-receipt-status-icon">
                        @if($positive)
                            <svg class="scholar-receipt-check" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="m13 24 8 8 14-16" pathLength="1" fill="none" stroke="currentColor"
                                    stroke-width="3.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @else
                            <i class="bi bi-{{ $fullyRefunded ? 'arrow-return-left' : 'clock' }}" aria-hidden="true"></i>
                        @endif
                    </div>
                </div>
                <strong>{{ $status }}</strong>
                <div class="scholar-receipt-status-art" aria-hidden="true">
                    <img src="{{ asset('images/student-fees-banner.png') }}" alt="">
                </div>
            </aside>
        </div>
        <p class="scholar-receipt-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>{{ __('Authorized payment record issued by :school. Original payment details are retained; later refunds and adjustments appear in the invoice history.', ['school' => config('finance.school_name')]) }}</span></p>
    </section>
</article>
@endsection

@if($positive)
    @push('scripts')
        <script src="{{ asset('js/receipt-status.js') }}?v={{ filemtime(public_path('js/receipt-status.js')) }}" defer></script>
    @endpush
@endif
