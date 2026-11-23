(() => {
    const locale = document.documentElement.lang || 'en';
    const numberFormat = new Intl.NumberFormat(locale);
    const percentFormat = new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 1 });
    const summary = () => document.querySelector('.students-page .attendance-summary');

    const renderWeek = chart => {
        if (!chart) return;
        const svg = chart.querySelector('svg');
        const points = Array.from(chart.querySelectorAll('[data-week-day]'), (day, index) => ({
            x: 81 + index * 74,
            rate: day.dataset.rate === '' ? null : Number(day.dataset.rate),
        }));
        const rates = points.flatMap(point => point.rate === null ? [] : [point.rate]);
        const min = rates.length && Math.min(...rates) >= 90 ? 90 : 0;
        const y = value => 132 - (value - min) * 112 / (100 - min);
        const draw = (tag, attributes, content) => {
            const node = document.createElementNS('http://www.w3.org/2000/svg', tag);
            Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value));
            if (content !== undefined) node.textContent = content;
            svg.append(node);
        };
        svg.replaceChildren();
        for (let step = 0; step <= 5; step++) {
            const value = min + step * (100 - min) / 5;
            draw('line', { x1: 44, y1: y(value), x2: 414, y2: y(value), class: 'student-week-guide' });
            draw('text', { x: 35, y: y(value) + 4, 'text-anchor': 'end' }, value + '%');
        }
        if (!rates.length) {
            draw('text', { x: 229, y: 80, 'text-anchor': 'middle' }, chart.dataset.emptyText);
        }
        points.forEach((point, index) => {
            if (point.rate === null) return;
            const previous = points[index - 1];
            if (previous && previous.rate !== null) {
                draw('polygon', { points: `${previous.x},132 ${previous.x},${y(previous.rate)} ${point.x},${y(point.rate)} ${point.x},132`, class: 'student-week-area' });
                draw('line', { x1: previous.x, y1: y(previous.rate), x2: point.x, y2: y(point.rate), class: 'student-week-line' });
            }
        });
        points.forEach(point => {
            if (point.rate === null) return;
            draw('circle', { cx: point.x, cy: y(point.rate), r: 4.5 });
            draw('text', { x: point.x, y: y(point.rate) - 9, 'text-anchor': 'middle' }, percentFormat.format(point.rate / 100));
        });
    };

    const updateOverview = () => {
        const dashboard = summary();
        if (!dashboard) return;
        const total = Number(dashboard.querySelector('[data-attendance-count="total"]').dataset.count);
        const counts = {};
        let offset = 0;
        dashboard.querySelectorAll('[data-attendance-segment]').forEach(segment => {
            const status = segment.dataset.attendanceSegment;
            const count = Number(dashboard.querySelector(`[data-attendance-count="${status}"]`).dataset.count);
            counts[status] = count;
            const length = total ? 100 * count / total : 0;
            segment.setAttribute('stroke-dasharray', `${length} 100`);
            segment.setAttribute('stroke-dashoffset', -offset);
            offset += length;
            dashboard.querySelectorAll(`[data-attendance-percent="${status}"]`).forEach(label => {
                label.textContent = percentFormat.format(total ? count / total : 0);
            });
        });
        const recorded = total - counts.unrecorded;
        const rate = recorded ? (counts.present + counts.late) / recorded : null;
        dashboard.querySelector('[data-attendance-rate]').textContent = rate === null ? '—' : percentFormat.format(rate);
        dashboard.querySelector('.attendance-distribution-legend .attendance-tone-unrecorded').hidden = !counts.unrecorded;
        const chart = dashboard.querySelector('[data-student-week]');
        const selectedDay = chart.querySelector(`[data-week-day="${chart.dataset.selectedDate}"]`);
        if (selectedDay) {
            selectedDay.dataset.rate = rate === null ? '' : String(Math.round(rate * 1000) / 10);
            renderWeek(chart);
        }
    };

    const animateCounts = () => {
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        summary()?.querySelectorAll('.attendance-summary-card [data-attendance-count]').forEach(element => {
            const count = Number(element.dataset.count);
            if (!count) return;
            const start = performance.now();
            const tick = now => {
                if (!element.isConnected || Number(element.dataset.count) !== count) return;
                const progress = Math.min((now - start) / 440, 1);
                element.textContent = numberFormat.format(Math.round(count * (1 - (1 - progress) ** 3)));
                if (progress < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        });
    };

    const initializeSummary = () => {
        animateCounts();
        renderWeek(summary()?.querySelector('[data-student-week]'));
    };
    initializeSummary();
    document.addEventListener('student-attendance-summary-replaced', initializeSummary);

    const updateRow = (form, status) => {
        form.dataset.attendanceState = status;
        form.closest('tr').dataset.attendanceRow = status;
        const badge = form.querySelector('[data-attendance-badge]');
        badge.dataset.tone = status;
        const select = form.querySelector('select');
        badge.querySelector('[data-attendance-label]').textContent = select.selectedOptions[0].textContent;
    };
    const adjustCount = (status, delta) => {
        document.querySelectorAll(`[data-attendance-count="${status}"]`).forEach(element => {
            const count = Math.max(0, Number(element.dataset.count) + delta);
            element.dataset.count = String(count);
            element.textContent = numberFormat.format(count);
        });
    };
    document.addEventListener('submit', async event => {
        const form = event.target.closest('form[data-student-attendance]');
        if (!form) return;
        event.preventDefault();
        const button = form.querySelector('button');
        const select = form.querySelector('select');
        const feedback = form.querySelector('[data-attendance-feedback]');
        if (button.disabled) return;
        const payload = new FormData(form);
        button.disabled = true;
        select.disabled = true;
        form.setAttribute('aria-busy', 'true');
        feedback.className = 'small d-block mt-1 text-secondary';
        feedback.textContent = 'Saving...';
        try {
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }, body: payload,
            });
            if (response.status === 401 || response.status === 419) throw new Error('Session expired. Refresh and sign in again.');
            if (response.status === 403) throw new Error('You do not have permission to change attendance.');
            const data = await response.json();
            if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Attendance could not be saved. Please try again.'));
            if (!data.success) throw new Error('Attendance could not be saved. Please try again.');
            feedback.className = 'small d-block mt-1 text-success';
            feedback.textContent = 'Saved';
            const previous = form.dataset.savedState;
            if (previous !== data.status) {
                adjustCount(previous, -1);
                adjustCount(data.status, 1);
                updateOverview();
            }
            form.dataset.savedState = data.status;
            select.value = data.status;
            updateRow(form, data.status);
        } catch (error) {
            feedback.className = 'small d-block mt-1 text-danger';
            feedback.textContent = error instanceof TypeError ? 'Connection failed. Try again.' : error.message;
        } finally {
            button.disabled = false;
            select.disabled = false;
            form.removeAttribute('aria-busy');
        }
    });
    document.addEventListener('change', event => {
        const form = event.target.closest('form[data-student-attendance]');
        if (form && event.target.matches('select[name="status"]')) {
            updateRow(form, event.target.value || 'unrecorded');
            const feedback = form.querySelector('[data-attendance-feedback]');
            feedback.className = 'small d-block mt-1 text-secondary';
            feedback.textContent = 'Not saved';
        }
    });
})();
