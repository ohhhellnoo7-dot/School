(() => {
    const form = document.querySelector('[data-teacher-report-search]');
    if (!form) return;

    const status = document.querySelector('[data-teacher-report-status]');
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

    function selectReport(type) {
        const annual = type === 'leave';
        form.elements.report.value = type;
        form.elements.month.disabled = annual;
        form.elements.year.disabled = !annual;
        form.querySelector('[data-report-month-field]').hidden = annual;
        form.querySelector('[data-report-year-field]').hidden = !annual;
    }

    async function update() {
        clearTimeout(timer);
        if (!form.reportValidity()) return;
        pending?.abort();
        const request = new AbortController();
        pending = request;
        const url = new URL(form.action);
        url.search = new URLSearchParams(new FormData(form));
        const current = document.getElementById('teacher-payroll-report-results');
        current.setAttribute('aria-busy', 'true');
        printButton.disabled = true;
        status.classList.add('visually-hidden');
        status.textContent = 'Updating teacher reports.';
        try {
            const response = await fetch(url, { signal: request.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || response.redirected) throw new Error('Reports unavailable');
            const html = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = html.getElementById(current.id);
            if (!updated) throw new Error('Reports unavailable');
            if (request.signal.aborted) return;

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
        } catch {
            if (!request.signal.aborted) {
                status.textContent = 'Could not update reports. Use Search to try again.';
                status.classList.remove('visually-hidden');
            }
        } finally {
            if (!request.signal.aborted) {
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
        timer = setTimeout(update, 250);
    });
})();
