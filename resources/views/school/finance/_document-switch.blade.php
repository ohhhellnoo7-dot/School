@php
    $onReceipt = request()->routeIs('finance.receipt');
    $switchReceipt = $onReceipt
        ? $payment
        : ($fee->payments->find(request()->integer('receipt')) ?? $fee->payments->sortBy([['paid_on', 'desc'], ['id', 'desc']])->first());
@endphp
<nav class="finance-screen-only finance-document-switch" aria-label="{{ __('Payment documents') }}">
    <a class="btn {{ $onReceipt ? 'btn-outline-primary' : 'btn-primary' }}"
        href="{{ route('finance.invoice', ['fee' => $fee, 'receipt' => $switchReceipt?->id]) }}#invoice-document"
        @unless($onReceipt) aria-current="page" @endunless><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>{{ __('Invoice') }}</a>
    @if($switchReceipt)
        <a class="btn {{ $onReceipt ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('finance.receipt', $switchReceipt) }}"
            @if($onReceipt) aria-current="page" @endif><i class="bi bi-receipt me-1" aria-hidden="true"></i>{{ __('Receipt') }}</a>
    @else
        <button type="button" class="btn btn-outline-secondary" disabled><i class="bi bi-receipt me-1" aria-hidden="true"></i>{{ __('Receipt') }}</button>
    @endif
</nav>
