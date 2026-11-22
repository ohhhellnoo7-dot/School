(() => {
    document.querySelectorAll('[data-address-controls]').forEach(controls => {
        const input = document.getElementById(controls.dataset.addressControls);
        if (!input) return;
        const mode = controls.querySelector('[data-address-mode]');
        const button = controls.querySelector('[data-address-random]');
        const note = controls.querySelector('[data-address-note]');
        let manualAddress = input.value;
        let previousMode = mode.value;
        const source = document.getElementById(controls.dataset.addressSource || '');
        const sourceMode = source && document.getElementById(`${source.id}-mode`);
        const syncAddress = () => {
            if (mode.value !== 'same' || !source) return;
            input.value = source.value;
            input.dispatchEvent(new Event('input', {bubbles: true}));
            input.dispatchEvent(new Event('change', {bubbles: true}));
        };
        source?.addEventListener('input', syncAddress);
        source?.addEventListener('change', syncAddress);
        const generate = () => {
            // Example addresses only: no external services or location collection.
            const number = max => window.crypto.getRandomValues(new Uint32Array(1))[0] % max + 1;
            input.value = `House ${number(250)}, Street ${number(600)}, Phnom Penh, Cambodia`;
            input.dispatchEvent(new Event('input', {bubbles: true}));
            input.dispatchEvent(new Event('change', {bubbles: true}));
        };
        mode.addEventListener('change', () => {
            if (previousMode === 'manual') manualAddress = input.value;
            const random = mode.value === 'random';
            button.hidden = note.hidden = !random;
            input.readOnly = mode.value === 'same';
            if (mode.value === 'same') {
                if (sourceMode?.value === 'same') {
                    sourceMode.value = 'manual';
                    sourceMode.dispatchEvent(new Event('change', {bubbles: true}));
                } else {
                    syncAddress();
                }
            }
            else if (random) generate();
            else { input.value = manualAddress; input.dispatchEvent(new Event('input', {bubbles: true})); }
            previousMode = mode.value;
        });
        button.addEventListener('click', generate);
    });
    const parent = document.getElementById('parent_id');
    parent?.addEventListener('change', () => {
        const option = parent.selectedOptions[0];
        const employee = option?.hasAttribute('data-employee');
        const addressMode = document.getElementById('guardian_address-mode');
        if (addressMode?.value === 'same' || (employee && addressMode && addressMode.value !== 'manual')) {
            addressMode.value = 'manual';
            addressMode.dispatchEvent(new Event('change', {bubbles: true}));
        }
        const fields = {guardian_gender: 'gender', guardian_address: 'address', guardian_phone: 'phone'};
        if (employee) fields.guardian_name = 'name';
        for (const [id, key] of Object.entries(fields)) {
            const input = document.getElementById(id);
            if (input && (employee || option?.dataset[key])) {
                input.value = option.dataset[key] || '';
                input.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
        const number = document.querySelector('[aria-label="Parent profile number"]');
        if (number) number.value = option?.dataset.profileNumber || '';
    });
})();
