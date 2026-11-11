(() => {
    const registry = JSON.parse(document.getElementById('scholar-theme-registry').textContent);
    const themes = Object.keys(registry);
    const root = document.documentElement;
    const key = 'scholar-theme';
    let timer;
    let selectedTheme = 'light';
    function apply(value, animate = false) {
        const theme = themes.includes(value) ? value : 'light';
        if (animate && root.dataset.theme !== theme && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            clearTimeout(timer);
            root.classList.add('theme-changing');
            timer = setTimeout(() => root.classList.remove('theme-changing'), 750);
        }
        root.dataset.theme = theme;
        root.dataset.bsTheme = registry[theme].dark ? 'dark' : 'light';
        document.querySelectorAll('[data-theme-picker]').forEach(picker => { picker.value = theme; });
        document.querySelectorAll('[data-current-theme]').forEach(label => {
            label.textContent = registry[theme].name;
        });
        document.querySelectorAll('[data-theme-choice]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.themeChoice === selectedTheme)));
    }
    let saved;
    try { saved = localStorage.getItem(key); if (localStorage.getItem('scholar-sidebar-collapsed') === 'true') root.dataset.sidebar = 'collapsed'; } catch (_) {}
    selectedTheme = themes.includes(saved) ? saved : 'light';
    apply(selectedTheme);
    document.addEventListener('DOMContentLoaded', () => {
        apply(root.dataset.theme);
        const choose = value => {
            selectedTheme = themes.includes(value) ? value : 'light';
            apply(selectedTheme, true);
            try { localStorage.setItem(key, selectedTheme); } catch (_) {}
        };
        document.querySelectorAll('[data-theme-picker]').forEach(picker => picker.addEventListener('change', () => choose(picker.value)));
        document.querySelectorAll('[data-theme-menu], [data-language-menu]').forEach(menu => {
            const summary = menu.querySelector('summary');
            const search = menu.querySelector('[data-theme-search]');
            search?.addEventListener('input', () => {
                const term = search.value.trim().toLocaleLowerCase();
                menu.querySelectorAll('[data-theme-choice]').forEach(button => {
                    button.hidden = !button.textContent.toLocaleLowerCase().includes(term);
                });
                menu.querySelectorAll('[data-theme-group]').forEach(group => {
                    group.hidden = !group.querySelector('[data-theme-choice]:not([hidden])');
                });
                menu.querySelector('[data-theme-empty]').hidden = !!menu.querySelector('[data-theme-choice]:not([hidden])');
                apply(selectedTheme, true);
            });
            let closeTimer;
            let keyboardNavigation = false;
            let openedByClick = false;
            summary.addEventListener('click', event => {
                event.preventDefault();
                clearTimeout(closeTimer);
                const opening = !menu.open || !openedByClick;
                document.querySelectorAll('[data-theme-menu], [data-language-menu]').forEach(other => { if (other !== menu) other.open = false; });
                openedByClick = opening;
                menu.open = opening;
            });
            menu.addEventListener('pointerenter', event => {
                if (root.dataset.hoverMenus === 'off') return;
                if (event.pointerType !== 'mouse' || !window.matchMedia('(hover: hover)').matches) return;
                keyboardNavigation = false;
                clearTimeout(closeTimer);
                document.querySelectorAll('[data-theme-menu], [data-language-menu]').forEach(other => { if (other !== menu) other.open = false; });
                menu.open = true;
            });
            menu.addEventListener('pointerleave', event => {
                if (openedByClick || root.dataset.hoverMenus === 'off') return;
                if (event.pointerType !== 'mouse') return;
                closeTimer = setTimeout(() => {
                    if (!keyboardNavigation || !menu.contains(document.activeElement)) menu.open = false;
                }, 180);
            });
            const restoreSelection = () => {
                if (menu.hasAttribute('data-theme-menu')) apply(selectedTheme, true);
            };
            menu.querySelectorAll('[data-theme-choice]').forEach(button => {
                button.addEventListener('pointerenter', event => {
                    if (event.pointerType === 'mouse' && window.matchMedia('(hover: hover)').matches) {
                        apply(button.dataset.themeChoice, true);
                    }
                });
                button.addEventListener('pointerleave', restoreSelection);
                button.addEventListener('focus', () => apply(button.dataset.themeChoice, true));
                button.addEventListener('blur', restoreSelection);
                button.addEventListener('click', () => {
                    choose(button.dataset.themeChoice);
                    menu.open = false;
                    summary.focus();
                });
            });
            menu.addEventListener('pointerleave', restoreSelection);
            menu.addEventListener('toggle', () => { if (!menu.open) { openedByClick = false; restoreSelection(); } });
            menu.addEventListener('keydown', event => {
                keyboardNavigation = true;
                clearTimeout(closeTimer);
                if (event.key === 'Escape') { menu.open = false; summary.focus(); }
                if (event.target === search && !['ArrowDown', 'ArrowUp'].includes(event.key)) return;
                if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                menu.open = true;
                const options = [...menu.querySelectorAll('[data-theme-choice]:not([hidden]), [data-language-choice]')];
                if (!options.length) return;
                let index = options.indexOf(document.activeElement);
                index = event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
                options[index].focus();
            });
            document.addEventListener('click', event => { if (!menu.contains(event.target)) menu.open = false; });
            document.addEventListener('focusin', event => { if (!menu.contains(event.target)) menu.open = false; });
        });
    });
    window.addEventListener('storage', event => {
        if (event.key === key) {
            selectedTheme = themes.includes(event.newValue) ? event.newValue : 'light';
            apply(selectedTheme, true);
        }
    });
})();
