(() => {
    const search = document.querySelector('section.directory-panel input[name="search"]');
    const form = search?.closest('form');
    const panel = form?.closest('section.panel');
    if (!panel) return;
    const liveFilters = form.hasAttribute('data-live-filters');
    let timer;
    let controller;
    let version = 0;
    const status = document.createElement('div');
    status.className = 'small text-secondary px-4 pb-2';
    status.setAttribute('role', 'status');
    form.after(status);
    const refresh = (delay = 0) => {
        clearTimeout(timer);
        controller?.abort();
        const current = ++version;
        if (liveFilters && !form.checkValidity()) {
            status.textContent = 'Select a valid attendance date to update the results.';
            return;
        }
        timer = setTimeout(async () => {
            controller = new AbortController();
            const url = new URL(form.action || location.href);
            url.search = new URLSearchParams(new FormData(form)).toString();
            url.searchParams.delete('page');
            status.textContent = liveFilters ? 'Updating results...' : 'Searching...';
            try {
                const response = await fetch(url, { signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok || response.redirected) throw new Error('Search failed');
                const html = new DOMParser().parseFromString(await response.text(), 'text/html');
                if (current !== version) return;
                const next = html.querySelector('section.directory-panel');
                for (const selector of ['.panel-header', '.table-responsive', ':scope > .p-3']) {
                    const replacement = next?.querySelector(selector);
                    const existing = panel.querySelector(selector);
                    if (!replacement || !existing) throw new Error('Missing results');
                    existing.replaceWith(replacement);
                }
                // Student attendance totals must follow the same live search as the roster.
                const summary = document.querySelector('.students-page .attendance-summary');
                const nextSummary = html.querySelector('.students-page .attendance-summary');
                if (summary && nextSummary) {
                    summary.replaceWith(nextSummary);
                    document.dispatchEvent(new Event('student-attendance-summary-replaced'));
                }
                panel.querySelectorAll('[data-attendance-count]').forEach(count => {
                    const replacement = next.querySelector(`[data-attendance-count="${count.dataset.attendanceCount}"]`);
                    if (replacement) {
                        count.dataset.count = replacement.dataset.count;
                        count.textContent = replacement.textContent;
                    }
                });
                panel.querySelectorAll('form[data-confirm]').forEach(deleteForm => {
                    deleteForm.addEventListener('submit', event => {
                        if (!confirm(deleteForm.dataset.confirm)) event.preventDefault();
                    });
                });
                history.replaceState(null, '', url);
                status.textContent = '';
            } catch (error) {
                if (current === version && error.name !== 'AbortError') {
                    status.textContent = liveFilters
                        ? 'Results could not load. Change a filter or press Enter in search to try again.'
                        : 'Search could not load. Use the filter button to try again.';
                }
            }
        }, delay);
    };
    search.addEventListener('input', () => refresh(300));
    if (liveFilters) {
        form.addEventListener('change', event => {
            if (event.target.matches('select, input[type="date"], input[type="radio"]')) refresh();
        });
        search.addEventListener('keydown', event => {
            if (event.key === 'Enter' && !event.isComposing) {
                event.preventDefault();
                refresh();
            }
        });
    }
    form.addEventListener('submit', () => { clearTimeout(timer); controller?.abort(); ++version; });
})();
