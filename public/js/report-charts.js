(() => {
    document.querySelectorAll('.reports-tabs a[href^="#"]').forEach(link => link.addEventListener('click', () => { document.querySelectorAll('.reports-tabs a').forEach(tab => tab.classList.remove('active')); link.classList.add('active'); }));
    if (typeof Chart === 'undefined') return;
    let charts = [];
    const createChart = (canvas, config) => charts.push(new Chart(canvas, config));
    function renderCharts() {
        charts.forEach(chart => chart.destroy());
        charts = [];
        const palette = getComputedStyle(document.querySelector('.reports-redesign') || document.documentElement);
        const color = (name, fallback) => palette.getPropertyValue(name).trim() || fallback;
        const primary = color('--primary', '#702df2');
        const surface = color('--surface', '#ffffff');
        const border = color('--border-color', '#e6defa');
    Chart.defaults.font.family = 'DM Sans, system-ui, sans-serif';
    Chart.defaults.color = color('--text-secondary', '#73698d');
    Chart.defaults.borderColor = color('--border-color', '#eee8f9');
    const programColors = [1,2,3,4,5].map(n => color('--report-program-' + n, primary));
    const statusColors = [color('--chart-present', '#0bbd78'), color('--chart-late', '#d99a21'), color('--chart-absent', '#e35d79')];
    document.querySelectorAll('[data-program-collection], [data-invoice-status]').forEach(canvas => {
        const values = JSON.parse(canvas.dataset.values).map(value => Math.max(0, value));
        const total = values.reduce((sum, n) => sum + n, 0);
        const invoice = canvas.hasAttribute('data-invoice-status');
        createChart(canvas, {type: 'doughnut', data: {labels: total ? (invoice ? ['Fully paid / waived', 'Partly paid', 'Unpaid'] : JSON.parse(canvas.dataset.labels)) : ['No records'], datasets: [{data: total ? values : [1], backgroundColor: total ? (invoice ? statusColors : programColors) : [border], borderWidth: 0, hoverOffset: 3}]}, options: {maintainAspectRatio: false, cutout: '75%', plugins: {legend: {display: false}, tooltip: {enabled: total > 0}}}});
    });
    const pie = document.getElementById('programStudentsChart');
    if (pie) {
        const values = JSON.parse(pie.dataset.values);
        const total = values.reduce((sum, n) => sum + n, 0);
        createChart(pie, {type: 'pie', data: {labels: total ? JSON.parse(pie.dataset.labels) : ['No enrollments'], datasets: [{data: total ? values : [1], backgroundColor: total ? [programColors[0],programColors[2],programColors[1],programColors[3],programColors[4]] : [border], borderWidth: 2, borderColor: surface}]}, options: {maintainAspectRatio: false, plugins: {legend: {position: 'bottom'}, tooltip: {enabled: total > 0}}}});
    }
    document.querySelectorAll('[data-fee-collection]').forEach(canvas => {
        const currency = canvas.dataset.currency;
        createChart(canvas, {
            type: 'line',
            data: {labels: JSON.parse(canvas.dataset.labels), datasets: [{label: `Net fees collected (${currency})`, data: JSON.parse(canvas.dataset.values), borderColor: primary, backgroundColor: context => { const chart = context.chart; const area = chart.chartArea; if (!area) return Chart.helpers.color(primary).alpha(.12).rgbString(); const gradient = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom); gradient.addColorStop(0, Chart.helpers.color(primary).alpha(.32).rgbString()); gradient.addColorStop(1, Chart.helpers.color(primary).alpha(.02).rgbString()); return gradient; }, fill: true, tension: 0.35, pointRadius: 4, pointBackgroundColor: surface, pointBorderWidth: 2, borderWidth: 3}]},
            options: {maintainAspectRatio: false, scales: {y: {beginAtZero: true, title: {display: true, text: currency}, grid: {color: border}, border: {display: false}}, x: {ticks: {maxTicksLimit: 6}, grid: {display: false}, border: {display: false}}}, plugins: {legend: {display: false}, tooltip: {callbacks: {label: context => `${Number(context.parsed.y).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})} ${currency}`}}}}
        });
    });
    }
    renderCharts();
    let scheduled;
    new MutationObserver(() => {
        cancelAnimationFrame(scheduled);
        scheduled = requestAnimationFrame(renderCharts);
    }).observe(document.documentElement, {attributes: true, attributeFilter: ['data-theme']});
})();
