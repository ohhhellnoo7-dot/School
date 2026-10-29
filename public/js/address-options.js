(() => {
    document.querySelectorAll('[data-address-controls]').forEach(controls => {
        const input = document.getElementById(controls.dataset.addressControls);
        if (!input) return;
        const mode = controls.querySelector('[data-address-mode]');
        const button = controls.querySelector('[data-address-random]');
        const note = controls.querySelector('[data-address-note]');
        let manualAddress = input.value;
        const generate = () => {
            // Example addresses only: no external services or location collection.
            const number = max => window.crypto.getRandomValues(new Uint32Array(1))[0] % max + 1;
            input.value = `House ${number(250)}, Street ${number(600)}, Phnom Penh, Cambodia`;
            input.dispatchEvent(new Event('input', {bubbles: true}));
            input.dispatchEvent(new Event('change', {bubbles: true}));
        };
        mode.addEventListener('change', () => {
            const random = mode.value === 'random';
            button.hidden = note.hidden = !random;
            if (random) { manualAddress = input.value; generate(); }
            else { input.value = manualAddress; input.dispatchEvent(new Event('input', {bubbles: true})); }
        });
        button.addEventListener('click', generate);
    });
    const parent = document.getElementById('parent_id');
    parent?.addEventListener('change', () => {
        const option = parent.selectedOptions[0];
        const fields = {guardian_gender: 'gender', guardian_address: 'address', guardian_phone: 'phone'};
        for (const [id, key] of Object.entries(fields)) {
            const input = document.getElementById(id);
            if (input && option?.dataset[key]) input.value = option.dataset[key];
        }
        const number = document.querySelector('[aria-label="Parent profile number"]');
        if (number) number.value = option?.dataset.profileNumber || '';
    });
})();
