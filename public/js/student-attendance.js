(() => {
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
            element.textContent = count.toLocaleString(document.documentElement.lang || 'en');
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
