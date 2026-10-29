@extends('school.layout')
@section('title', 'Pay school fee')
@section('content')
<div class="page-heading"><div><h1>Pay school fee</h1><p>{{ $fee->student->name }} &middot; {{ $fee->student->student_number }}</p></div><a class="btn btn-light" href="{{ route('modules.index', 'fees') }}">Back to fees</a></div>
<section class="panel p-4 mx-auto" style="max-width:600px">
    <h2 class="h5">{{ $fee->description }}</h2>
    <p>Remaining balance: <strong class="fs-3">${{ $fee->balance }} USD</strong></p>
    @if((float) $fee->balance > 0)
        @if($hasQr)
            <img src="{{ route('fees.qr') }}" alt="School bank payment QR code" class="d-block mx-auto img-fluid mb-3" style="max-height:500px">
            <ol><li>Open your banking app and scan this QR (or import its saved image).</li><li>Check the recipient is <strong>VICHAKA DEN</strong>. Enter <strong>${{ $fee->balance }} USD</strong> and verify the currency and amount before paying.</li><li>Use <strong>{{ $fee->student->student_number }} / fee {{ $fee->id }}</strong> as the payment reference when supported.</li><li>Keep your receipt and give it to the school office for confirmation.</li></ol>
            <div class="alert alert-info">Scanning does not confirm payment. Your balance stays unpaid until the administrator checks the bank receipt and records the payment.</div>
        @else
            <div class="alert alert-info">The school payment QR has not been uploaded. Contact the school office before paying.</div>
        @endif
    @else
        <div class="alert alert-success">This fee has been paid. No further payment is needed.</div>
    @endif
    @if(auth()->user()->role === 'administrator')
        <hr><h2 class="h6">School payment QR</h2><p class="small text-secondary">Upload the original KHQR image for VICHAKA DEN. This image is shared across all school fees.</p>
        <form method="post" action="{{ route('fees.qr.upload') }}" enctype="multipart/form-data">@csrf<label for="qr" class="form-label">QR image (PNG or JPEG, up to 4 MB)</label><input type="file" id="qr" name="qr" class="form-control mb-3" accept="image/png,image/jpeg" required><button class="btn btn-primary">Save QR image</button></form>
        <a class="btn btn-outline-secondary mt-3" href="{{ route('modules.edit', ['fees', $fee->id]) }}">Record verified payment</a>
    @endif
</section>
@endsection
