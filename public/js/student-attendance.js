(() => {
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
        feedback.className = 'small d-block mt-1 text-secondary';
        feedback.textContent = 'Saving...';
        try {
            const response = await fetch(form.action, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }, body: payload,
            });
            if (response.status === 401 || response.status === 419) throw new Error('Session expired. Refresh and sign in again.');
            if (response.status === 403) throw new Error('You do not have permission to change attendance.');
            if (!response.ok) throw new Error('Attendance could not be saved. Please try again.');
            const data = await response.json();
            if (!data.success) throw new Error('Attendance could not be saved. Please try again.');
            feedback.className = 'small d-block mt-1 text-success';
            feedback.textContent = 'Saved';
            form.dataset.attendanceState = data.status;
        } catch (error) {
            feedback.className = 'small d-block mt-1 text-danger';
            feedback.textContent = error instanceof TypeError ? 'Connection failed. Try again.' : error.message;
        } finally {
            button.disabled = false;
            select.disabled = false;
        }
    });
    document.addEventListener('change', event => {
        const form = event.target.closest('form[data-student-attendance]');
        if (form) form.querySelector('[data-attendance-feedback]').textContent = 'Not saved';
    });
})();
