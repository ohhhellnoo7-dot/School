(() => {
    const rooms = document.querySelector('[data-parent-classroom-url]');
    if (!rooms) return;
    let refreshing = false;
    setInterval(async () => {
        if (document.hidden || refreshing) return;
        refreshing = true;
        try {
            const response = await fetch(rooms.dataset.parentClassroomUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (response.ok && !response.redirected) rooms.innerHTML = (await response.json()).html;
        } catch {
            // Keep the last successful classroom snapshot when the connection drops.
        } finally {
            refreshing = false;
        }
    }, 30000);
})();
