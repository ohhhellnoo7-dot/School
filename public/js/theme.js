(() => {
    const themes = ['light', 'night', 'relax', 'cute', 'ocean', 'sunset', 'luxury', 'galaxy'];
    const root = document.documentElement;
    const key = 'scholar-theme';
    let timer;
    function apply(value, animate = false) {
        const theme = themes.includes(value) ? value : 'light';
        if (animate && root.dataset.theme !== theme && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            clearTimeout(timer);
            root.classList.add('theme-changing');
            timer = setTimeout(() => root.classList.remove('theme-changing'), 200);
        }
        root.dataset.theme = theme;
        root.dataset.bsTheme = ['night', 'luxury', 'galaxy'].includes(theme) ? 'dark' : 'light';
        document.querySelectorAll('[data-theme-picker]').forEach(picker => { picker.value = theme; });
        document.querySelectorAll('[data-current-theme]').forEach(label => {
            const option = document.querySelector(`[data-theme-choice="${theme}"] strong`);
            if (option) label.textContent = option.textContent;
        });
        document.querySelectorAll('[data-theme-choice]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.themeChoice === theme)));
    }
    let saved;
    try { saved = localStorage.getItem(key); if (localStorage.getItem('scholar-sidebar-collapsed') === 'true') root.dataset.sidebar = 'collapsed'; } catch (_) {}
    apply(saved);
    document.addEventListener('DOMContentLoaded', () => {
        apply(root.dataset.theme);
        const choose = value => {
            apply(value, true);
            try { localStorage.setItem(key, root.dataset.theme); } catch (_) {}
        };
        document.querySelectorAll('[data-theme-picker]').forEach(picker => picker.addEventListener('change', () => choose(picker.value)));
        document.querySelectorAll('[data-theme-menu], [data-language-menu]').forEach(menu => {
            const summary = menu.querySelector('summary');
            let closeTimer;
            menu.addEventListener('pointerenter', event => {
                if (event.pointerType !== 'mouse' || !window.matchMedia('(hover: hover)').matches) return;
                clearTimeout(closeTimer);
                document.querySelectorAll('[data-theme-menu], [data-language-menu]').forEach(other => { if (other !== menu) other.open = false; });
                menu.open = true;
            });
            menu.addEventListener('pointerleave', event => {
                if (event.pointerType !== 'mouse') return;
                closeTimer = setTimeout(() => {
                    if (document.activeElement === summary || !menu.contains(document.activeElement)) menu.open = false;
                }, 180);
            });
            menu.querySelectorAll('[data-theme-choice]').forEach(button => button.addEventListener('click', () => {
                choose(button.dataset.themeChoice);
                menu.open = false;
                summary.focus();
            }));
            menu.addEventListener('keydown', event => {
                if (event.key === 'Escape') { menu.open = false; summary.focus(); }
                if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                menu.open = true;
                const options = [...menu.querySelectorAll('[data-theme-choice], [data-language-choice]')];
                let index = options.indexOf(document.activeElement);
                index = event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                options[index].focus();
            });
            document.addEventListener('click', event => { if (!menu.contains(event.target)) menu.open = false; });
            document.addEventListener('focusin', event => { if (!menu.contains(event.target)) menu.open = false; });
        });
    });
    window.addEventListener('storage', event => { if (event.key === key) apply(event.newValue, true); });
})();
