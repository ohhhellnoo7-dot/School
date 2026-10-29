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
})();
