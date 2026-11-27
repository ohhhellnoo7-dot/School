(() => {
    const form = document.querySelector('[data-teacher-report-search]');
    if (!form) return;

    const status = document.querySelector('[data-teacher-report-status]');
    const refreshButton = document.querySelector('[data-refresh-teacher-reports]');
    const advancesModal = document.getElementById('outstanding-advances-modal');
    advancesModal?.addEventListener('show.bs.modal', () => update(true));
    const printButton = document.querySelector('[data-print-teacher-reports]');
    printButton.addEventListener('click', () => window.print());
    const bonusDialog = document.getElementById('leave-bonus-dialog');
    const bonusForm = bonusDialog.querySelector('form');
    function openBonusEditor(button) {
        if (!button || bonusDialog.open) return;
        bonusForm.elements.bonus_service_months.value = button.dataset.bonusMonths;
        bonusDialog.showModal();
    }
    document.addEventListener('click', event => openBonusEditor(event.target.closest('[data-bonus-months]')));
    document.addEventListener('dblclick', event => openBonusEditor(event.target.closest('td')?.querySelector('[data-bonus-months]')));
    bonusDialog.querySelector('[data-cancel-bonus-edit]').addEventListener('click', () => bonusDialog.close());
    let pending;
    let timer;
    let lastRefresh = Date.now();
    const refreshInterval = 30000 + Math.random() * 5000;

    function selectReport(type) {
        const annual = type === 'leave';
        form.elements.report.value = type;
        form.elements.month.disabled = annual;
        form.elements.year.disabled = !annual;
        form.querySelector('[data-report-month-field]').hidden = annual;
        form.querySelector('[data-report-year-field]').hidden = !annual;
    }

    async function update(background = false, manual = false) {
        if (background && (pending || timer || bonusDialog.open)) return;
        if (!background) {
            clearTimeout(timer);
            timer = null;
            if (!form.reportValidity()) return;
        }
        pending?.abort();
        const request = new AbortController();
        pending = request;
        const url = background ? new URL(window.location.href) : new URL(form.action);
        if (!background) url.search = new URLSearchParams(new FormData(form));
        const current = document.getElementById('teacher-payroll-report-results');
        if (!background || manual) {
            current.setAttribute('aria-busy', 'true');
            printButton.disabled = true;
            status.textContent = 'Updating teacher reports.';
        }
        const timeout = setTimeout(() => request.abort('timeout'), 20000);
        try {
            const response = await fetch(url, {
                signal: request.signal,
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Include-Outstanding-Advances': '1' },
            });
            if (!response.ok || response.redirected) throw new Error('Reports unavailable');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = html.getElementById(current.id);
            if (!updated) throw new Error('Reports unavailable');
            if (request.signal.aborted) return;

            const advanceBody = advancesModal?.querySelector('[data-outstanding-advance-body]');
            const updatedAdvances = html.querySelector('[data-outstanding-advance-body]');
            if (updatedAdvances && advanceBody && advanceBody.innerHTML !== updatedAdvances.innerHTML) {
                const scrollTop = advanceBody.scrollTop;
                advanceBody.replaceWith(updatedAdvances);
                updatedAdvances.scrollTop = scrollTop;
            }
            lastRefresh = Date.now();
            if (background) {
                const changed = current.innerHTML !== updated.innerHTML;
                const canReplace = !current.contains(document.activeElement) && !bonusDialog.open;
                if (changed && canReplace) {
                    current.replaceWith(updated);
                }
                if (canReplace && (manual || changed || status.dataset.refreshFailed)) status.textContent = 'Teacher reports are up to date.';
                delete status.dataset.refreshFailed;
                return;
            }

            current.replaceWith(updated);
            const referenceMonth = updated.dataset.referenceMonth;
            form.elements.month.value = referenceMonth;
            form.elements.year.value = referenceMonth.slice(0, 4);
            const back = document.querySelector('[data-report-attendance-link]');
            const returnUrl = new URL(back.href);
            returnUrl.searchParams.set('month', referenceMonth);
            for (const key of ['program', 'session']) {
                const value = url.searchParams.get(key);
                if (value) returnUrl.searchParams.set(key, value);
                else returnUrl.searchParams.delete(key);
            }
            back.href = returnUrl;
            for (const link of document.querySelectorAll('[data-teacher-report-type]')) {
                const target = new URL(url);
                const type = link.dataset.teacherReportType;
                const selected = type === form.elements.report.value;
                target.searchParams.set('report', type);
                target.searchParams.delete('month');
                target.searchParams.delete('year');
                if (type === 'leave') target.searchParams.set('year', form.elements.year.value);
                else target.searchParams.set('month', referenceMonth);
                link.href = target;
                link.classList.toggle('btn-primary', selected);
                link.classList.toggle('btn-outline-primary', !selected);
                if (selected) link.setAttribute('aria-current', 'true');
                else link.removeAttribute('aria-current');
            }
            window.history.replaceState(null, '', url);
            status.textContent = 'Teacher reports updated.';
            delete status.dataset.refreshFailed;
        } catch {
            if (!request.signal.aborted || request.signal.reason === 'timeout') {
                status.textContent = 'Could not update reports. Use Refresh to try again.';
                status.dataset.refreshFailed = 'true';
            }
        } finally {
            clearTimeout(timeout);
            if (pending === request) {
                pending = null;
                current.removeAttribute('aria-busy');
                printButton.disabled = false;
            }
        }
    }

    for (const link of document.querySelectorAll('[data-teacher-report-type]')) {
        link.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            selectReport(link.dataset.teacherReportType);
            update();
        });
    }

    form.addEventListener('submit', event => { event.preventDefault(); update(); });
    form.addEventListener('change', event => {
        if (event.target.name !== 'search') update();
    });
    form.elements.search.addEventListener('input', () => {
        clearTimeout(timer);
        pending?.abort();
        timer = setTimeout(() => update(), 250);
    });

    refreshButton.addEventListener('click', () => update(true, true));
    advancesModal?.addEventListener('shown.bs.modal', () => update(true));
    setInterval(() => {
        if (!document.hidden) update(true);
    }, refreshInterval);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() - lastRefresh >= refreshInterval) update(true);
    });
})();
