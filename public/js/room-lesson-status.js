(() => {
    const panel = document.querySelector('[data-room-live]');
    if (!panel) return;
    let pending = false;
    const update = async () => {
        if (pending || document.hidden) return;
        pending = true;
        const query = new URLSearchParams(panel.dataset.live === '1' ? {live: '1'} : {date: panel.dataset.date, time: panel.dataset.time});
        try {
            const response = await fetch(`${panel.dataset.endpoint}?${query}`, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Refresh failed');
            const payload = await response.json();
            panel.querySelector('[data-room-live-content]').innerHTML = payload.html;
            panel.querySelector('[data-room-live-label]').textContent = `${payload.label} · Updates every 30 seconds`;
        } catch {
            panel.querySelector('[data-room-live-label]').textContent = 'Update unavailable. Showing the last successful status.';
        } finally { pending = false; }
    };
    setInterval(update, 30000);
    document.addEventListener('visibilitychange', update);
})();
