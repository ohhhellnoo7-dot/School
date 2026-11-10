document.querySelectorAll('[data-curriculum-form]').forEach(form => {
    const body = form.querySelector('[data-curriculum-rows]');
    const updateSummary = () => {
        const total = [...body.querySelectorAll('input[type=number]')].reduce((sum, input) => sum + (Number(input.value) || 0), 0);
        form.querySelector('[data-curriculum-total]').textContent = total;
        [...body.rows].forEach(row => { const name = row.querySelector('input').value.trim(); row.cells[2].textContent = ['Khmer Literature', 'Mathematics'].includes(name) ? '2 hours' : '1 hour'; });
    };
    form.addEventListener('input', updateSummary);
    const renumber = () => [...body.rows].forEach((row, index) => {
        row.querySelectorAll('input').forEach(input => { input.name = `subjects[${index}][${input.type === 'number' ? 'weekly_hours' : 'name'}]`; });
    });
    form.addEventListener('click', event => {
        if (event.target.closest('[data-remove-subject]')) { event.target.closest('tr').remove(); renumber(); updateSummary(); }
        if (event.target.closest('[data-add-subject]')) {
            const row = document.createElement('tr');
            row.innerHTML = '<td><input aria-label="Subject name" class="form-control" maxlength="100" required></td><td><input aria-label="Weekly hours" class="form-control" type="number" min="1" max="20" value="1" required></td><td>1 hour</td><td><button type="button" class="btn btn-sm btn-outline-secondary" data-remove-subject>Remove</button></td>';
            body.append(row); renumber(); updateSummary(); row.querySelector('input').focus();
        }
    });
    form.addEventListener('submit', renumber);
});
