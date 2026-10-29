(() => {
    const search = document.querySelector('section.directory-panel input[name="search"]');
    const form = search?.closest('form');
    const panel = form?.closest('section.panel');
    if (!panel) return;
    let timer;
    let controller;
    let version = 0;
    const status = document.createElement('div');
    status.className = 'small text-secondary px-4 pb-2';
    status.setAttribute('role', 'status');
    form.after(status);
    search.addEventListener('input', () => {
        clearTimeout(timer);
        controller?.abort();
        const current = ++version;
        timer = setTimeout(async () => {
            controller = new AbortController();
            const url = new URL(form.action || location.href);
            url.search = new URLSearchParams(new FormData(form)).toString();
            url.searchParams.delete('page');
            status.textContent = 'Searching...';
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
                panel.querySelectorAll('form[data-confirm]').forEach(deleteForm => {
                    deleteForm.addEventListener('submit', event => {
                        if (!confirm(deleteForm.dataset.confirm)) event.preventDefault();
                    });
                });
                history.replaceState(null, '', url);
                status.textContent = '';
            } catch (error) {
                if (current === version && error.name !== 'AbortError') status.textContent = 'Search could not load. Use the filter button to try again.';
            }
        }, 300);
    });
    form.addEventListener('submit', () => { clearTimeout(timer); controller?.abort(); ++version; });
})();
