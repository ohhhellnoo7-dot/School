(() => {
    const content = document.getElementById('dashboard-content');
    const button = document.getElementById('dashboard-refresh');
    const status = document.getElementById('dashboard-sync-status');
    if (!content || !button || !status) return;

    let charts = [];
    let latestPayload;
    let pending = false;
    let sessionExpired = false;

    function renderCharts(payload) {
        if (typeof Chart === 'undefined') return;
        latestPayload = payload;
        const night = document.documentElement.dataset.bsTheme === 'dark';
        const palette = getComputedStyle(document.documentElement);
        const color = (name, fallback) => palette.getPropertyValue('--chart-' + name).trim() || fallback;
        const data = payload.charts;
        Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
        Chart.defaults.font.size = 11;
        Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#98a4ab';
        const axes = () => ({x: {grid: {display: false}, border: {display: false}}, y: {beginAtZero: true, grid: {color: color('grid', night ? '#a7b7d225' : '#83909825')}, border: {display: false}, ticks: {maxTicksLimit: 5, precision: 0}}});
        const create = (id, config) => {
            const canvas = document.getElementById(id);
            if (canvas) charts.push(new Chart(canvas, config));
        };
        const options = {maintainAspectRatio: false, animation: false, plugins: {legend: {display: false}}};
        create('enrollmentChart', {type: 'line', data: {labels: data.labels, datasets: [{label: 'New students', data: data.enrollment, borderColor: color('line', night ? '#a5afff' : '#4c4fff'), backgroundColor: color('fill', night ? '#8899ff25' : '#5b67ff25'), fill: true, borderWidth: 2.5, tension: .4, pointRadius: 3}]}, options: {...options, scales: axes()}});
        const doughnut = (id, labels, values, colors) => create(id, {type: 'doughnut', data: {labels, datasets: [{data: values, backgroundColor: colors, borderWidth: 0, borderRadius: 5}]}, options: {...options, cutout: '77%'}});
        doughnut('attendanceChart', ['Present', 'Absent', 'Late', 'Excused'], data.attendance, [color('present', '#13ca78'), color('absent', '#ff707d'), color('late', '#ffcb45'), color('excused', '#aeb9cd')]);
        create('revenueChart', {type: 'bar', data: {labels: data.labels, datasets: [{label: payload.showRevenue ? 'Collected (USD)' : 'New students', data: payload.showRevenue ? data.revenue : data.enrollment, backgroundColor: data.labels.map((_, index) => color(index === data.labels.length - 1 ? 'bar-active' : 'bar', index === data.labels.length - 1 ? (night ? '#71d6b0' : '#329c7c') : (night ? '#315f60' : '#d9eee4'))), borderRadius: 5, maxBarThickness: 32}]}, options: {...options, scales: axes()}});
        doughnut('genderChart', ['Female', 'Male', 'Other'], data.gender, [color('female', '#a6b9e8'), color('male', '#55a38c'), color('other', '#e8cc91')]);
    }

    async function refresh() {
        if (pending || document.hidden || sessionExpired || content.querySelector('.attendance-actions[open]')) return;
        pending = true;
        button.disabled = true;
        content.setAttribute('aria-busy', 'true');
        status.textContent = 'Updating dashboard…';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const url = new URL(content.dataset.url, window.location.origin);
            const selectedDate = new URLSearchParams(window.location.search).get('date');
            if (selectedDate) url.searchParams.set('date', selectedDate);
            const response = await fetch(url, {
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal
            });
            if (response.status === 401 || response.status === 403 || response.redirected) {
                sessionExpired = true;
                status.textContent = 'Your session has ended. Sign in again to refresh.';
                return;
            }
            if (!response.ok) throw new Error('Dashboard request failed');
            const payload = await response.json();
            if (typeof payload.html !== 'string' || !payload.charts) throw new Error('Invalid dashboard response');
            charts.forEach(chart => chart.destroy());
            charts = [];
            content.innerHTML = payload.html;
            renderCharts(payload);
            status.textContent = `Updated ${new Date().toLocaleTimeString()} · refreshes every 30 seconds`;
        } catch (_) {
            status.textContent = 'Could not refresh. Showing the last loaded data. Try Refresh again.';
        } finally {
            clearTimeout(timeout);
            pending = false;
            button.disabled = sessionExpired;
            content.setAttribute('aria-busy', 'false');
        }
    }

    renderCharts(JSON.parse(document.getElementById('dashboard-initial-data').textContent));
    new MutationObserver(() => {
        if (!latestPayload) return;
        charts.forEach(chart => chart.destroy());
        charts = [];
        renderCharts(latestPayload);
    }).observe(document.documentElement, {attributes: true, attributeFilter: ['data-theme']});
    button.addEventListener('click', refresh);
    content.addEventListener('pointerover', event => {
        const dropdown = event.target.closest('.attendance-actions');
        if (dropdown && event.pointerType === 'mouse') dropdown.open = true;
    });
    content.addEventListener('pointerout', event => {
        const dropdown = event.target.closest('.attendance-actions');
        if (dropdown && !dropdown.contains(event.relatedTarget) && !dropdown.contains(document.activeElement)) dropdown.open = false;
    });
    document.addEventListener('click', event => {
        content.querySelectorAll('.attendance-actions[open]').forEach(dropdown => {
            if (!dropdown.contains(event.target)) dropdown.open = false;
        });
    });
    content.addEventListener('keydown', event => {
        const dropdown = event.target.closest('.attendance-actions');
        if (dropdown && event.key === 'Escape') {
            dropdown.open = false;
            dropdown.querySelector('summary').focus();
        }
    });
    content.addEventListener('change', event => {
        if (event.target.id === 'attendance-report-date' && event.target.value) {
            event.target.form.requestSubmit();
        }
    });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
    window.addEventListener('focus', refresh);
    setInterval(refresh, 30000);
    refresh();
})();
