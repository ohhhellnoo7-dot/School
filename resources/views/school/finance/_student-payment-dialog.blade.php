<dialog class="student-payment-loading" data-payment-loading data-state="loading" aria-labelledby="student-payment-loading-title">
    <div class="payment-dialog-content">
        @include('school.finance._payment-illustration')
        <i class="bi payment-dialog-outcome" aria-hidden="true" hidden></i>
        <h2 id="student-payment-loading-title">{{ __('Recording your payment') }}</h2>
        <p data-payment-status role="status" aria-live="polite">{{ __('Preparing the tuition invoice and receipt...') }}</p>
        <div class="student-payment-loading-track" role="progressbar" aria-label="{{ __('Waiting for payment confirmation') }}"><span></span></div>
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
    <svg class="payment-dialog-waves" aria-hidden="true" viewBox="0 0 720 80" preserveAspectRatio="none">
        <path d="M0 21c105 0 106 63 232 45s148-46 258-22S611 7 720 12v68H0Z" fill="currentColor" opacity=".12"/>
        <path d="M0 43c112-8 134 48 254 24s151-8 225 1S635 15 720 26v54H0Z" fill="currentColor" opacity=".23"/>
    </svg>
</dialog>
