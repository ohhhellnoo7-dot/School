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
            for (const id of ['salary-teacher-count', 'salary-payroll-month', 'salary-payroll-metrics', 'salary-payroll-overview', 'salary-monthly-attendance', 'salary-export', 'salary-weekend-calendar']) {
                const current = document.getElementById(id);
                const updated = documentResult.getElementById(id);
                if (current && updated) current.replaceWith(updated);
            }
            document.getElementById('salary-rate-weekend')?.dispatchEvent(new Event('input'));
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
    const views = [...dialog.querySelectorAll('[data-salary-view]')];
    const switchView = (active) => {
        views.forEach((button) => {
            const selected = button === active;
            button.setAttribute('aria-pressed', String(selected));
            button.classList.toggle('btn-primary', selected);
            button.classList.toggle('btn-outline-primary', !selected);
            document.getElementById(button.getAttribute('aria-controls')).hidden = !selected;
        });
    };
    views.forEach((button) => button.addEventListener('click', () => switchView(button)));
    dialog.addEventListener('invalid', (event) => {
        const panel = event.target.closest('#salary-weekday, #salary-weekend');
        if (panel?.hidden) switchView(views.find((button) => button.getAttribute('aria-controls') === panel.id));
    }, true);
    const weekendRate = dialog.querySelector('#salary-rate-weekend');
    const weekendCurrency = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });
    const updateWeekendPreview = () => {
        dialog.querySelectorAll('[data-weekend-pay-hours]').forEach((cell) => {
            cell.textContent = weekendRate.validity.valid
                ? weekendCurrency.format(weekendRate.valueAsNumber * Number(cell.dataset.weekendPayHours))
                : '—';
        });
    };
    weekendRate.addEventListener('input', updateWeekendPreview);
    updateWeekendPreview();
    if (weekendRate.classList.contains('is-invalid')) {
        switchView(views.find((button) => button.dataset.salaryView === 'weekend'));
    }
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
