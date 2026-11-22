(() => {
    if (typeof Chart === 'undefined') return;

    const root = document.documentElement;
    const locale = root.lang || undefined;

    for (const canvas of document.querySelectorAll('[data-finance-months]')) {
        const panel = canvas.closest('[data-finance-trend]');
        const modes = panel.querySelector('.finance-chart-modes');
        const labels = JSON.parse(canvas.dataset.labels);
        const amounts = JSON.parse(canvas.dataset.values);
        const currency = canvas.dataset.currency;
        const money = new Intl.NumberFormat(locale, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const numbers = new Intl.NumberFormat(locale);
        const foreground = getComputedStyle(panel).getPropertyValue('--text-secondary').trim() || '#42607b';
        const tint = (opacity) => ({chart}) => {
            if (!chart.chartArea) return 'rgba(14, 188, 181, .23)';
            const gradient = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
            gradient.addColorStop(0, `rgba(14, 188, 181, ${opacity})`);
            gradient.addColorStop(1, 'rgba(14, 188, 181, .025)');
            return gradient;
        };
        const options = {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            interaction: {mode: 'index', intersect: false},
            layout: {padding: {top: 8, right: 12}},
            scales: {
                x: {grid: {display: false}, ticks: {color: foreground, maxRotation: 0}},
                y: {beginAtZero: true, border: {display: false}, grid: {color: '#68a4c52b'}, ticks: {
                    color: foreground,
                    maxTicksLimit: 6,
                    callback: value => numbers.format(value),
                }},
            },
            plugins: {
                legend: {display: false},
                tooltip: {
                    displayColors: false,
                    backgroundColor: '#007983',
                    callbacks: {
                        title: ([item]) => item ? `${item.label} ${canvas.dataset.year}` : '',
                        label: item => `${money.format(item.parsed.y)} ${currency}`,
                    },
                },
            },
        };
        const render = mode => new Chart(canvas, {
            type: mode === 'column' ? 'bar' : 'line',
            data: {
                labels,
                datasets: [{
                    data: amounts,
                    borderColor: '#008e92',
                    borderWidth: mode === 'column' ? 0 : 3,
                    backgroundColor: tint(mode === 'line' ? .34 : .66),
                    fill: mode !== 'column',
                    cubicInterpolationMode: 'monotone',
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: mode === 'area' ? 0 : 3.5,
                    pointHoverRadius: 5,
                    borderRadius: 5,
                    maxBarThickness: 42,
                }],
            },
            options,
        });

        let chart = render(canvas.dataset.mode || 'line');
        if (!modes) continue;
        modes.hidden = false;
        modes.addEventListener('click', event => {
            const button = event.target.closest('[data-finance-mode]');
            if (!button || button.getAttribute('aria-pressed') === 'true') return;
            modes.querySelector('[aria-pressed="true"]').setAttribute('aria-pressed', 'false');
            button.setAttribute('aria-pressed', 'true');
            chart.destroy();
            chart = render(button.dataset.financeMode);
        });
    }

    for (const canvas of document.querySelectorAll('[data-finance-programs]')) {
        const values = JSON.parse(canvas.dataset.values);
        const colors = JSON.parse(canvas.dataset.colors);
        const hasData = values.some(value => value > 0);
        new Chart(canvas, {
            type: 'doughnut',
            data: {datasets: [{data: hasData ? values : [1], backgroundColor: hasData ? colors : ['#9bb4c344'], borderWidth: 0}]},
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                cutout: '70%',
                plugins: {legend: {display: false}, tooltip: {enabled: false}},
            },
        });
    }
})();
