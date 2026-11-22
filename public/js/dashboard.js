(() => {
    const content = document.getElementById('dashboard-content');
    const button = document.getElementById('dashboard-refresh');
    const status = document.getElementById('dashboard-sync-status');
    if (!content || !button || !status) return;

    let charts = [];
    let latestPayload;
    let pending = false;
    let sessionExpired = false;
    const weeklyFiltersAreEditing = () => content.querySelector('[data-weekly-filters]:focus-within, [data-weekly-filters][data-dirty]');

    function renderCharts(payload) {
        if (typeof Chart === 'undefined') return;
        latestPayload = payload;
        const night = document.documentElement.dataset.bsTheme === 'dark';
        const palette = getComputedStyle(content);
        const color = (name, fallback) => palette.getPropertyValue('--chart-' + name).trim() || fallback;
        const data = payload.charts;
        Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
        Chart.defaults.font.size = 11;
        Chart.defaults.color = palette.getPropertyValue('--muted').trim() || '#98a4ab';
        const axes = () => ({x: {grid: {display: false}, border: {display: false}}, y: {beginAtZero: true, grid: {color: color('grid', night ? '#a7b7d225' : '#83909825')}, border: {display: false}, ticks: {maxTicksLimit: 5, precision: 0}}});
        const create = (id, config) => {
            const canvas = document.getElementById(id);
            if (canvas) charts.push(new Chart(canvas, config));
        };
        const options = {maintainAspectRatio: false, animation: false, plugins: {legend: {display: false}}};
        const weeklyCanvas = content.querySelector('#weeklyAttendanceChart');
        if (weeklyCanvas && data.weeklyAttendance) {
            const days = data.weeklyAttendance;
            const line = palette.getPropertyValue('--action').trim() || color('line', '#007c89');
            const surface = palette.getPropertyValue('--surface').trim() || '#fff';
            const percent = new Intl.NumberFormat(document.documentElement.lang || undefined, {maximumFractionDigits: 2});
            const weeklyAxes = axes();
            create('weeklyAttendanceChart', {
                type: 'line',
                data: {labels: days.map(day => day.label), datasets: [{
                    label: weeklyCanvas.dataset.chartLabel,
                    data: days.map(day => day.rate),
                    borderColor: line,
                    fill: false,
                    spanGaps: false,
                    cubicInterpolationMode: 'monotone',
                    borderWidth: 3,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointHitRadius: 12,
                    pointBackgroundColor: line,
                    pointBorderColor: surface,
                    pointBorderWidth: 2,
                }]},
                options: {
                    ...options,
                    layout: {padding: {top: 10, right: 10}},
                    interaction: {mode: 'nearest', intersect: true},
                    scales: {
                        x: {...weeklyAxes.x, offset: true, ticks: {
                            autoSkip: false,
                            maxRotation: 0,
                            callback(value) {
                                return this.chart.width < 440 ? days[value].shortLabel : days[value].label;
                            },
                        }},
                        y: {...weeklyAxes.y, min: 0, max: 100, ticks: {stepSize: 25, callback: value => `${value}%`}},
                    },
                    plugins: {...options.plugins, tooltip: {
                        callbacks: {
                            label: ({parsed}) => `${weeklyCanvas.dataset.chartLabel}: ${percent.format(parsed.y)}%`,
                            afterLabel: ({dataIndex}) => `${days[dataIndex].attended} / ${days[dataIndex].recorded} ${weeklyCanvas.dataset.attendedLabel}`,
                        },
                    }},
                },
            });
        }
        const doughnut = (id, labels, values, colors) => create(id, {type: 'doughnut', data: {labels, datasets: [{data: values, backgroundColor: colors, borderWidth: 0, borderRadius: 5}]}, options: {...options, cutout: '77%'}});
        doughnut('attendanceChart', ['Present', 'Absent', 'Late', 'Excused'], data.attendance, [color('present', '#13ca78'), color('absent', '#ff707d'), color('late', '#ffcb45'), color('excused', '#aeb9cd')]);
        const teacherValues = data.teacherAttendance || [0, 0, 0, 0];
        const teacherTotal = teacherValues.reduce((sum, value) => sum + value, 0);
        create('teacherAttendanceChart', {type: 'doughnut', data: {labels: teacherTotal ? ['Present', 'Absent', 'Late', 'Excused'] : ['No records'], datasets: [{data: teacherTotal ? teacherValues : [1], backgroundColor: teacherTotal ? ['#329c7c', '#d45669', '#c68b25', '#8d96af'] : ['#dce5e2'], borderWidth: 0}]}, options: {...options, cutout: '77%', plugins: {legend: {display: false}, tooltip: {enabled: teacherTotal > 0}}}});
        const dashboardColor = (name, fallback) => palette.getPropertyValue('--dashboard-' + name).trim() || fallback;
        const trendColor = dashboardColor('line', '#128443');
        create('revenueChart', {
            type: 'line',
            data: {labels: data.labels, datasets: [{
                label: payload.showRevenue ? 'Collected (USD)' : 'New students',
                data: payload.showRevenue ? data.revenue : data.enrollment,
                borderColor: trendColor,
                backgroundColor: ({chart}) => {
                    if (!chart.chartArea) return 'transparent';
                    const gradient = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
                    gradient.addColorStop(0, `color-mix(in srgb, ${trendColor} 45%, transparent)`);
                    gradient.addColorStop(1, 'transparent');
                    return gradient;
                },
                fill: true,
                tension: .35,
                cubicInterpolationMode: 'monotone',
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: trendColor,
                pointBorderColor: palette.getPropertyValue('--surface').trim() || '#fff',
                pointBorderWidth: 2,
            }]},
            options: {...options, scales: axes()},
        });
        const genderColors = [dashboardColor('female', '#e54b99'), dashboardColor('male', '#168be4'), dashboardColor('other', '#8060d6')];
        if (data.gender.some(Boolean)) {
            doughnut('genderChart', ['Female', 'Male', 'Other'], data.gender, genderColors);
        } else {
            create('genderChart', {
                type: 'doughnut',
                data: {datasets: [{data: [1], backgroundColor: palette.getPropertyValue('--border-color').trim(), borderWidth: 0}]},
                options: {...options, cutout: '77%', events: []},
            });
        }
    }

    async function refresh() {
        if (pending || document.hidden || sessionExpired || weeklyFiltersAreEditing() || content.querySelector('.attendance-actions[open]')) return;
        pending = true;
        button.disabled = true;
        content.setAttribute('aria-busy', 'true');
        status.textContent = 'Updating dashboard…';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 15000);
        try {
            const url = new URL(content.dataset.url, window.location.origin);
            const selected = new URLSearchParams(window.location.search);
            for (const key of ['date', 'session', 'attendance_week', 'weekly_program', 'weekly_grade', 'weekly_class', 'weekly_session']) {
                if (selected.get(key)) url.searchParams.set(key, selected.get(key));
            }
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
            if (weeklyFiltersAreEditing()) {
                status.textContent = 'Dashboard refresh paused while editing attendance filters.';
                return;
            }
            charts.forEach(chart => chart.destroy());
            charts = [];
            const calendar = content.querySelector('#dashboard-khmer-calendar');
            const calendarFocus = calendar?.contains(document.activeElement) ? document.activeElement : null;
            content.innerHTML = payload.html;
            if (calendar) {
                content.querySelector('#dashboard-khmer-calendar')?.replaceWith(calendar);
                calendarFocus?.focus({preventScroll: true});
            }
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
        const weeklyFilters = event.target.closest('[data-weekly-filters]');
        if (weeklyFilters) weeklyFilters.dataset.dirty = '';
        if ((event.target.id === 'attendance-report-date' && event.target.value) || event.target.id === 'attendance-report-session') {
            event.target.form.requestSubmit();
        }
    });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
    window.addEventListener('focus', refresh);
    setInterval(refresh, 30000);
    refresh();
})();
