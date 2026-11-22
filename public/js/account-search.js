(() => {
    document.querySelectorAll('[data-account-search]').forEach(input => {
        const select = document.getElementById(input.dataset.accountSearch);
        const results = document.getElementById(`${select.id}-results`);
        const status = document.getElementById(`${select.id}-status`);
        const options = Array.from(select.options).filter(option => option.value);
        const syncEmail = () => {
            const option = select.selectedOptions[0];
            const email = document.getElementById(input.dataset.emailField);
            if (option?.dataset.email && email) {
                email.value = option.dataset.email;
                email.dispatchEvent(new Event('input', { bubbles: true }));
            }
        };
        select.addEventListener('change', syncEmail);
        if (select.id === 'parent_id') {
            const email = document.getElementById(input.dataset.emailField);
            const password = document.getElementById('parent_password');
            if (email && password) {
                const newPassword = document.getElementById('guardian-new-password');
                const existingPassword = document.getElementById('guardian-existing-password');
                const accountEmails = new Set(options.map(option => option.dataset.email.toLowerCase()));
                const syncPassword = () => {
                    const existing = accountEmails.has(email.value.trim().toLowerCase());
                    newPassword.hidden = password.disabled = existing;
                    existingPassword.hidden = !existing;
                    if (existing) password.value = '';
                };
                email.addEventListener('input', syncPassword);
                email.addEventListener('change', syncPassword);
                select.addEventListener('change', syncPassword);
                const name = document.getElementById('guardian_name');
                name.addEventListener('input', syncPassword);
                name.addEventListener('change', syncPassword);
                syncPassword();
            }
        }
        input.addEventListener('input', () => {
            const prefix = input.value.trim().toLowerCase();
            results.replaceChildren();
            results.hidden = !prefix;
            status.textContent = '';
            if (!prefix) return;
            const matches = options.filter(option => option.dataset.email.toLowerCase().startsWith(prefix));
            status.textContent = matches.length ? `${matches.length} matching accounts` : 'No matching accounts.';
            matches.forEach(option => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'list-group-item list-group-item-action text-break';
                button.textContent = option.textContent;
                button.addEventListener('click', () => {
                    select.value = option.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    input.value = option.dataset.email;
                    results.hidden = true;
                    status.textContent = 'Account selected.';
                });
                results.append(button);
            });
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'Escape') results.hidden = true;
            if (event.key === 'ArrowDown' && !results.hidden) {
                event.preventDefault();
                results.querySelector('button')?.focus();
            }
        });
    });

    const picker = document.getElementById('guardian-employee-picker');
    if (!picker) return;

    const toggle = document.getElementById('guardian-employee-toggle');
    const search = document.getElementById('guardian-employee-search');
    const count = document.getElementById('guardian-employee-count');
    const results = document.getElementById('guardian-employee-results');
    const guardian = document.getElementById('parent_id');
    const employees = [...guardian.querySelectorAll('option[data-employee]')];
    const normalize = value => value.trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase();

    function setOpen(open) {
        picker.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open) search.focus();
        else toggle.focus();
    }

    function renderEmployees() {
        const prefix = normalize(search.value);
        const matches = employees.filter(option => normalize(option.dataset.name).startsWith(prefix));
        count.textContent = matches.length ? `${matches.length} matching employees` : 'No matching employees.';
        results.replaceChildren(...matches.map(option => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action text-break';
            button.textContent = option.textContent;
            button.addEventListener('click', () => {
                guardian.value = option.value;
                guardian.dispatchEvent(new Event('change', { bubbles: true }));
                setOpen(false);
            });
            return button;
        }));
        results.scrollTop = 0;
    }

    toggle.addEventListener('click', () => {
        if (picker.hidden) renderEmployees();
        setOpen(picker.hidden);
    });
    search.addEventListener('input', renderEmployees);
    picker.addEventListener('keydown', event => {
        if (event.key === 'Escape' || (event.target === search && event.key === 'Enter')) {
            event.preventDefault();
            if (event.key === 'Escape') setOpen(false);
            else results.querySelector('button')?.focus();
        }
    });
})();
