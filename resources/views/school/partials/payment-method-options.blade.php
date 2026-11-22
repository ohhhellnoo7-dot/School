<fieldset class="enrollment-methods">
    <legend class="visually-hidden">{{ __('Payment method') }}</legend>
    @foreach(['cash' => ['Cash', 'cash-stack'], 'qr' => ['KHQR', 'qr-code']] as $value => [$label, $icon])
        <label class="enrollment-method">
            <input class="visually-hidden" type="radio" name="method" value="{{ $value }}" required @checked($method === $value)>
            <span class="enrollment-method-option">
                <span class="enrollment-method-indicator" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                <i class="bi bi-{{ $icon }} enrollment-method-icon" aria-hidden="true"></i>
                <span>{{ __($label) }}</span>
            </span>
        </label>
    @endforeach
</fieldset>
