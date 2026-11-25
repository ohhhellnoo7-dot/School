(() => {
    const regions = ['metrics', 'history', 'queue'];
    const counts = Array.from(document.querySelectorAll('[data-expense-live-count]'));
    let timer;
    let pending = false;
    let failures = 0;
    let stopped = false;

    function schedule() {
        clearTimeout(timer);
        if (document.hidden || stopped) return;
        const delay = Math.min(5000 * 2 ** failures, 30000) + Math.random() * 500;
        timer = setTimeout(refresh, delay);
    }

    function updateCount(badge, count, label) {
        const kind = badge.dataset.expenseLiveCount;
        badge.hidden = count === 0;
        if (kind === 'sidebar') {
            badge.toggleAttribute('data-pending-advance-count', count > 0);
            badge.lastChild.nodeValue = String(count);
        } else if (kind === 'modal') {
            badge.textContent = label;
        } else {
            badge.textContent = String(count);
            badge.setAttribute('aria-label', label);
        }
    }

    async function refresh() {
        if (pending || stopped || document.hidden) return;
        clearTimeout(timer);
        pending = true;
        const request = new AbortController();
        const timeout = setTimeout(() => request.abort(), 12000);
        try {
            const response = await fetch(window.location.href, {
                signal: request.signal,
                cache: 'no-store',
                headers: {Accept: 'application/json', 'X-Expense-Live': '1'},
            });
            if (response.redirected || response.status === 401 || response.status === 403) {
                stopped = true;
                return;
            }
            if (!response.ok) throw new Error('Could not update expenses.');

            const {html, pendingCount, pendingLabel} = await response.json();
            const documentUpdate = new DOMParser().parseFromString(html, 'text/html');
            for (const region of regions) {
                const selector = `[data-expense-live="${region}"]`;
                const current = document.querySelector(selector);
                const next = documentUpdate.querySelector(selector);
                if (!current || !next || current.innerHTML === next.innerHTML || current.contains(document.activeElement)) continue;
                const scrollLeft = current.querySelector('.table-responsive')?.scrollLeft ?? 0;
                current.replaceWith(next);
                const table = next.querySelector('.table-responsive');
                if (table) table.scrollLeft = scrollLeft;
            }
            for (const badge of counts) updateCount(badge, pendingCount, pendingLabel);
            failures = 0;
        } catch {
            failures = Math.min(failures + 1, 3);
        } finally {
            clearTimeout(timeout);
            pending = false;
            schedule();
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) clearTimeout(timer);
        else refresh();
    });
    document.getElementById('employee-advance-modal')?.addEventListener('shown.bs.modal', refresh);
    schedule();
})();
