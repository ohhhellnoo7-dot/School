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
    const form = dialog.querySelector('#salary-calculator-weekday');
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

    const tabs = [...dialog.querySelectorAll('[data-calculator-tab]')];
    const weekend = dialog.querySelector('#salary-calculator-weekend');
    const selectTab = (active) => {
        tabs.forEach((tab) => {
            const selected = tab === active;
            tab.setAttribute('aria-pressed', String(selected));
            tab.classList.toggle('btn-primary', selected);
            tab.classList.toggle('btn-outline-primary', !selected);
            document.getElementById(tab.getAttribute('aria-controls')).hidden = !selected;
        });
    };
    tabs.forEach((tab) => tab.addEventListener('click', () => selectTab(tab)));

    const teacher = weekend.querySelector('#weekend-salary-teacher');
    const approvedRate = weekend.querySelector('#weekend-approved-rate');
    const approvedHours = weekend.querySelector('#weekend-approved-hours');
    const estimateRate = weekend.querySelector('#weekend-estimate-rate');
    const estimateHours = weekend.querySelector('#weekend-estimate-hours');
    const estimateDays = weekend.querySelector('#weekend-estimate-days');
    const number = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });
    const renderEstimate = () => {
        const hours = estimateHours.valueAsNumber;
        const totalHours = hours * Number(estimateDays.value);
        const minHours = Number(estimateHours.min);
        const progress = (hours - minHours) / (Number(estimateHours.max) - minHours) * 100;
        estimateHours.closest('.salary-slider').style.setProperty('--slider-progress', `${progress}%`);
        weekend.querySelector('#weekend-hours-per-day').value = `${number.format(hours)} h`;
        weekend.querySelector('#weekend-estimated-hours').value = `${number.format(totalHours)} h`;
        weekend.querySelector('#weekend-estimated-pay').value = estimateRate.validity.valid ? usd.format(estimateRate.valueAsNumber * totalHours) : '—';
    };
    const renderSettings = () => {
        const rate = approvedRate.valueAsNumber;
        const day = rate * approvedHours.valueAsNumber;
        const amounts = { hour: rate, day, weekend: day * 2, monthly: day * 2 * 52 / 12 };
        weekend.querySelectorAll('[data-weekend-summary]').forEach((value) => {
            const amount = amounts[value.dataset.weekendSummary];
            value.textContent = approvedRate.validity.valid && Number.isFinite(amount) && amount >= 0 && (value.dataset.weekendSummary === 'hour' || approvedHours.validity.valid)
                ? usd.format(amount)
                : '—';
        });
        estimateRate.value = approvedRate.value;
        if (approvedHours.validity.valid) estimateHours.value = approvedHours.value;
        renderEstimate();
    };
    const loadTeacher = () => {
        const { rate, hours } = teacher.selectedOptions[0].dataset;
        approvedRate.value = rate;
        approvedHours.value = hours;
        renderSettings();
    };
    teacher.addEventListener('change', loadTeacher);
    [approvedRate, approvedHours].forEach((input) => input.addEventListener('input', renderSettings));
    [estimateRate, estimateHours, estimateDays].forEach((input) => input.addEventListener('input', renderEstimate));
    if (weekend.querySelector('.text-danger')) renderSettings();
    else loadTeacher();
    if (weekend.hasAttribute('data-reopen')) {
        selectTab(tabs[1]);
        dialog.showModal();
    }
});
