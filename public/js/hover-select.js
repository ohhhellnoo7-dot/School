(() => {
    // Enhance mouse hover while retaining native select behavior for touch and keys.
    const panel = document.createElement('div');
    panel.className = 'hover-select-menu';
    panel.setAttribute('role', 'listbox');
    panel.hidden = true;
    document.body.append(panel);
    let activeSelect;
    let closeTimer;

    function close() {
        clearTimeout(closeTimer);
        panel.hidden = true;
        activeSelect = null;
    }

    function position() {
        if (!activeSelect?.isConnected || activeSelect.disabled) return close();
        const rect = activeSelect.getBoundingClientRect();
        if (rect.bottom < 0 || rect.top > innerHeight) return close();
        const width = Math.min(Math.max(rect.width, 180), innerWidth - 24);
        const below = innerHeight - rect.bottom - 16;
        const above = rect.top - 16;
        const upwards = below < 180 && above > below;
        panel.style.width = `${width}px`;
        panel.style.maxHeight = `${Math.max(0, Math.min(320, upwards ? above : below))}px`;
        panel.style.left = `${Math.max(12, Math.min(rect.left, innerWidth - width - 12))}px`;
        panel.style.top = upwards ? 'auto' : `${rect.bottom + 4}px`;
        panel.style.bottom = upwards ? `${innerHeight - rect.top + 4}px` : 'auto';
    }

    function open(select) {
        clearTimeout(closeTimer);
        if (select === activeSelect && !panel.hidden) return;
        activeSelect = select;
        panel.replaceChildren();
        const label = select.getAttribute('aria-label') || [...(select.labels || [])]
            .map(item => item.childNodes.length ? [...item.childNodes].filter(node => node.nodeType === Node.TEXT_NODE).map(node => node.textContent).join(' ').trim() : '')
            .filter(Boolean).join(' ') || select.name || 'Options';
        panel.setAttribute('aria-label', label);
        let group;
        [...select.options].forEach((option, index) => {
            const parent = option.parentElement;
            if (option.hidden || parent.hidden || option.style.display === 'none') return;
            if (parent.tagName === 'OPTGROUP' && parent !== group) {
                group = parent;
                const heading = document.createElement('div');
                heading.className = 'hover-select-group';
                heading.textContent = parent.label;
                panel.append(heading);
            }
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'hover-select-option';
            item.dataset.optionIndex = String(index);
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(option.selected));
            item.disabled = option.disabled || (parent.tagName === 'OPTGROUP' && parent.disabled);
            // Keep native keyboard focus on the original form control.
            item.tabIndex = -1;
            const text = document.createElement('span');
            text.textContent = option.label;
            const check = document.createElement('i');
            check.className = 'bi bi-check2';
            check.setAttribute('aria-hidden', 'true');
            item.append(text, check);
            panel.append(item);
        });
        if (!panel.querySelector('button')) return close();
        panel.hidden = false;
        position();
        const selected = panel.querySelector('[aria-selected="true"]');
        if (selected) panel.scrollTop = Math.max(0, selected.offsetTop - panel.clientHeight / 2);
    }

    document.addEventListener('pointerover', event => {
        if (event.pointerType !== 'mouse' || !matchMedia('(hover: hover)').matches) return;
        if (panel.contains(event.target)) return clearTimeout(closeTimer);
        const select = event.target.closest?.('select');
        if (select && !select.disabled && !select.multiple && select.size <= 1) open(select);
    });
    document.addEventListener('pointerout', event => {
        if (!activeSelect || event.pointerType !== 'mouse') return;
        const next = event.relatedTarget;
        if (next instanceof Node && (panel.contains(next) || activeSelect.contains(next))) return;
        clearTimeout(closeTimer);
        closeTimer = setTimeout(close, 220);
    });
    panel.addEventListener('pointerdown', event => event.preventDefault());
    panel.addEventListener('click', event => {
        const item = event.target.closest('[data-option-index]');
        if (!item || item.disabled || !activeSelect?.isConnected || activeSelect.disabled) return;
        const select = activeSelect;
        const index = Number(item.dataset.optionIndex);
        const changed = select.selectedIndex !== index;
        select.selectedIndex = index;
        close();
        select.focus({preventScroll: true});
        if (changed) {
            select.dispatchEvent(new Event('input', {bubbles: true}));
            select.dispatchEvent(new Event('change', {bubbles: true}));
        }
    });
    document.addEventListener('pointerdown', event => {
        if (!panel.contains(event.target)) close();
    });
    document.addEventListener('keydown', close);
    document.addEventListener('focusin', event => {
        if (event.target !== activeSelect) close();
    });
    document.addEventListener('scroll', event => {
        if (!panel.contains(event.target)) close();
    }, true);
    document.addEventListener('change', close);
    window.addEventListener('resize', close);
    window.addEventListener('blur', close);
})();
