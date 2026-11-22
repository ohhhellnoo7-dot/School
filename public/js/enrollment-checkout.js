(() => {
    const form = document.querySelector('[data-enrollment-checkout]');
    if (!form) return;
    const amount = form.querySelector('[name="amount_usd"]');
    const currency = form.querySelector('[name="currency"]');
    const button = form.querySelector('[data-continue-payment]');
    const buttonLabel = button.querySelector('[data-payment-button-label]');
    const continueLabel = buttonLabel.textContent;
    const update = () => {
        if (!amount || !currency) return;
        const cents = Math.round(Number(amount.value) * 100);
        form.querySelector('[data-payment-total]').textContent = cents > 0
            ? `Total: ${(cents * (currency.value === 'KHR' ? 4100 : 1) / 100).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${currency.value}`
            : 'Enter an amount to see the total.';
    };
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('submit', () => {
        button.disabled = true;
        buttonLabel.textContent = button.dataset.pendingLabel;
    });
    window.addEventListener('pageshow', () => {
        button.disabled = false;
        buttonLabel.textContent = continueLabel;
    });
    update();
})();
