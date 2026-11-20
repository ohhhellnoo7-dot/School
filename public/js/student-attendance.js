(() => {
    const locale = document.documentElement.lang || 'en';
    const numberFormat = new Intl.NumberFormat(locale);
    const percentFormat = new Intl.NumberFormat(locale, { style: 'percent', maximumFractionDigits: 1 });
    const summary = () => document.querySelector('.students-page .attendance-summary');

    const updateOverview = () => {
        const dashboard = summary();
        if (!dashboard) return;
        const total = Number(dashboard.querySelector('[data-attendance-count="total"]').dataset.count);
        dashboard.querySelectorAll('[data-attendance-segment]').forEach(segment => {
            const status = segment.dataset.attendanceSegment;
            const count = Number(dashboard.querySelector(`[data-attendance-count="${status}"]`).dataset.count);
            segment.style.flexGrow = count;
            dashboard.querySelectorAll(`[data-attendance-percent="${status}"]`).forEach(label => {
                label.textContent = percentFormat.format(total ? count / total : 0);
            });
        });
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

    animateCounts();
    document.addEventListener('student-attendance-summary-replaced', animateCounts);

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
