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
    const loadingStatus = dialog.querySelector('[role="status"]');
    const initialStatus = loadingStatus.textContent;
    let recording = false;

    dialog.addEventListener('cancel', event => event.preventDefault());
    document.addEventListener('submit', async event => {
        const form = event.target.closest('[data-payment-record]');
        if (!form || event.defaultPrevented) return;
        event.preventDefault();
        if (recording) return;

        const fields = new FormData(form);
        const button = form.querySelector('button');
        const errorMessage = form.querySelector('[data-payment-record-error]');
        recording = true;
        button.disabled = true;
        errorMessage.hidden = true;
        loadingStatus.textContent = initialStatus;
        dialog.showModal();
        const request = new AbortController();
        const timeout = setTimeout(() => request.abort(), 12000);
        const minimumDisplay = new Promise(resolve => setTimeout(() => {
            loadingStatus.textContent = 'Waiting for payment confirmation…';
            resolve();
        }, 3000));
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: fields,
                headers: { Accept: 'application/json' },
                signal: request.signal,
            });
            const data = await response.json();
            if (!response.ok || !data.next) {
                throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'The payment could not be recorded.'));
            }
            await minimumDisplay;
            const next = new URL(data.next, window.location.href);
            const here = new URL(window.location.href);
            if (next.origin === here.origin && next.pathname === here.pathname && next.search === here.search) {
                history.replaceState(null, '', next);
                window.location.reload();
            } else {
                window.location.assign(next);
            }
        } catch (error) {
            await minimumDisplay;
            dialog.close();
            recording = false;
            button.disabled = false;
            errorMessage.textContent = request.signal.aborted || error instanceof TypeError || error instanceof SyntaxError
                ? 'The payment result could not be confirmed. Check Invoices & payments before trying again.'
                : error.message;
            errorMessage.hidden = false;
            button.focus();
        } finally {
            clearTimeout(timeout);
        }
    });
})();
