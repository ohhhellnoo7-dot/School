(() => {
    const modal = document.querySelector('[data-reopen-teacher-excuse]');
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();

    const badge = document.querySelector('[data-teacher-excuse-count]');
    if (!badge) return;

    let updating = false;
    async function refresh() {
        if (document.hidden || updating) return;
        updating = true;
        try {
            const response = await fetch(badge.dataset.teacherExcuseCount, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok || response.redirected) return;
            const { count } = await response.json();
            document.querySelectorAll('[data-teacher-excuse-count]').forEach(badge => {
                badge.hidden = count === 0;
                badge.textContent = count;
            });
        } catch {
            // Keep the last count until the next successful refresh.
        } finally {
            updating = false;
        }
    }

    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('pageshow', refresh);
    setInterval(refresh, 10000);
})();
