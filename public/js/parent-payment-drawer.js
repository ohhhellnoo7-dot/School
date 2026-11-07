(() => {
    const drawer = document.getElementById('parent-payment-drawer');
    if (!drawer || !window.bootstrap) return;
    const mount = document.getElementById('parent-payment-content');
    const panel = bootstrap.Offcanvas.getOrCreateInstance(drawer);
    let opener, activeTab = 'overview', detailsUrl, request, previewRequest;
    const disposeTips = () => mount.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => bootstrap.Tooltip.getInstance(el)?.dispose());
    const notification = (message, error = false) => {
        const box = mount.querySelector('.pd-notification');
        if (!box) return;
        box.textContent = message;
        box.classList.toggle('pd-notification--error', error);
        box.hidden = false;
        box.setAttribute('role', error ? 'alert' : 'status');
    };
    const skeleton = () => `<div class="pd-loading" role="status" aria-live="polite"><h2 id="parent-drawer-title">Parent Payment Overview</h2><button class="pd-close" data-bs-dismiss="offcanvas" aria-label="Close parent payment overview"><i class="bi bi-x-lg" aria-hidden="true"></i></button><span class="visually-hidden">Loading payment details</span><div class="pd-skeleton pd-skeleton-title"></div><div class="pd-skeleton"></div><div class="pd-skeleton"></div><div class="pd-skeleton pd-skeleton-card"></div><div class="pd-skeleton pd-skeleton-card"></div></div>`;
    const selectTab = (tab, focus = false) => {
        activeTab = tab;
        mount.querySelectorAll('[data-pd-tab]').forEach(button => {
            const selected = button.dataset.pdTab === tab;
            button.setAttribute('aria-selected', selected);
            button.tabIndex = selected ? 0 : -1;
            if (selected && focus) button.focus();
        });
        mount.querySelectorAll('[data-pd-panel]').forEach(section => section.hidden = section.dataset.pdPanel !== tab);
        const preview = mount.querySelector('[data-pd-preview]');
        if (preview) preview.hidden = true;
    };
    const load = async (url, tab = 'overview', success = '', returnFocus = '') => {
        request?.abort(); previewRequest?.abort();
        request = new AbortController();
        const signal = request.signal;
        const previous = mount.querySelector('[data-parent-overview]');
        const sameParent = previous?.dataset.detailsUrl === new URL(url, location.href).origin + new URL(url, location.href).pathname;
        disposeTips();
        mount.innerHTML = skeleton();
        drawer.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {signal, headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'}});
            if (!response.ok || response.redirected) {
                let message = response.status === 403 ? 'You do not have permission to view these payment details.' : 'Unable to load payment details. Please try again.';
                if (response.status === 422) {
                    const result = await response.json();
                    message = Object.values(result.errors || {}).flat().join(' ') || message;
                }
                throw new Error(message);
            }
            const html = await response.text();
            if (signal.aborted) return;
            mount.innerHTML = html;
            const overview = mount.querySelector('[data-details-url]');
            detailsUrl = overview.dataset.detailsUrl;
            const rowDate = document.querySelector(`[data-parent-schedule="${overview.dataset.parentId}"]`);
            const dateSummary = mount.querySelector('[data-pd-schedule-summary]');
            if (rowDate && dateSummary) rowDate.innerHTML = dateSummary.innerHTML;
            selectTab(tab);
            mount.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el, {container: drawer}));
            if (success) notification(success);
            if (returnFocus) mount.querySelector(returnFocus)?.focus();
        } catch (error) {
            if (error.name === 'AbortError') return;
            if (previous && sameParent) {
                mount.replaceChildren(previous);
                selectTab(tab);
                mount.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el, {container: drawer}));
                notification(error.message, true);
                return;
            }
            mount.innerHTML = skeleton();
            mount.querySelectorAll('.pd-skeleton').forEach(el => el.remove());
            const box = document.createElement('div');
            box.className = 'pd-empty'; box.setAttribute('role', 'alert'); box.textContent = error.message;
            const retry = document.createElement('button'); retry.className = 'btn btn-primary'; retry.textContent = 'Try again'; retry.type = 'button'; retry.addEventListener('click', () => load(url, tab));
            box.append(document.createElement('br'), retry); mount.append(box);
        } finally {
            if (!signal.aborted) drawer.removeAttribute('aria-busy');
        }
    };
    document.querySelectorAll('[data-payment-detail]').forEach(button => button.addEventListener('click', () => {
        opener = button; detailsUrl = button.dataset.paymentDetail;
        document.documentElement.classList.add('pd-drawer-open');
        panel.show();
        load(detailsUrl);
    }));
    drawer.addEventListener('hidden.bs.offcanvas', () => {
        request?.abort(); previewRequest?.abort(); disposeTips();
        document.documentElement.classList.remove('pd-drawer-open');
        opener?.focus();
    });
    mount.addEventListener('keydown', event => {
        const tab = event.target.closest('[data-pd-tab]');
        if (!tab || !['ArrowRight','ArrowLeft','Home','End'].includes(event.key)) return;
        event.preventDefault();
        const tabs = [...mount.querySelectorAll('[data-pd-tab]')];
        let index = tabs.indexOf(tab);
        index = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
        selectTab(tabs[index].dataset.pdTab, true);
    });
    mount.addEventListener('click', async event => {
        const tab = event.target.closest('[data-pd-tab], [data-pd-jump]');
        if (tab) { selectTab(tab.dataset.pdTab || tab.dataset.pdJump, true); mount.querySelector('.pd-scroll').scrollTop = 0; return; }
        const pagination = event.target.closest('[data-pd-load]');
        if (pagination) { event.preventDefault(); load(pagination.href, 'history', '', '#pd-tab-history'); return; }
        const edit = event.target.closest('[data-pd-edit-reminder]');
        if (edit) { mount.querySelector('#pd-reminder-fields').hidden = false; edit.setAttribute('aria-expanded', 'true'); mount.querySelector('#pd-reminder-date').focus(); return; }
        const back = event.target.closest('[data-pd-back]');
        if (back) { previewRequest?.abort(); selectTab(activeTab, true); return; }
        const invoice = event.target.closest('[data-invoice-preview]');
        if (!invoice || event.ctrlKey || event.metaKey || event.shiftKey) return;
        event.preventDefault();
        previewRequest?.abort(); previewRequest = new AbortController();
        const signal = previewRequest.signal;
        const preview = mount.querySelector('[data-pd-preview]');
        mount.querySelectorAll('[data-pd-panel]').forEach(section => section.hidden = true);
        preview.hidden = false;
        const content = preview.querySelector('[data-pd-preview-content]');
        content.innerHTML = '<div class="pd-skeleton pd-skeleton-card" role="status" aria-label="Loading invoice preview"></div>';
        mount.querySelector('.pd-scroll').scrollTop = 0;
        preview.querySelector('[data-pd-back]').focus();
        try {
            const url = new URL(invoice.href); url.searchParams.set('preview', '1');
            const response = await fetch(url, {signal, headers: {Accept: 'text/html'}});
            if (!response.ok || response.redirected) throw new Error('Unable to load this invoice.');
            const html = await response.text(); if (!signal.aborted) content.innerHTML = html;
        } catch (error) { if (error.name !== 'AbortError') content.textContent = error.message; }
    });
    mount.addEventListener('submit', async event => {
        const form = event.target;
        if (form.matches('[data-pd-history-form]')) {
            event.preventDefault();
            const url = new URL(form.action);
            new FormData(form).forEach((value, key) => { if (value) url.searchParams.set(key, value); });
            load(url.toString(), 'history', '', '#pd-tab-history'); return;
        }
        if (!form.matches('[data-pd-reminder-form]')) return;
        event.preventDefault();
        const data = new FormData(form);
        if (event.submitter?.name === 'clear') data.set('next_payment_on', '');
        form.querySelectorAll('button').forEach(button => button.disabled = true);
        try {
            const response = await fetch(form.action, {method: 'POST', body: data, headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to save reminder.');
            if (!drawer.classList.contains('show') || !mount.contains(form)) return;
            // Refresh only after the backend confirms the transaction.
            await load(detailsUrl, 'upcoming', result.message, '#pd-tab-upcoming');
        } catch (error) { if (mount.contains(form)) notification(error.message, true); }
        finally { form.querySelectorAll('button').forEach(button => button.disabled = false); }
    });
})();
