@extends('school.layout')
@section('title', 'Add expense & reason')
@section('content')
<div class="page-heading">
    <div><a href="{{ route('reports') }}" class="text-decoration-none"><i class="bi bi-arrow-left" aria-hidden="true"></i> Reports</a><h1>Add expense &amp; reason</h1><p>Record money taken out and what it was used for.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('reports') }}">Cancel</a>
</div>
<div class="row g-4">
    <div class="col-lg-8"><section class="panel p-4">@include('school.partials.expense-form')</section></div>
    <div class="col-lg-4"><section class="panel p-4">
        <h2 class="h5"><i class="bi bi-wallet2 me-2" aria-hidden="true"></i>Money available</h2>
        <p class="text-secondary">{{ $cashReport['month']->format('F Y') }} &middot; All academic years</p>
        @foreach(['USD', 'KHR'] as $code)
        <div class="d-flex justify-content-between border-bottom py-3"><span>{{ $code }}</span><strong>{{ $code === 'USD' ? '$' : '' }}{{ \App\Support\Money::display($cashReport['totals'][$code]['remaining'] ?? 0) }}</strong></div>
        @endforeach
        <p class="small text-secondary mt-3 mb-0">Saving deducts the expense from the remaining receipts for its month and currency. You will return to Reports after saving.</p>
    </section></div>
</div>
@endsection
