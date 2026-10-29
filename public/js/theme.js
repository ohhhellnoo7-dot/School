(() => {
    const themes = ['light', 'night', 'relax', 'cute', 'ocean', 'sunset', 'luxury', 'galaxy'];
    const key = 'scholar-theme';
    let motionTimer;
    function apply(value, animate = false) {
        const theme = themes.includes(value) ? value : 'light';
        if (animate && document.documentElement.dataset.theme !== theme) {
            clearTimeout(motionTimer);
            document.documentElement.classList.remove('theme-changing');
            // Restart the short entrance sequence when choosing another theme.
            void document.documentElement.offsetWidth;
            document.documentElement.classList.add('theme-changing');
            motionTimer = setTimeout(() => document.documentElement.classList.remove('theme-changing'), 1000);
        }
        document.documentElement.dataset.theme = theme;
        document.documentElement.dataset.bsTheme = ['night', 'luxury', 'galaxy'].includes(theme) ? 'dark' : 'light';
        document.querySelectorAll('[data-theme-picker]').forEach(picker => { picker.value = theme; });
    }
    let saved;
    try { saved = localStorage.getItem(key); } catch (_) { /* Storage may be disabled. */ }
    apply(saved);
    document.addEventListener('DOMContentLoaded', () => {
        apply(document.documentElement.dataset.theme);
        document.documentElement.classList.add('theme-entering');
        setTimeout(() => document.documentElement.classList.remove('theme-entering'), 1000);
        document.querySelectorAll('[data-theme-picker]').forEach(picker => {
            picker.addEventListener('change', () => {
                apply(picker.value, true);
                try { localStorage.setItem(key, picker.value); } catch (_) { /* Keep the current page usable. */ }
            });
        });
    });
    window.addEventListener('storage', event => { if (event.key === key) apply(event.newValue, true); });
})();
