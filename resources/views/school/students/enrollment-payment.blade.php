@extends('school.layout')
@section('title', 'Enrollment payment')
@section('content')
<div class="page-heading"><div><div class="eyebrow">ENROLLMENT COMPLETE</div><h1>Choose how to pay.</h1><p>{{ $student->name }} · {{ $student->student_number }}</p></div><a class="btn btn-outline-secondary" href="{{ route('students.show', $student) }}">Student profile</a></div>
<section class="panel p-4 mx-auto" style="max-width:700px">
    <h2 class="h5">Enrollment fee</h2>
    <p class="text-secondary">Enter the school’s enrollment charge, then choose Cash or KHQR. Tuition invoices are managed separately in Finance.</p>
    <form method="post" action="{{ route('students.enrollment-payment.store', $student) }}" data-enrollment-checkout>@csrf
        <label class="form-label" for="amount-usd">Enrollment amount (USD)</label>
        <input class="form-control mb-3" id="amount-usd" name="amount_usd" type="number" min="0.01" max="999999" step="0.01" value="{{ old('amount_usd') }}" required>
        <label class="form-label" for="payment-currency">Pay in</label>
        <select class="form-select mb-2" id="payment-currency" name="currency"><option value="USD" @selected(old('currency') === 'USD')>USD — US dollars</option><option value="KHR" @selected(old('currency') === 'KHR')>KHR — Cambodian riel</option></select>
        <p class="small text-secondary">1 USD = 4,100 KHR. The selected currency will be saved on the invoice.</p>
        <p class="h5 my-4" data-payment-total aria-live="polite">Enter an amount to see the total.</p>
        <fieldset class="mb-4"><legend class="h6">Payment method</legend>
            <label class="btn btn-outline-primary me-2"><input type="radio" name="method" value="cash" required @checked(old('method') === 'cash')> Cash</label>
            <label class="btn btn-outline-primary"><input type="radio" name="method" value="qr" required @checked(old('method') === 'qr')> KHQR</label>
        </fieldset>
        <button class="btn btn-primary w-100" data-continue-payment>Continue to payment</button>
    </form>
</section>
@endsection
@push('scripts')
<script src="{{ asset('js/enrollment-checkout.js') }}?v={{ filemtime(public_path('js/enrollment-checkout.js')) }}"></script>
@endpush
