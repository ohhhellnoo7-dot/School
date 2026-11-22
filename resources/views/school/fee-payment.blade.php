@extends('school.layout')
@section('title', 'Pay school fee')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/enrollment-payment.css') }}?v={{ filemtime(public_path('css/enrollment-payment.css')) }}">
@endpush

@section('content')
@php
    $paid = $fee->balanceCents() <= 0;
    $pending = ! $paid && $fee->checkout_submitted_at;
    $admin = auth()->user()->role === 'administrator';
    $method = old('method', $fee->checkout_method ?: 'cash');
    $step = $paid ? 4 : ($pending ? 3 : 2);
    $money = fn ($amount) => ($fee->currency === 'USD' ? '$' : '').$amount.' '.$fee->currency;
@endphp
<div class="enrollment-payment" data-fee-checkout data-status-url="{{ route('fees.status', $fee) }}" data-pending="{{ $pending ? '1' : '0' }}">
    <header class="enrollment-hero fee-payment-hero">
        <img class="enrollment-hero-art" src="{{ asset('images/enrollment-payment-hero.png') }}" alt="">
        <div class="enrollment-heading">
            <div class="enrollment-status"><i class="bi {{ $paid ? 'bi-check-lg' : 'bi-credit-card' }}" aria-hidden="true"></i> {{ __('School payment') }}</div>
            <h1>{{ $paid ? __('Payment confirmed.') : ($pending ? __('Awaiting confirmation.') : __('Complete your payment.')) }}</h1>
        </div>
        <div class="enrollment-student-row">
            <div class="enrollment-student">
                @include('school.partials.avatar', ['person' => $fee->student, 'size' => 62])
                <div><strong>{{ $fee->student->name }}</strong><small>{{ $fee->student->student_number }}</small></div>
            </div>
            <a class="enrollment-profile" href="{{ route('students.show', $fee->student) }}"><i class="bi bi-person" aria-hidden="true"></i> {{ __('Student profile') }}</a>
        </div>
    </header>

    <ol class="fee-payment-progress" aria-label="{{ __('Payment progress') }}">
        @foreach([__('Choose method'), __('Pay by Cash or KHQR'), __('Administrator confirmation')] as $label)
            <li @class(['is-complete' => $loop->iteration < $step]) @if($loop->iteration === $step) aria-current="step" @endif>
                <span class="fee-payment-step">
                    @if($loop->iteration < $step)
                        <i class="bi bi-check-lg" aria-hidden="true"></i><span class="visually-hidden">{{ __('Complete') }}</span>
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
                <span>{{ $label }}</span>
            </li>
        @endforeach
    </ol>

    <div class="enrollment-payment-grid fee-payment-grid">
        <section class="enrollment-card" aria-labelledby="fee-invoice-title">
            <div class="enrollment-card-heading enrollment-tuition-heading">
                <span class="enrollment-icon enrollment-icon-fee"><i class="bi bi-receipt" aria-hidden="true"></i></span>
                <div>
                    <h2 id="fee-invoice-title">{{ $fee->description }}</h2>
                    <p class="fee-invoice-number">{{ __('Invoice') }} {{ $fee->display_number }}</p>
                </div>
            </div>
            @if($fee->items->isNotEmpty())
                <table class="enrollment-tuition" aria-label="{{ __('Invoice items') }}">
                    <tbody>
                        @foreach($fee->items as $item)
                            <tr @class(['enrollment-tuition-alternate' => $loop->even])>
                                <th scope="row">{{ $item->description }}</th>
                                <td>{{ $item->amount }} {{ $fee->currency }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <div @class(['enrollment-total fee-payment-total', 'fee-payment-total-due' => !$paid])>
                <span>{{ $paid ? __('Paid amount') : __('Remaining balance') }}</span>
                <strong>{{ $money($paid ? $fee->paid : $fee->balance) }}</strong>
            </div>
        </section>

        <section class="enrollment-card" aria-labelledby="fee-payment-title">
            <div class="enrollment-card-heading">
                <span class="enrollment-icon"><i class="bi {{ $paid ? 'bi-check-circle' : ($pending ? 'bi-clock-history' : 'bi-wallet2') }}" aria-hidden="true"></i></span>
                <div>
                    <h2 id="fee-payment-title">{{ $paid ? __('Payment receipts') : ($pending ? __('Confirmation status') : __('Payment method')) }}</h2>
                    @if(!$paid && !$pending)<p>{{ __('Choose how to pay, then submit for confirmation.') }}</p>@endif
                </div>
            </div>
            @if($paid)
                <p class="fee-payment-message fee-payment-success" role="status">{{ __('Payment verified and confirmed by the school.') }}</p>
                <ul class="fee-payment-receipts">
                    @foreach($fee->payments as $payment)
                        <li>
                            <span>{{ $payment->method === 'qr' ? 'KHQR' : ucfirst($payment->method) }} · {{ $payment->amount }} {{ $payment->currency }} · {{ $payment->paid_on->format('d M Y') }}</span>
                            <a href="{{ route('finance.receipt', $payment) }}"><i class="bi bi-receipt" aria-hidden="true"></i> {{ __('View receipt') }} {{ $payment->receipt_number }}</a>
                        </li>
                    @endforeach
                </ul>
                <a class="enrollment-continue text-decoration-none" href="{{ route('students.show', $fee->student) }}">{{ __('Done — student profile') }}</a>
            @else
                @if($pending)
                    <div class="fee-payment-message" role="status">
                        <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>{{ $fee->checkout_method === 'qr' ? __('KHQR payment reported. The administrator must check the bank transaction.') : __('Cash payment submitted. The administrator must confirm cash was received.') }}
                    </div>
                    @if($fee->checkout_reference)<p>{{ __('Reported reference') }} <strong class="text-break">{{ $fee->checkout_reference }}</strong></p>@endif
                    <p class="enrollment-help small" data-status-message aria-live="polite">{{ __('This page checks for confirmation automatically.') }}</p>
                @endif

                @if(!$pending || $admin)
                    @if($pending)<details class="fee-payment-details"><summary>{{ __('Update payment submission') }}</summary>@endif
                    <form method="post" action="{{ route('fees.checkout', $fee) }}" data-method-form>
                        @csrf
                        @include('school.partials.payment-method-options', ['method' => $method])
                        <div class="fee-payment-instructions" data-cash-panel @if($method !== 'cash') hidden @endif>
                            <h3>{{ __('Pay cash at the school office') }}</h3>
                            <p>{{ __('Hand :amount to the administrator, then submit for confirmation.', ['amount' => $money($fee->balance)]) }}</p>
                        </div>
                        <div class="fee-payment-instructions" data-qr-panel @if($method !== 'qr') hidden @endif>
                            @if($hasQr)
                                <img src="{{ route('fees.qr') }}" alt="School KHQR for VICHAKA DEN" class="fee-payment-qr">
                                <ol>
                                    <li>{{ __('Scan or import this KHQR in your banking app.') }}</li>
                                    <li>{{ __('Check the recipient is :recipient and choose :currency.', ['recipient' => 'VICHAKA DEN', 'currency' => $fee->currency]) }}</li>
                                    <li>{{ __('Enter exactly :amount and complete the transfer.', ['amount' => $money($fee->balance)]) }}</li>
                                    <li>{{ __('Keep your receipt and use :reference when your bank supports references.', ['reference' => $fee->student->student_number.' / fee '.$fee->id]) }}</li>
                                </ol>
                            @else
                                <p class="fee-payment-message">{{ $admin ? __('Upload the current school KHQR below before starting a QR payment.') : __('The school has yet to set up its KHQR image. Choose Cash or contact the school.') }}</p>
                            @endif
                            <label class="form-label" for="transaction-reference">{{ __('Bank transaction reference (if available)') }}</label>
                            <input class="form-control mb-2" id="transaction-reference" name="reference" maxlength="255" value="{{ old('reference', $fee->checkout_reference) }}" @disabled($method !== 'qr')>
                            <p class="enrollment-help small">{{ __('Scanning does not confirm payment. The school checks its bank account before confirming your payment.') }}</p>
                        </div>
                        <button class="enrollment-continue" type="submit" data-submit-payment data-has-qr="{{ $hasQr ? '1' : '0' }}" data-cash-label="{{ __('Cash handed over — submit for confirmation') }}" data-qr-label="{{ __('I have sent the payment — submit for review') }}" data-processing-label="{{ __('Processing…') }}" @disabled($method === 'qr' && !$hasQr)>
                            <i class="bi bi-arrow-right-circle" aria-hidden="true"></i>
                            <span data-fee-button-label>{{ $method === 'qr' ? __('I have sent the payment — submit for review') : __('Cash handed over — submit for confirmation') }}</span>
                        </button>
                    </form>
                    @if($pending)</details>@endif
                @endif

                @if($admin && $pending)
                    <div class="fee-payment-admin">
                        <h3>{{ __('Administrator verification') }}</h3>
                        <form method="post" action="{{ route('fees.confirm', $fee) }}" data-confirm-payment>
                            @csrf
                            <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                            @if($fee->checkout_method === 'qr')
                                <label class="form-label" for="verified-reference">{{ __('Verified bank transaction reference') }}</label>
                                <input id="verified-reference" class="form-control mb-3" name="reference" value="{{ old('reference', $fee->checkout_reference) }}" maxlength="255" required>
                            @endif
                            <div class="form-check mb-3">
                                <input class="form-check-input" id="verified-received" type="checkbox" name="verified" value="1" required>
                                <label class="form-check-label" for="verified-received">{{ $fee->checkout_method === 'qr' ? __('I checked the bank account, recipient, currency and amount, and verified this transaction was received.') : __('I received and counted the cash in the invoice currency.') }}</label>
                            </div>
                            <button class="enrollment-continue" type="submit" data-processing-label="{{ __('Processing…') }}"><i class="bi bi-check-circle" aria-hidden="true"></i><span data-fee-button-label>{{ __('Confirm received & create receipt') }}</span></button>
                        </form>
                    </div>
                @endif
            @endif
            @if($admin && !$paid)
                <details class="fee-payment-details" @if(!$hasQr) open @endif>
                    <summary>{{ __('School KHQR image') }}</summary>
                    <p class="enrollment-help small">{{ __('Upload your original VICHAKA DEN KHQR image. Use a reusable QR without a fixed amount. The same image is displayed for all school fees.') }}</p>
                    <form method="post" action="{{ route('fees.qr.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <label for="qr" class="form-label">{{ __('KHQR image (PNG or JPEG, up to 4 MB)') }}</label>
                        <input type="file" id="qr" name="qr" class="form-control mb-3" accept="image/png,image/jpeg" required>
                        <button class="btn btn-outline-primary">{{ __('Save school KHQR') }}</button>
                    </form>
                </details>
            @endif
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/fee-checkout.js') }}?v={{ filemtime(public_path('js/fee-checkout.js')) }}"></script>
@endpush
