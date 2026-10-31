(() => {
    // Hover opens information disclosures, including content added by live refresh.
    // Native selects, action buttons, tabs and confirmation dialogs keep their behavior.
    const hoverMenus = new WeakMap();
    const openInformation = new Set();
    let keyboardNavigation = false;
    const hoverTarget = target => target instanceof Element
        ? target.closest('details:not([data-theme-menu]):not([data-language-menu]), .dropdown') : null;
    const closeInformation = menu => {
        if (menu.matches('details')) {
            if (openInformation.has(menu)) menu.open = false;
        } else {
            const trigger = menu.querySelector('[data-bs-toggle="dropdown"]');
            if (trigger && window.bootstrap) bootstrap.Dropdown.getOrCreateInstance(trigger).hide();
        }
        openInformation.delete(menu);
    };
    document.addEventListener('pointerover', event => {
        if (event.pointerType !== 'mouse' || !matchMedia('(hover: hover)').matches) return;
        keyboardNavigation = false;
        const menu = hoverTarget(event.target);
        for (const other of openInformation) {
            if (other !== menu && !other.contains(event.target)) {
                clearTimeout(hoverMenus.get(other));
                closeInformation(other);
            }
        }
        if (!menu || (event.relatedTarget instanceof Node && menu.contains(event.relatedTarget))) return;
        clearTimeout(hoverMenus.get(menu));
        if (menu.matches('details')) {
            if (!menu.open) {
                menu.open = true;
                openInformation.add(menu);
            }
        } else {
            const trigger = menu.querySelector('[data-bs-toggle="dropdown"]');
            if (trigger && window.bootstrap) {
                bootstrap.Dropdown.getOrCreateInstance(trigger).show();
                openInformation.add(menu);
            }
        }
    });
    document.addEventListener('pointerout', event => {
        if (event.pointerType !== 'mouse') return;
        const menu = hoverTarget(event.target);
        if (!menu || (event.relatedTarget instanceof Node && menu.contains(event.relatedTarget))) return;
        clearTimeout(hoverMenus.get(menu));
        hoverMenus.set(menu, setTimeout(() => {
            if (!keyboardNavigation || !menu.contains(document.activeElement)) closeInformation(menu);
        }, 220));
    });
    document.addEventListener('focusout', event => {
        const menu = hoverTarget(event.target);
        if (!menu) return;
        setTimeout(() => {
            if (!menu.matches(':hover') && !menu.contains(document.activeElement)) closeInformation(menu);
        }, 0);
    });
    document.addEventListener('keydown', event => {
        keyboardNavigation = true;
        for (const menu of openInformation) clearTimeout(hoverMenus.get(menu));
        if (event.key !== 'Escape') return;
        for (const menu of openInformation) {
            closeInformation(menu);
            if (menu.contains(document.activeElement)) menu.querySelector('summary, [data-bs-toggle="dropdown"]')?.focus();
        }
    });
    document.addEventListener('click', event => {
        for (const menu of openInformation) {
            if (!menu.isConnected || !menu.contains(event.target)) closeInformation(menu);
        }
    });
    // A single confirmation dialog retains the original form, method, CSRF and submitter.
    const confirmation = document.getElementById('scholar-confirm-dialog');
    const approved = new WeakSet();
    let pendingForm;
    let pendingSubmitter;
    document.addEventListener('submit', event => {
        const form = event.target.closest('form[data-confirm]');
        if (!form) return;
        if (approved.has(form)) { approved.delete(form); return; }
        if (!confirmation || !window.bootstrap) {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
            return;
        }
        event.preventDefault();
        pendingForm = form;
        pendingSubmitter = event.submitter;
        confirmation.querySelector('#scholar-confirm-message').textContent = form.dataset.confirm;
        bootstrap.Modal.getOrCreateInstance(confirmation).show();
    });
    confirmation?.querySelector('[data-confirm-accept]').addEventListener('click', () => {
        if (!pendingForm?.isConnected) return;
        const form = pendingForm;
        const submitter = pendingSubmitter;
        approved.add(form);
        bootstrap.Modal.getOrCreateInstance(confirmation).hide();
        form.requestSubmit(submitter || undefined);
    });
    confirmation?.addEventListener('hidden.bs.modal', () => {
        pendingSubmitter?.focus();
        pendingForm = null;
        pendingSubmitter = null;
    });
    const root = document.documentElement;
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const syncSidebar = () => {
        if (!toggle) return;
        const collapsed = root.dataset.sidebar === 'collapsed';
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.setAttribute('aria-label', collapsed ? 'Expand navigation' : 'Collapse navigation');
        toggle.title = collapsed ? 'Expand navigation' : 'Collapse navigation';
    };
    syncSidebar();
    toggle?.addEventListener('click', () => {
        const collapsed = root.dataset.sidebar !== 'collapsed';
        root.dataset.sidebar = collapsed ? 'collapsed' : 'expanded';
        try { localStorage.setItem('scholar-sidebar-collapsed', String(collapsed)); } catch (_) {}
        syncSidebar();
    });
    document.querySelectorAll('.school-nav a').forEach(link => {
        const label = link.textContent.trim();
        link.setAttribute('aria-label', label);
        link.title = label;
    });
    // Keep navigation context while the document itself scrolls independently.
    const navigation = document.querySelector('.school-nav');
    if (navigation) {
        const key = 'scholar-navigation-scroll';
        try { navigation.scrollTop = Number(sessionStorage.getItem(key)) || 0; } catch (_) {}
        const active = navigation.querySelector('a.active');
        if (active) {
            const bounds = navigation.getBoundingClientRect();
            const item = active.getBoundingClientRect();
            if (item.top < bounds.top || item.bottom > bounds.bottom) {
                navigation.scrollTop += item.top - bounds.top - navigation.clientHeight / 2 + item.height / 2;
            }
            active.setAttribute('aria-current', 'page');
        }
        const save = () => { try { sessionStorage.setItem(key, String(navigation.scrollTop)); } catch (_) {} };
        navigation.addEventListener('scroll', save, {passive: true});
        window.addEventListener('pagehide', save);
    }
    // Missing photos retain a useful identity without a broken image icon.
    document.querySelectorAll('img.student-avatar[src], img.user-avatar[src]:not(#profile-preview)').forEach(photo => {
        const fallback = () => {
            const initials = document.createElement('span');
            initials.className = photo.className;
            initials.style.cssText = photo.style.cssText;
            const name = photo.classList.contains('user-avatar')
                ? document.querySelector('.user-info strong')?.textContent?.trim() || photo.alt
                : photo.alt;
            initials.textContent = Array.from(name || '?')[0].toUpperCase();
            initials.setAttribute('aria-label', name || 'Profile');
            photo.replaceWith(initials);
        };
        photo.addEventListener('error', fallback, {once: true});
        if (photo.complete && photo.naturalWidth === 0) fallback();
    });
    document.querySelectorAll('.table .btn[aria-label]').forEach(button => {
        if (!button.title) button.title = button.getAttribute('aria-label');
    });
})();
