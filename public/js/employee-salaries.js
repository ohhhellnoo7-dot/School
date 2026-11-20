document.querySelectorAll('[data-salary-search]').forEach((form) => {
    let timer;
    let controller;
    const update = async () => {
        clearTimeout(timer);
        controller?.abort();
        controller = new AbortController();
        const signal = controller.signal;
        const url = new URL(form.action || window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        const results = document.getElementById('salary-search-results');
        results.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok) throw new Error('Search failed');
            const documentResult = new DOMParser().parseFromString(await response.text(), 'text/html');
            const replacement = documentResult.getElementById('salary-search-results');
            if (!replacement) throw new Error('Search results unavailable');
            if (signal.aborted) return;
            results.innerHTML = replacement.innerHTML;
            const count = document.getElementById('salary-teacher-count');
            const newCount = documentResult.getElementById('salary-teacher-count');
            if (count && newCount) count.textContent = newCount.textContent;
            const payrollMonth = document.getElementById('salary-payroll-month');
            const newPayrollMonth = documentResult.getElementById('salary-payroll-month');
            if (payrollMonth && newPayrollMonth) payrollMonth.textContent = newPayrollMonth.textContent;
            const metrics = document.getElementById('salary-payroll-metrics');
            const newMetrics = documentResult.getElementById('salary-payroll-metrics');
            if (metrics && newMetrics) metrics.innerHTML = newMetrics.innerHTML;
            const attendanceLink = document.getElementById('salary-monthly-attendance');
            const newAttendanceLink = documentResult.getElementById('salary-monthly-attendance');
            if (attendanceLink && newAttendanceLink) attendanceLink.href = newAttendanceLink.getAttribute('href');
            window.history.replaceState({}, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') window.location.assign(url);
        } finally {
            if (!signal.aborted) results.removeAttribute('aria-busy');
        }
    };
    form.addEventListener('submit', (event) => { event.preventDefault(); update(); });
    form.querySelector('[name="search"]').addEventListener('input', () => {
        clearTimeout(timer);
        controller?.abort();
        timer = setTimeout(update, 300);
    });
    form.querySelectorAll('input[type="month"], input[type="date"]').forEach((input) => input.addEventListener('change', update));
});

document.querySelectorAll('#salary-calculator').forEach((dialog) => {
    const form = dialog.querySelector('form');
    const sliders = [...form.querySelectorAll('input[type="range"]')];
    const estimates = Object.fromEntries(
        [...dialog.querySelectorAll('[data-estimate]')].map((element) => [element.dataset.estimate, element])
    );
    const rateCents = Number(dialog.dataset.hourlyCents);
    const usd = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
    const formatSalary = (hours) => usd.format(hours * rateCents / 100);

    const update = () => {
        const hours = Object.fromEntries(sliders.map((slider) => {
            const selected = slider.valueAsNumber;
            slider.closest('.salary-slider').style.setProperty('--slider-progress', `${100 * selected / Number(slider.max)}%`);
            dialog.querySelector(`[data-hours="${slider.name}"]`).textContent = `${selected} h`;
            return [slider.name, selected];
        }));
        const totalHours = hours.daytime + hours.evening;
        estimates.daytime.textContent = formatSalary(hours.daytime);
        estimates.evening.textContent = formatSalary(hours.evening);
        estimates.hours.textContent = `${totalHours} h`;
        estimates.total.value = formatSalary(totalHours);
    };

    document.querySelector('[data-open-salary-calculator]').addEventListener('click', () => {
        update();
        dialog.showModal();
    });
    form.addEventListener('input', update);
    form.addEventListener('reset', () => requestAnimationFrame(update));
});
