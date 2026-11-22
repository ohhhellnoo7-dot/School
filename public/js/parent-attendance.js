(() => {
    for (const form of document.querySelectorAll('[data-parent-attendance-filter]')) {
        form.addEventListener('change', () => form.requestSubmit());
    }

    const canvas = document.querySelector('[data-parent-attendance-chart]');
    if (!canvas || typeof Chart === 'undefined') return;

    const points = JSON.parse(canvas.dataset.parentAttendanceChart);
    const values = points.map(point => point.rate);
    const minimum = Math.max(0, Math.min(80, Math.floor((Math.min(...values.filter(value => value !== null)) - 4) / 5) * 5));
    const text = getComputedStyle(canvas).color;
    const labels = {
        id: 'attendance-point-labels',
        afterDatasetsDraw(chart) {
            const {ctx} = chart;
            ctx.save();
            ctx.font = '700 12px system-ui';
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'center';
            chart.getDatasetMeta(0).data.forEach((point, index) => {
                if (point.skip) return;
                const label = values[index] + '%';
                const width = ctx.measureText(label).width + 17;
                const centerX = Math.max(chart.chartArea.left + width / 2, Math.min(point.x, chart.chartArea.right - width / 2));
                const top = point.y - 32;
                ctx.beginPath();
                ctx.roundRect(centerX - width / 2, top, width, 20, 10);
                ctx.fillStyle = '#ece4ff';
                ctx.fill();
                ctx.fillStyle = '#48218e';
                ctx.fillText(label, centerX, top + 10);
            });
            ctx.restore();
        },
    };

    new Chart(canvas, {
        type: 'line',
        plugins: [labels],
        data: {
            labels: points.map(point => point.month),
            datasets: [{
                data: values,
                spanGaps: false,
                fill: true,
                borderColor: '#8339ef',
                borderWidth: 3,
                cubicInterpolationMode: 'monotone',
                pointRadius: 4.5,
                pointBackgroundColor: '#fff',
                pointBorderWidth: 3,
                backgroundColor: ({chart}) => {
                    if (!chart.chartArea) return '#a478f344';
                    const tint = chart.ctx.createLinearGradient(0, chart.chartArea.top, 0, chart.chartArea.bottom);
                    tint.addColorStop(0, '#a478f3a6');
                    tint.addColorStop(1, '#a478f30a');
                    return tint;
                },
            }],
        },
        options: {
            maintainAspectRatio: false,
            animation: false,
            layout: {padding: {top: 36, right: 10, left: 5}},
            plugins: {
                legend: {display: false},
                tooltip: {callbacks: {
                    title: ([item]) => item ? points[item.dataIndex].label : '',
                    label: item => item.parsed.y + '%',
                }},
            },
            scales: {
                x: {offset: true, grid: {display: false}, ticks: {color: text}},
                y: {min: minimum, max: 100, grid: {color: '#a482e125'}, ticks: {color: text, maxTicksLimit: 5, callback: value => value + '%'}},
            },
        },
    });
})();
