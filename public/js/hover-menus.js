(() => {
    const root = document.documentElement;
    try { root.dataset.hoverMenus = localStorage.getItem('scholar-hover-menus') || 'on'; } catch { root.dataset.hoverMenus = 'on'; }
    // Capture hover events before individual menu handlers run. Clicks and keyboard input stay available.
    ['pointerover', 'mouseover', 'mouseenter', 'pointerenter'].forEach(type => {
        document.addEventListener(type, event => {
            if (root.dataset.hoverMenus === 'off') event.stopImmediatePropagation();
        }, true);
    });
    document.addEventListener('DOMContentLoaded', () => {
        const button = document.getElementById('hover-menus-toggle');
        const update = () => {
            const enabled = root.dataset.hoverMenus !== 'off';
            button?.setAttribute('aria-pressed', String(enabled));
            if (button) button.querySelector('span').textContent = 'Hover menus: ' + (enabled ? 'On' : 'Off');
        };
        update();
        button?.addEventListener('click', () => {
            root.dataset.hoverMenus = root.dataset.hoverMenus === 'off' ? 'on' : 'off';
            try { localStorage.setItem('scholar-hover-menus', root.dataset.hoverMenus); } catch {}
            document.querySelectorAll('details[open]').forEach(menu => menu.open = false);
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                const trigger = menu.parentElement.querySelector('[data-bs-toggle="dropdown"]');
                if (trigger && window.bootstrap) bootstrap.Dropdown.getOrCreateInstance(trigger).hide();
            });
            document.querySelectorAll('.stat-breakdown.is-open').forEach(menu => menu.classList.remove('is-open'));
            document.dispatchEvent(new Event('hover-menus-change'));
            update();
        });
        document.addEventListener('click', event => {
            const trigger = event.target.closest('.stat-breakdown-trigger');
            document.querySelectorAll('.stat-breakdown.is-open').forEach(menu => {
                if (!menu.contains(event.target)) menu.classList.remove('is-open');
            });
            if (trigger) {
                const open = trigger.closest('.stat-breakdown').classList.toggle('is-open');
                trigger.setAttribute('aria-expanded', String(open));
            }
        });
    });
})();
