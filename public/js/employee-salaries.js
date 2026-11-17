document.querySelectorAll('[data-salary-search]').forEach((form) => {
    let timer;
    let controller;
    const update = async () => {
        clearTimeout(timer);
        controller?.abort();
        controller = new AbortController();
        const signal = controller.signal;
        const url = new URL(form.action || window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        const results = document.getElementById('salary-search-results');
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Search failed');
            const documentResult = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = documentResult.getElementById('salary-search-results');
            if (!replacement) throw new Error('Search results unavailable');
            if (signal.aborted) return;
            results.innerHTML = replacement.innerHTML;
            const count = document.getElementById('salary-teacher-count');
            const newCount = documentResult.getElementById('salary-teacher-count');
            if (count && newCount) count.textContent = newCount.textContent;
            window.history.replaceState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') window.location.assign(url);
        } finally {
            if (!signal.aborted) results.removeAttribute('aria-busy');
        }
    };
    form.addEventListener('submit', (event) => { event.preventDefault(); update(); });
    form.querySelector('[name="search"]').addEventListener('input', () => {
        clearTimeout(timer);
        controller?.abort();
        timer = setTimeout(update, 300);
    });
    form.querySelectorAll('input[type="month"], input[type="date"]').forEach((input) => input.addEventListener('change', update));
});
