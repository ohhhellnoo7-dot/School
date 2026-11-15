@extends('school.layout')
@section('title', 'Pay school fee')
@section('content')
@php($paid = $fee->balanceCents() <= 0)
@php($pending = !$paid && $fee->checkout_submitted_at)
@php($method = old('method', $fee->checkout_method ?: 'cash'))
<div class="page-heading"><div><div class="eyebrow">SCHOOL PAYMENT</div><h1>{{ $paid ? 'Payment confirmed.' : ($pending ? 'Awaiting confirmation.' : 'Complete your payment.') }}</h1><p>{{ $fee->student->name }} · {{ $fee->student->student_number }}</p></div><a class="btn btn-outline-secondary" href="{{ route('students.show', $fee->student) }}">Student profile</a></div>
<section class="panel p-4 mx-auto" style="max-width:720px" data-fee-checkout data-status-url="{{ route('fees.status', $fee) }}" data-pending="{{ $pending ? '1' : '0' }}">
    <ol class="d-flex flex-wrap gap-4 small mb-4" aria-label="Payment progress"><li>Choose method ✓</li><li>{{ $paid || $pending ? 'Payment submitted ✓' : 'Pay by Cash or KHQR' }}</li><li>{{ $paid ? 'Confirmed ✓' : 'Administrator confirmation' }}</li></ol>
    <h2 class="h5">{{ $fee->description }}</h2>
    <p class="text-secondary">Invoice {{ $fee->display_number }}</p>
    <p>{{ $paid ? 'Paid amount' : 'Remaining balance' }}: <strong class="fs-3">{{ $fee->currency === 'USD' ? '$' : '' }}{{ $paid ? $fee->paid : $fee->balance }} {{ $fee->currency }}</strong></p>
    @if($paid)
        <div class="alert alert-success" role="status"><i class="bi bi-check-circle me-2"></i>Payment verified and confirmed by the school.</div>
        @foreach($fee->payments as $payment)
            <p>{{ $payment->method === 'qr' ? 'KHQR' : ucfirst($payment->method) }} · {{ $payment->amount }} {{ $payment->currency }} · {{ $payment->paid_on->format('d M Y') }}<br><a href="{{ route('finance.receipt', $payment) }}">View receipt {{ $payment->receipt_number }}</a></p>
        @endforeach
        <a class="btn btn-primary" href="{{ route('students.show', $fee->student) }}">Done — student profile</a>
    @else
        <p class="small text-secondary">Scanning does not confirm payment. The school verifies receipt of funds before marking the invoice paid.</p>
        @if($pending)
            <div class="alert alert-info" role="status"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>{{ $fee->checkout_method === 'qr' ? 'KHQR payment reported. The administrator must check the bank transaction.' : 'Cash payment submitted. The administrator must confirm cash was received.' }} This page checks for confirmation automatically.</div>
            @if($fee->checkout_reference)<p>Reported reference: <strong>{{ $fee->checkout_reference }}</strong></p>@endif
            <p class="small text-secondary" data-status-message aria-live="polite"></p>
        @endif
        @if(!$pending || auth()->user()->role === 'administrator')
        <form method="post" action="{{ route('fees.checkout', $fee) }}" data-method-form>@csrf
            <fieldset class="my-4"><legend class="h6">Pay by</legend>
                <label class="btn btn-outline-primary me-2"><input type="radio" name="method" value="cash" @checked($method === 'cash')> Cash</label>
                <label class="btn btn-outline-primary"><input type="radio" name="method" value="qr" @checked($method === 'qr')> KHQR</label>
            </fieldset>
            <div data-cash-panel @if($method !== 'cash') hidden @endif><h3 class="h6">Pay cash at the school office</h3><p>Hand <strong>{{ $fee->balance }} {{ $fee->currency }}</strong> to the administrator, then submit for confirmation.</p></div>
            <div data-qr-panel @if($method !== 'qr') hidden @endif>
                @if($hasQr)
                    <img src="{{ route('fees.qr') }}" alt="School KHQR for VICHAKA DEN" class="d-block mx-auto img-fluid mb-3" style="max-height:500px">
                    <ol><li>Scan or import this KHQR in your banking app.</li><li>Check the recipient is <strong>VICHAKA DEN</strong> and choose <strong>{{ $fee->currency }}</strong>.</li><li>Enter exactly <strong>{{ $fee->balance }} {{ $fee->currency }}</strong> and complete the transfer.</li><li>Keep your receipt. Use <strong>{{ $fee->student->student_number }} / fee {{ $fee->id }}</strong> as the reference when supported.</li></ol>
                @else
                    <div class="alert alert-warning">Upload the current school KHQR below before starting a QR payment.</div>
                @endif
                <label class="form-label" for="transaction-reference">Bank transaction reference (if available)</label>
                <input class="form-control mb-3" id="transaction-reference" name="reference" maxlength="255" value="{{ old('reference', $fee->checkout_reference) }}">
                <p class="small text-secondary">The school checks its bank account before confirming your payment.</p>
            </div>
            <button class="btn btn-primary w-100" data-submit-payment data-has-qr="{{ $hasQr ? '1' : '0' }}">Submit payment for confirmation</button>
        </form>
        @endif
        @if(auth()->user()->role === 'administrator' && $pending)
            <hr><h2 class="h5">Administrator verification</h2>
            <form method="post" action="{{ route('fees.confirm', $fee) }}" data-confirm-payment>@csrf
                <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                @if($fee->checkout_method === 'qr')
                    <label class="form-label" for="verified-reference">Verified bank transaction reference</label>
                    <input id="verified-reference" class="form-control mb-3" name="reference" value="{{ old('reference', $fee->checkout_reference) }}" maxlength="255" required>
                @endif
                <div class="form-check mb-3"><input class="form-check-input" id="verified-received" type="checkbox" name="verified" value="1" required><label class="form-check-label" for="verified-received">{{ $fee->checkout_method === 'qr' ? 'I checked the bank account, recipient, currency and amount, and verified this transaction was received.' : 'I received and counted the cash in the invoice currency.' }}</label></div>
                <button class="btn btn-success w-100">Confirm received & create receipt</button>
            </form>
        @endif
    @endif
    @if(auth()->user()->role === 'administrator' && !$paid)
        <hr><details @if(!$hasQr) open @endif><summary class="h6">School KHQR image</summary><p class="small text-secondary mt-2">Upload your original VICHAKA DEN KHQR image. Use a reusable QR without a fixed amount. The same image is displayed for all school fees.</p>
            <form method="post" action="{{ route('fees.qr.upload') }}" enctype="multipart/form-data">@csrf<label for="qr" class="form-label">KHQR image (PNG or JPEG, up to 4 MB)</label><input type="file" id="qr" name="qr" class="form-control mb-3" accept="image/png,image/jpeg" required><button class="btn btn-outline-primary">Save school KHQR</button></form>
        </details>
    @endif
</section>
@endsection
@push('scripts')
<script src="{{ asset('js/fee-checkout.js') }}?v={{ filemtime(public_path('js/fee-checkout.js')) }}"></script>
@endpush
