(() => {
    const root = document.querySelector('[data-attendance-people]');
    if (!root) return;
    let timer;
    let controller;
    let version = 0;
    const load = async (url, current) => {
        controller = new AbortController();
        const status = root.querySelector('[data-live-search-status]');
        status.textContent = 'Searching…';
        root.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || response.redirected) throw new Error('Search failed');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (version !== current) return;
            const next = doc.querySelector('[data-attendance-people]');
            if (!next) throw new Error('Missing results');
            const input = root.querySelector('input[name="search"]');
            const focused = document.activeElement === input;
            const selection = [input.selectionStart, input.selectionEnd];
            root.replaceChildren(...next.childNodes);
            if (focused) {
                const replacement = root.querySelector('input[name="search"]');
                replacement.focus({ preventScroll: true });
                try { replacement.setSelectionRange(...selection); } catch (_) {}
            }
            history.replaceState(history.state, '', url);
            document.title = doc.title;
            root.querySelector('[data-live-search-status]').textContent = 'Results updated.';
        } catch (error) {
            if (version === current && error.name !== 'AbortError') {
                root.querySelector('[data-live-search-status]').textContent = 'Could not update results. Press Enter to retry.';
            }
        } finally {
            if (version === current) root.removeAttribute('aria-busy');
        }
    };
    const schedule = (url, delay = 0) => {
        clearTimeout(timer);
        controller?.abort();
        const current = ++version;
        timer = setTimeout(() => load(url, current), delay);
    };
    const searchUrl = () => {
        const form = root.querySelector('[data-attendance-search]');
        const url = new URL(form.action);
        url.search = new URLSearchParams(new FormData(form)).toString();
        url.searchParams.delete('page');
        return url;
    };
    root.addEventListener('input', event => {
        if (event.target.matches('input[name="search"]')) schedule(searchUrl(), 200);
    });
    root.addEventListener('change', event => {
        if (event.target.matches('select[name="name_part"]')) schedule(searchUrl());
    });
    root.addEventListener('submit', event => {
        if (!event.target.matches('[data-attendance-search]')) return;
        event.preventDefault();
        schedule(searchUrl());
    });
    root.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const url = new URL(link.href);
        if (url.origin !== location.origin || !url.pathname.startsWith('/attendance/people/')) return;
        event.preventDefault();
        // Preserve the latest typed name even when a filter is clicked before search completes.
        if (link.textContent.trim() !== 'Reset filters') {
            url.searchParams.set('search', root.querySelector('input[name="search"]').value);
            url.searchParams.set('name_part', root.querySelector('select[name="name_part"]').value);
        }
        schedule(url);
    });
})();
