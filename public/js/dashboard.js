(() => {
    const content = document.getElementById('dashboard-content');
    const button = document.getElementById('dashboard-refresh');
    const status = document.getElementById('dashboard-sync-status');
    if (!content || !button || !status) return;

    let charts = [];
    let monthlyChart;
    let attendanceMode = 'combined';
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
            if (!canvas) return;
            const chart = new Chart(canvas, config);
            charts.push(chart);
            return chart;
        };
        const options = {maintainAspectRatio: false, animation: false, plugins: {legend: {display: false}}};
        const monthlyCanvas = content.querySelector('#monthlyAttendanceChart');
        if (monthlyCanvas && data.monthlyAttendance) {
            const days = data.monthlyAttendance;
            const monthlyAxes = axes();
            const action = palette.getPropertyValue('--action').trim() || '#007c89';
            const rates = days.map(day => day.rate);
            const dateFormat = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {dateStyle: 'medium', timeZone: 'UTC'});
            const shading = ({chart}) => {
                if (!chart.chartArea) return 'rgba(21, 190, 179, .35)';
                const fill = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
                fill.addColorStop(0, 'rgba(21, 190, 179, .56)');
                fill.addColorStop(1, 'rgba(21, 190, 179, .1)');
                return fill;
            };
            monthlyChart = create('monthlyAttendanceChart', {
                type: 'line',
                data: {
                    labels: days.map(day => day.label),
                    datasets: [
                        {
                            label: monthlyCanvas.dataset.rateLabel,
                            data: rates,
                            hidden: attendanceMode === 'column',
                            borderColor: action,
                            borderWidth: 2,
                            backgroundColor: shading,
                            fill: attendanceMode === 'area' ? 'origin' : false,
                            cubicInterpolationMode: 'monotone',
                            pointBackgroundColor: palette.getPropertyValue('--surface').trim() || '#fff',
                            pointRadius: 3.5,
                            pointHoverRadius: 6,
                            pointHitRadius: 8,
                        },
                        {
                            data: days.map(() => 90),
                            borderColor: '#ee981d',
                            borderDash: [5, 4],
                            borderWidth: 1,
                            pointRadius: 0,
                            pointHitRadius: 0,
                            order: 1,
                        },
                        {
                            type: 'bar',
                            label: monthlyCanvas.dataset.rateLabel,
                            data: rates,
                            hidden: attendanceMode === 'area',
                            backgroundColor: shading,
                            hoverBackgroundColor: 'rgba(21, 190, 179, .8)',
                            maxBarThickness: 28,
                            order: 2,
                        },
                    ],
                },
                options: {
                    ...options,
                    layout: {padding: {top: 8, right: 8}},
                    scales: {
                        x: {...monthlyAxes.x, offset: true, ticks: {maxRotation: 0}},
                        y: {...monthlyAxes.y, min: 0, max: 100,
                            title: {display: true, text: monthlyCanvas.dataset.rateLabel},
                            ticks: {stepSize: 25, callback: value => value + '%'},
                        },
                    },
                    interaction: {mode: 'index', intersect: false},
                    plugins: {
                        ...options.plugins,
                        tooltip: {
                            filter: item => item.datasetIndex === (attendanceMode === 'column' ? 2 : 0),
                            callbacks: {
                                title: ([item]) => item ? dateFormat.format(new Date(days[item.dataIndex].date)) : '',
                                label: ({parsed}) => monthlyCanvas.dataset.rateLabel + ': ' + parsed.y + '%',
                                afterLabel: ({dataIndex}) => Object.entries(days[dataIndex].counts)
                                    .map(([status, count]) => monthlyCanvas.dataset[status] + ': ' + count),
                            },
                        },
                    },
                },
            });
            content.querySelectorAll('[data-attendance-mode]').forEach(control => {
                control.setAttribute('aria-pressed', String(control.dataset.attendanceMode === attendanceMode));
            });
        }
        const doughnut = (id, labels, values, colors) => create(id, {type: 'doughnut', data: {labels, datasets: [{data: values, backgroundColor: colors, borderWidth: 0, borderRadius: 5}]}, options: {...options, cutout: '77%'}});
        doughnut('attendanceChart', ['Present', 'Absent', 'Late', 'Excused'], data.attendance, [color('present', '#13ca78'), color('absent', '#ff707d'), color('late', '#ffcb45'), color('excused', '#aeb9cd')]);
        const teacherValues = data.teacherAttendance || [0, 0, 0, 0];
        const teacherTotal = teacherValues.reduce((sum, value) => sum + value, 0);
        create('teacherAttendanceChart', {type: 'doughnut', data: {labels: teacherTotal ? ['Present', 'Absent', 'Late', 'Excused'] : ['No records'], datasets: [{data: teacherTotal ? teacherValues : [1], backgroundColor: teacherTotal ? ['#329c7c', '#d45669', '#c68b25', '#8d96af'] : ['#dce5e2'], borderWidth: 0}]}, options: {...options, cutout: '77%', plugins: {legend: {display: false}, tooltip: {enabled: teacherTotal > 0}}}});
        const dashboardColor = (name, fallback) => palette.getPropertyValue('--dashboard-' + name).trim() || fallback;
        if (content.querySelector('#revenueChart')) {
            const revenue = data.revenue;
            const peak = Math.max(...revenue);
            const accent = dashboardColor('line', '#007c89');
            const ink = palette.getPropertyValue('--ink').trim() || '#253c34';
            const format = new Intl.NumberFormat(document.documentElement.lang || undefined, {maximumFractionDigits: 2});
            const money = new Intl.NumberFormat(document.documentElement.lang || undefined, {style: 'currency', currency: 'USD'});
            const revenueAxes = axes();

            create('revenueChart', {
                type: 'bar',
                data: {labels: data.labels, datasets: [{
                    data: revenue,
                    backgroundColor: ({raw}) => raw === 0 ? `color-mix(in srgb, ${accent} 22%, transparent)`
                        : raw === peak ? accent : `color-mix(in srgb, ${accent} 62%, transparent)`,
                    borderRadius: 8,
                    borderSkipped: 'bottom',
                    maxBarThickness: 68,
                    minBarLength: 3,
                }]},
                options: {
                    ...options,
                    layout: {padding: {top: 20, right: 8}},
                    scales: {
                        x: {...revenueAxes.x, ticks: {maxRotation: 0}},
                        y: {...revenueAxes.y, suggestedMax: peak > 0 ? peak * 1.1 : 1,
                            border: {...revenueAxes.y.border, dash: [4, 4]},
                            ticks: {...revenueAxes.y.ticks, callback: value => format.format(value)}},
                    },
                    interaction: {mode: 'index', intersect: false},
                    plugins: {...options.plugins, tooltip: {callbacks: {label: ({raw}) => money.format(raw)}}},
                },
                plugins: [{
                    id: 'revenueLabels',
                    afterDatasetsDraw(chart) {
                        const {ctx, chartArea} = chart;
                        const slotWidth = chartArea.width / revenue.length;
                        ctx.save();
                        ctx.font = '600 11px Inter, system-ui, sans-serif';
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        chart.getDatasetMeta(0).data.forEach(({x, y, base}, index) => {
                            const label = format.format(revenue[index]);
                            const width = ctx.measureText(label).width + 12;
                            if (width > slotWidth - 2) return;
                            const top = Math.min(y, base) - 23;
                            ctx.beginPath();
                            ctx.roundRect(x - width / 2, top, width, 18, 6);
                            ctx.fillStyle = `color-mix(in srgb, ${accent} 14%, ${palette.getPropertyValue('--surface').trim() || '#fff'})`;
                            ctx.fill();
                            ctx.fillStyle = ink;
                            ctx.fillText(label, x, top + 9);
                        });
                        ctx.restore();
                    },
                }],
            });
        }
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
            for (const key of ['date', 'session', 'weekly_program', 'weekly_grade', 'weekly_class', 'weekly_session', 'attendance_scope']) {
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
            const modeFocus = content.contains(document.activeElement) ? document.activeElement.dataset.attendanceMode : null;
            const ratesWereOpen = content.querySelector('[data-monthly-rates]')?.open;
            const previousFeed = content.querySelector('[data-attendance-message-feed]');
            const feedScroll = previousFeed?.scrollTop;
            const feedWasFocused = previousFeed && previousFeed === document.activeElement;
            content.innerHTML = payload.html;
            const feed = content.querySelector('[data-attendance-message-feed]');
            if (feed && previousFeed?.dataset.attendanceMessageFeed === feed.dataset.attendanceMessageFeed) {
                feed.scrollTop = feedScroll;
            }
            if (feedWasFocused) feed?.focus({preventScroll: true});
            const rates = content.querySelector('[data-monthly-rates]');
            if (rates && ratesWereOpen) rates.open = true;
            if (calendar) {
                content.querySelector('#dashboard-khmer-calendar')?.replaceWith(calendar);
                calendarFocus?.focus({preventScroll: true});
            }
            renderCharts(payload);
            if (modeFocus) content.querySelector(`[data-attendance-mode="${modeFocus}"]`)?.focus({preventScroll: true});
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
    content.addEventListener('click', event => {
        const control = event.target.closest('[data-attendance-mode]');
        if (!control || !monthlyChart) return;
        attendanceMode = control.dataset.attendanceMode;
        content.querySelector('[data-attendance-mode][aria-pressed="true"]')?.setAttribute('aria-pressed', 'false');
        control.setAttribute('aria-pressed', 'true');
        monthlyChart.setDatasetVisibility(0, attendanceMode !== 'column');
        monthlyChart.setDatasetVisibility(2, attendanceMode !== 'area');
        monthlyChart.data.datasets[0].fill = attendanceMode === 'area' ? 'origin' : false;
        monthlyChart.update();
    });
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
