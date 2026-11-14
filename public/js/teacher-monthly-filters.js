(() => {
    const form = document.getElementById('teacher-monthly-filters');
    if (!form) return;

    form.addEventListener('change', (event) => {
        if (!['month', 'program', 'session', 'date'].includes(event.target.name)) return;
        // A selected day takes priority on the server, so clear it when changing months.
        if (event.target.name === 'month') form.elements.date.value = '';
        if (!form.reportValidity()) return;

        const status = document.createElement('input');
        status.type = 'hidden';
        status.name = 'status';
        status.value = form.dataset.status || '';
        form.append(status);
        form.requestSubmit();
        status.remove();
    });
})();
