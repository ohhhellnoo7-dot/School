(() => {
    const form = document.querySelector('[data-student-payment-search]');
    if (!form) return;
    const input = form.elements.search;
    const status = document.querySelector('[data-student-payment-status]');
    let timer;
    let pending;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        pending?.abort();
        timer = setTimeout(async () => {
            const request = new AbortController();
            pending = request;
            const url = new URL(form.action);
            url.searchParams.set('search', input.value);
            status.textContent = 'Searching…';
            try {
                const response = await fetch(url, { signal: request.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok || response.redirected) throw new Error('Search failed');
                const html = new DOMParser().parseFromString(await response.text(), 'text/html');
                const results = html.querySelector('[data-student-payment-results]');
                if (!results) throw new Error('Search results unavailable');
                if (request.signal.aborted) return;
                document.querySelector('[data-student-payment-results]').replaceWith(results);
                status.textContent = '';
            } catch (error) {
                if (!request.signal.aborted) status.textContent = 'Search could not load. Press Search to try again.';
            }
        }, 250);
    });
})();

(() => {
    const section = document.querySelector('#student-payment-quote');
    const status = section?.querySelector('[data-payment-preview-status]');
    if (!status) return;
    let pending;
    const programChecks = root => [...root.querySelectorAll('input[type="checkbox"][name="enrollments[]"]')];
    const syncSelectAll = root => {
        const all = root.querySelector('[data-payment-select-all]');
        if (!all) return;
        const checks = programChecks(root);
        const checked = checks.filter(check => check.checked).length;
        all.checked = checked === checks.length;
        all.indeterminate = checked > 0 && checked < checks.length;
    };
    syncSelectAll(section);

    const update = async (form, focused) => {
        pending?.abort();
        const request = new AbortController();
        pending = request;
        const url = new URL(form.action);
        url.search = new URLSearchParams(new FormData(form)).toString();
        const preview = section.querySelector('[data-payment-preview]');
        const record = preview.querySelector('[data-payment-record]');
        const method = record?.elements.method.value;
        const reference = record?.elements.reference.value;
        if (record) {
            record.querySelector('button[type="submit"], button:not([type])').disabled = true;
            record.elements.confirmed.checked = false;
        }
        preview.setAttribute('aria-busy', 'true');
        status.textContent = 'Updating payment preview…';

        try {
            const response = await fetch(url, { signal: request.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || response.redirected) throw new Error('Preview unavailable');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = html.querySelector('[data-payment-preview]');
            if (!updated) throw new Error('Preview unavailable');
            if (request.signal.aborted) return;

            const updatedRecord = updated.querySelector('[data-payment-record]');
            if (updatedRecord && method !== undefined) {
                updatedRecord.elements.method.value = method;
                updatedRecord.elements.reference.value = reference;
            }
            const restoreFocus = document.activeElement === focused;
            preview.replaceWith(updated);
            syncSelectAll(updated);
            if (restoreFocus && focused) {
                const match = focused.matches('[data-payment-select-all]')
                    ? updated.querySelector('[data-payment-select-all]')
                    : [...updated.querySelectorAll('[data-payment-duration], input[type="checkbox"][name="enrollments[]"]')]
                        .find(control => control.dataset.paymentDuration === focused.dataset.paymentDuration && control.value === focused.value);
                match?.focus({ preventScroll: true });
            }
            history.replaceState(null, '', url);
            status.textContent = 'Payment preview updated.';
        } catch {
            if (request.signal.aborted) return;
            const retry = document.createElement('button');
            retry.type = 'button';
            retry.className = 'btn btn-sm btn-outline-primary ms-2';
            retry.textContent = 'Retry';
            retry.addEventListener('click', () => update(form, focused));
            status.replaceChildren('Could not update the payment preview.', retry);
            preview.removeAttribute('aria-busy');
        }
    };

    section.addEventListener('change', event => {
        if (event.target.matches('[data-payment-selection] input[type="checkbox"]')) {
            if (event.target.matches('[data-payment-select-all]')) {
                programChecks(event.target.form).forEach(check => { check.checked = event.target.checked; });
            }
            syncSelectAll(event.target.form);
            update(event.target.form, event.target);
        }
    });
    section.addEventListener('click', event => {
        const duration = event.target.closest('[data-payment-duration]');
        const form = section.querySelector('[data-payment-selection]');
        if (!duration || !form || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        form.elements.months.value = duration.dataset.paymentDuration;
        update(form, duration);
    });
    section.addEventListener('submit', event => {
        if (event.target.matches('[data-payment-selection]')) {
            event.preventDefault();
            update(event.target);
        } else if (event.target.matches('[data-payment-record]') && event.target.querySelector('button').disabled) {
            event.preventDefault();
        }
    });
})();

(() => {
    const dialog = document.querySelector('[data-payment-loading]');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const title = dialog.querySelector('h2');
    const status = dialog.querySelector('[data-payment-status]');
    const progress = dialog.querySelector('.payment-dialog-progress');
    const art = dialog.querySelector('.payment-dialog-illustration');
    const outcome = dialog.querySelector('.payment-dialog-outcome');
    const actions = dialog.querySelector('.payment-dialog-actions');
    const receipt = dialog.querySelector('[data-payment-receipt]');
    const retry = dialog.querySelector('[data-payment-retry]');
    const close = dialog.querySelector('[data-payment-close]');
    const steps = [...dialog.querySelectorAll('.payment-dialog-steps li')];
    const initialTitle = title.textContent;
    const initialStatus = status.textContent;
    let pending = false;
    let submission;
    let next;

    function showState(state, heading, message, hasReceipt = false) {
        dialog.dataset.state = state;
        title.textContent = heading;
        status.textContent = message;
        const loading = state === 'loading';
        progress.hidden = !loading;
        art.hidden = !loading;
        outcome.hidden = loading;
        outcome.className = 'bi payment-dialog-outcome ' + (state === 'error' ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill');
        actions.hidden = loading;
        receipt.hidden = !hasReceipt;
        retry.hidden = true;
        steps.forEach((step, index) => {
            const complete = hasReceipt || (state === 'coverage' && index === 0);
            step.dataset.step = complete ? 'complete' : 'pending';
            step.querySelector('.payment-step-check').hidden = !complete;
            step.querySelector('[data-step-status]').textContent = complete ? ' (Completed)'
                : state === 'coverage' ? ' (Not required)' : loading ? ' (Awaiting confirmation)' : ' (Unconfirmed)';
        });
    }

    function dismiss() {
        if (pending) return;
        dialog.close();
        if (next) {
            const destination = new URL(next, window.location.href);
            const current = new URL(window.location.href);
            if (destination.pathname === current.pathname && destination.search === current.search) {
                history.replaceState(null, '', destination);
                window.location.reload();
            } else {
                window.location.assign(destination);
            }
        } else {
            submission.button.disabled = false;
            submission.button.focus();
        }
    }

    async function record() {
        pending = true;
        submission.button.disabled = true;
        showState('loading', initialTitle, initialStatus);
        if (!dialog.open) dialog.showModal();
        const showResultAfter = performance.now() + 5000;
        const request = new AbortController();
        const timeout = setTimeout(() => request.abort(), 15000);
        try {
            const response = await fetch(submission.url, {
                method: 'POST',
                body: submission.fields,
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });
            const data = await response.json();
            if (!response.ok || !data.next) {
                const retryable = response.status >= 500 || response.ok;
                const message = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
                showState('error', retryable ? 'Payment not confirmed' : 'Payment could not be recorded',
                    retryable ? 'Retry this same request to check or finish recording the payment.'
                        : (message || 'Review the payment details and try again.'));
                retry.hidden = !retryable;
                (retryable ? retry : close).focus();
                return;
            }

            const amounts = data.amounts.map(({amount, currency}) => amount + ' ' + currency).join(' + ');
            clearTimeout(timeout);
            const remaining = showResultAfter - performance.now();
            if (remaining > 0) await new Promise(resolve => setTimeout(resolve, remaining));
            next = data.next;
            if (data.receipt) {
                receipt.href = data.receipt;
                showState('success', 'Payment recorded successfully!', amounts, true);
                receipt.focus();
            } else {
                showState('coverage', 'Tuition coverage recorded', 'No payment was due for this invoice.');
                close.focus();
            }
        } catch {
            showState('error', 'Payment not confirmed', 'Retry this same request to check or finish recording the payment.');
            retry.hidden = false;
            retry.focus();
        } finally {
            clearTimeout(timeout);
            pending = false;
        }
    }

    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        dismiss();
    });
    close.addEventListener('click', dismiss);
    retry.addEventListener('click', record);
    document.addEventListener('submit', event => {
        const form = event.target.closest('[data-payment-record]');
        if (!form || event.defaultPrevented) return;
        event.preventDefault();
        if (pending || next) return;

        submission = {url: form.action, fields: new FormData(form), button: form.querySelector('button[type="submit"]')};
        record();
    });
})();
