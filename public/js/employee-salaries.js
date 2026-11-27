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
            document.getElementById('payday-withdrawal')?.remove();
            for (const id of ['salary-teacher-count', 'salary-payroll-month', 'salary-payroll-metrics', 'salary-payroll-overview', 'salary-monthly-attendance', 'salary-export']) {
                const current = document.getElementById(id);
                const updated = documentResult.getElementById(id);
                if (current && updated) current.replaceWith(updated);
            }
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

document.querySelectorAll('#salary-rates-dialog').forEach((dialog) => {
    const rateCurrency = new Intl.NumberFormat('en-US', {
        style: 'currency', currency: 'USD', maximumFractionDigits: 4,
    });
    dialog.querySelectorAll('[data-session-hours]').forEach((input) => {
        const preview = dialog.querySelector(`output[for="${input.id}"]`);
        const { sessionHours, estimateDays } = input.dataset;
        const updatePreview = () => {
            if (!input.validity.valid) {
                preview.value = '';
                return;
            }
            const weeklyHourValue = input.valueAsNumber / sessionHours;
            const hourlyRate = weeklyHourValue / estimateDays;
            preview.value = `${rateCurrency.format(input.valueAsNumber)} ÷ ${sessionHours} = ${rateCurrency.format(weeklyHourValue)}. Then ÷ ${estimateDays} = ${rateCurrency.format(hourlyRate)}/h.`;
        };
        input.addEventListener('input', updatePreview);
        updatePreview();
    });
    document.querySelector('[data-edit-salary-rates]').addEventListener('click', () => dialog.showModal());
    dialog.querySelector('[data-cancel-salary-rates]').addEventListener('click', () => dialog.close());
    if (dialog.hasAttribute('data-reopen')) dialog.showModal();
});

document.querySelectorAll('#salary-calculator').forEach((dialog) => {
    const form = dialog.querySelector('form');
    const sliders = [...form.querySelectorAll('input[type="range"]')];
    const estimates = Object.fromEntries(
        [...dialog.querySelectorAll('[data-estimate]')].map((element) => [element.dataset.estimate, element])
    );
    const defaultDays = Number(dialog.dataset.defaultDays);
    const usd = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
    const formatDays = (days) => days === 1 ? '1 day' : `${days} days`;

    const update = () => {
        const { morning, afternoon, evening, days } = Object.fromEntries(sliders.map((slider) => {
            const selected = slider.valueAsNumber;
            const min = Number(slider.min);
            const progress = 100 * (selected - min) / (Number(slider.max) - min);
            slider.closest('.salary-slider').style.setProperty('--slider-progress', `${progress}%`);
            dialog.querySelector(`[data-hours="${slider.name}"]`).textContent = slider.name === 'days' ? formatDays(selected) : `${selected} h/week`;
            return [slider.name, selected];
        }));
        const pay = (name, hours) => {
            const { monthlyCents, sessionHours } = form.elements[name].dataset;
            return hours * monthlyCents * days / (sessionHours * defaultDays * 100);
        };
        const daytimePay = pay('morning', morning) + pay('afternoon', afternoon);
        const eveningPay = pay('evening', evening);
        estimates.daytime.textContent = usd.format(daytimePay);
        estimates.evening.textContent = usd.format(eveningPay);
        estimates.hours.textContent = `${morning + afternoon + evening} h/week`;
        estimates['formula-days'].textContent = formatDays(days);
        estimates.total.value = usd.format(daytimePay + eveningPay);
    };

    document.querySelector('[data-open-salary-calculator]').addEventListener('click', () => {
        update();
        dialog.showModal();
    });
    form.addEventListener('input', update);
    form.addEventListener('reset', () => requestAnimationFrame(update));
});
