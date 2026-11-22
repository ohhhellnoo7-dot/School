(() => {
    const page = document.querySelector('[data-fee-checkout]');
    if (!page) return;
    const form = page.querySelector('[data-method-form]');
    const update = () => {
        if (!form) return;
        const qr = form.querySelector('[name="method"]:checked')?.value === 'qr';
        form.querySelector('[data-cash-panel]').hidden = qr;
        form.querySelector('[data-qr-panel]').hidden = !qr;
        form.querySelector('[name="reference"]').disabled = !qr;
        const button = form.querySelector('[data-submit-payment]');
        button.disabled = qr && button.dataset.hasQr !== '1';
        button.querySelector('[data-fee-button-label]').textContent = qr ? button.dataset.qrLabel : button.dataset.cashLabel;
    };
    form?.addEventListener('change', update);
    update();
    page.querySelectorAll('[data-method-form], [data-confirm-payment]').forEach(paymentForm => {
        paymentForm.addEventListener('submit', () => {
            const button = paymentForm.querySelector('button');
            button.disabled = true;
            button.querySelector('[data-fee-button-label]').textContent = button.dataset.processingLabel;
        });
    });
    window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
    if (page.dataset.pending !== '1') return;
    let timer;
    let stopped = false;
    const check = async () => {
        if (stopped) return;
        if (!document.hidden) {
            const message = page.querySelector('[data-status-message]');
            try {
                const response = await fetch(page.dataset.statusUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
                if ([401, 403, 404].includes(response.status) || response.redirected) {
                    message.textContent = 'Sign in again to check payment status.';
                    return;
                }
                if (!response.ok) throw new Error('Status unavailable');
                const state = await response.json();
                if (state.paid) { window.location.reload(); return; }
                message.textContent = 'Waiting for the school to confirm receipt of funds.';
            } catch (_) {
                message.textContent = 'Unable to check status right now. Retrying shortly; you can also refresh this page.';
            }
        }
        if (!stopped) timer = window.setTimeout(check, 5000);
    };
    window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); });
    timer = window.setTimeout(check, 5000);
})();
