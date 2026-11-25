(() => {
    const form = document.querySelector('[data-request-money-form]');
    if (!form) return;

    const amount = form.querySelector('#requested-amount');
    const plan = form.querySelector('[data-repayment-plan]');
    const months = form.querySelector('#repayment-months');
    const summary = form.querySelector('[data-repayment-summary]');
    const minimum = Number(form.dataset.planMinimumCents);
    const currency = new Intl.NumberFormat(document.documentElement.lang, {style: 'currency', currency: 'USD'});
    const money = cents => currency.format(cents / 100);
    const amountCents = () => Math.round(amount.valueAsNumber * 100);

    function updatePlan() {
        const cents = amountCents();
        if (!(cents >= minimum)) {
            plan.hidden = true;
            plan.disabled = true;
            months.value = '';
        }

        const count = Number(months.value);
        summary.textContent = '';
        if (plan.hidden || !count) return;

        if (count === 1) {
            summary.textContent = summary.dataset.single.replace(':amount', money(cents));
            return;
        }

        const installment = Math.ceil(cents / count);
        const last = cents - installment * (count - 1);
        const description = summary.dataset.installments.replace(':amount', money(installment)).replace(':months', count);
        summary.textContent = last === installment
            ? description
            : `${description} ${summary.dataset.final.replace(':amount', money(last))}`;
    }

    function showPlan() {
        plan.hidden = false;
        plan.disabled = false;
        updatePlan();
    }

    amount.addEventListener('input', updatePlan);
    months.addEventListener('change', updatePlan);
    form.addEventListener('submit', event => {
        if (amountCents() < minimum || !plan.hidden) return;
        event.preventDefault();
        showPlan();
        months.focus();
    });

    if (form.hasAttribute('data-reopen-plan') && amountCents() >= minimum) {
        showPlan();
        bootstrap.Modal.getOrCreateInstance(form.closest('.modal')).show();
    }
})();
