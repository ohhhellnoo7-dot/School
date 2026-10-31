(() => {
    const panel = document.querySelector('[data-teacher-live]');
    if (!panel) return;
    let pending = false;
    const refresh = async () => {
        if (pending || document.hidden) return;
        pending = true;
        try {
            const response = await fetch(panel.dataset.endpoint, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Refresh failed');
            const data = await response.json();
            ['status', 'current', 'next', 'time'].forEach(key => {
                panel.querySelector(`[data-teacher-${key}]`).textContent = data[key];
            });
        } catch { panel.querySelector('[data-teacher-time]').textContent = panel.dataset.refreshError; }
        finally { pending = false; }
    };
    setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', refresh);
})();
