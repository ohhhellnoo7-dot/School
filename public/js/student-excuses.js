(() => {
    const family = document.querySelector('#family-excuse-dialog');
    if (family) {
        const form = family.querySelector('[data-excuse-form]');
        const chosenChild = form.elements.child_id;
        const session = form.elements.session;
        const choose = (id, previousSession = '') => {
            chosenChild.value = id;
            form.hidden = false;
            family.querySelectorAll('[data-excuse-child]').forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.excuseChild === id));
                button.classList.toggle('active', button.dataset.excuseChild === id);
            });
            session.selectedIndex = 0;
            Array.from(session.options).slice(1).forEach(option => {
                option.hidden = option.disabled = option.dataset.forChild !== id;
                if (!option.disabled && option.value === previousSession) option.selected = true;
            });
        };
        document.querySelectorAll('[data-open-excuse]').forEach(button => {
            button.addEventListener('click', () => family.showModal());
        });
        family.querySelectorAll('[data-excuse-child]').forEach(button => {
            button.addEventListener('click', () => {
                choose(button.dataset.excuseChild);
                session.focus();
            });
        });
        if (family.hasAttribute('data-reopen')) {
            choose(chosenChild.value, session.dataset.selected);
            family.showModal();
        }
    }

    const report = document.querySelector('#student-excuse-report');
    if (!report) return;
    const date = report.querySelector('input[type="date"]');
    const results = report.querySelector('[data-excuse-results]');
    let pending;
    let timer;
    let displayed;

    const refresh = async (showLoading = false) => {
        clearTimeout(timer);
        pending?.abort();
        if (!date.checkValidity()) {
            results.replaceChildren();
            displayed = undefined;
            return;
        }
        const request = new AbortController();
        pending = request;
        const url = new URL(report.dataset.reportUrl, location.href);
        url.searchParams.set('date', date.value);
        if (showLoading) {
            displayed = undefined;
            results.textContent = report.dataset.loading;
        }
        try {
            const response = await fetch(url, {
                cache: 'no-store',
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                signal: request.signal,
            });
            if (!response.ok || response.redirected) throw new Error('Report unavailable');
            const html = await response.text();
            if (!request.signal.aborted && html !== displayed) {
                displayed = html;
                results.innerHTML = html;
            }
        } catch (error) {
            if (!request.signal.aborted) {
                displayed = undefined;
                results.textContent = report.dataset.error;
            }
        } finally {
            if (!request.signal.aborted && report.open) timer = setTimeout(refresh, 5000);
        }
    };

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-open-excuse-report]');
        if (!button) return;
        date.value = button.dataset.openExcuseReport;
        report.showModal();
        refresh(true);
    });
    date.addEventListener('change', () => refresh(true));
    report.addEventListener('close', () => {
        clearTimeout(timer);
        pending?.abort();
    });
})();
