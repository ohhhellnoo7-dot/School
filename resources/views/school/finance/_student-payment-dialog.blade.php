<dialog class="student-payment-loading" data-payment-loading data-state="loading" aria-labelledby="student-payment-loading-title">
    <div class="payment-dialog-content">
        <div class="payment-dialog-illustration" aria-hidden="true">
            <img class="payment-dialog-reference-art" src="{{ asset('images/payment-printer-reference.png') }}" width="800" height="421" alt="" decoding="async">
            @include('school.finance._payment-illustration')
        </div>
        <i class="bi payment-dialog-outcome" aria-hidden="true" hidden></i>
        <h2 id="student-payment-loading-title">{{ __('Recording your payment') }}</h2>
        <p data-payment-status role="status" aria-live="polite">{{ __('Preparing the tuition invoice and receipt...') }}</p>
        <div class="payment-dialog-progress">
            <span class="payment-dialog-progress-badge" aria-hidden="true">{{ __('Processing') }}</span>
            <div class="student-payment-loading-track" role="progressbar" aria-label="{{ __('Waiting for payment confirmation') }}"><span></span></div>
        </div>
        <ol class="payment-dialog-steps" aria-label="{{ __('Payment steps') }}">
            @foreach(['file-earmark-text' => __('Generate Invoice'), 'credit-card' => __('Record Payment'), 'receipt' => __('Prepare Receipt')] as $icon => $label)
                <li><span class="payment-step-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><i class="bi bi-check payment-step-check" aria-hidden="true" hidden></i></span>
                    {{ $label }}<span class="visually-hidden" data-step-status></span>
                </li>
            @endforeach
        </ol>
        <div class="payment-dialog-actions" hidden>
            <a class="btn btn-primary" data-payment-receipt hidden>{{ __('View Receipt') }}</a>
            <button type="button" class="btn btn-primary" data-payment-retry hidden>{{ __('Retry payment') }}</button>
            <button type="button" class="btn btn-outline-primary" data-payment-close>{{ __('Close') }}</button>
        </div>
    </div>
    <svg class="payment-dialog-waves" aria-hidden="true" viewBox="0 0 960 150" preserveAspectRatio="none">
        <defs>
            <linearGradient id="payment-dialog-wave" x2="1" y2=".5">
                <stop stop-color="var(--payment-wave-one)"/>
                <stop offset=".54" stop-color="var(--payment-wave-two)"/>
                <stop offset="1" stop-color="var(--payment-wave-one)"/>
            </linearGradient>
        </defs>
        <path d="M0 0c119 11 137 148 264 132 99-12 149-33 234 0 58 23 93 5 136-7 97-27 161 13 232-43 40-31 63-53 94-53v121H0Z" fill="var(--payment-wave-one)" opacity=".3"/>
        <path d="M0 48c95-9 127 103 251 85 77-12 131-40 213-10 112 42 144 28 226-2 92-34 142 11 210-36 30-20 40-27 60-22v87H0Z" fill="url(#payment-dialog-wave)" opacity=".76"/>
        <path d="M0 48c95-9 127 103 251 85 77-12 131-40 213-10 112 42 144 28 226-2 92-34 142 11 210-36 30-20 40-27 60-22" stroke="var(--payment-rim)" stroke-width="1.6" fill="none"/>
    </svg>
</dialog>
