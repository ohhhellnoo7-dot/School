(() => {
    const dashboard = document.querySelector('[data-classroom-dashboard]');
    const form = document.querySelector('[data-availability-filters]');
    const results = document.querySelector('[data-availability-results]');
    const status = document.querySelector('[data-refresh-status]');

    if (!dashboard || !form || !results) return;

    const endpoint = dashboard.dataset.endpoint;
    const interval = Number(dashboard.dataset.interval || 30000);
    let followNow = dashboard.dataset.followNow === '1';
    let requestController = null;

    const localDateParts = () => {
        const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Bangkok', year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        }).formatToParts(new Date()).map(part => [part.type, part.value]));
        return {date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}`};
    };

    const useCurrentMoment = () => {
        if (!followNow) return;
        const current = localDateParts();
        const date = form.querySelector('[name="date"]');
        const time = form.querySelector('[name="time"]');
        if (date) date.value = current.date;
        if (time) time.value = current.time;
    };

    const params = () => {
        const query = new URLSearchParams();
        new FormData(form).forEach((value, key) => {
            if (String(value).trim() !== '') query.set(key, value);
        });
        if (followNow) query.set('live', '1');
        return query;
    };

    const refresh = async ({ updateHistory = false } = {}) => {
        useCurrentMoment();
        const query = params();
        const url = `${endpoint}?${query.toString()}`;

        requestController?.abort();
        requestController = new AbortController();
        results.setAttribute('aria-busy', 'true');
        if (status) status.textContent = 'Refreshing classroom availability...';

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
                signal: requestController.signal,
            });
            if (!response.ok) throw new Error(`Availability request failed (${response.status})`);

            const payload = await response.json();
            results.innerHTML = payload.html;
            if (status) status.textContent = `Showing room status for ${payload.label}. Refreshed just now; refreshes every 30 seconds.`;
            if (updateHistory) {
                const browserUrl = `${form.action}?${query.toString()}`;
                window.history.replaceState({}, '', browserUrl);
            }
        } catch (error) {
            if (error.name !== 'AbortError' && status) {
                status.textContent = 'Classroom status could not be refreshed. The last successful results are still shown.';
            }
        } finally {
            results.removeAttribute('aria-busy');
        }
    };

    form.addEventListener('submit', event => {
        event.preventDefault();
        followNow = false;
        refresh({ updateHistory: true });
    });
    form.querySelectorAll('[name="date"], [name="time"]').forEach(input => {
        input.addEventListener('input', () => { followNow = false; });
    });
    document.querySelector('[data-availability-refresh]')?.addEventListener('click', () => refresh());

    window.setInterval(() => {
        if (!document.hidden) refresh();
    }, Math.max(interval, 10000));
})();
