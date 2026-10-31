(() => {
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (!('IntersectionObserver' in window) || reducedMotion.matches) return;

    const selectors = '.page-heading, .stat-card, .panel, .mini-stats, .attendance-toolbar, .auth-box, .class-summary-card, .teacher-stat, .parent-stat, .timetable-calendar';
    // Animate only outer cards so nested panels and their controls do not move twice.
    const elements = Array.from(document.querySelectorAll(selectors))
        .filter(element => !element.parentElement?.closest(selectors));
    let observer;
    const revealAll = () => {
        observer?.disconnect();
        elements.forEach(element => {
            element.classList.remove('motion-pending', 'motion-reveal');
            element.style.removeProperty('--motion-delay');
        });
    };
    try {
        observer = new IntersectionObserver(entries => {
            const visible = entries.filter(entry => entry.isIntersecting);
            visible.forEach((entry, index) => {
                const element = entry.target;
                // Cap the stagger so large directories remain responsive.
                element.style.setProperty('--motion-delay', `${Math.min(index, 3) * 65}ms`);
                element.classList.remove('motion-pending');
                element.classList.add('motion-reveal');
                observer.unobserve(element);
                element.addEventListener('animationend', () => {
                    element.classList.remove('motion-reveal');
                    element.style.removeProperty('--motion-delay');
                }, { once: true });
            });
        }, { threshold: 0, rootMargin: '0px 0px 24px 0px' });
        elements.forEach(element => {
            // Never hide an active input or its containing panel.
            if (element.contains(document.activeElement)) return;
            element.classList.add('motion-pending');
            observer.observe(element);
        });
        // A keyboard user can reach a card before it intersects the viewport.
        document.addEventListener('focusin', event => {
            const pending = event.target.closest('.motion-pending');
            if (!pending) return;
            pending.classList.remove('motion-pending');
            observer.unobserve(pending);
        });
        reducedMotion.addEventListener('change', event => { if (event.matches) revealAll(); });
        window.addEventListener('pageshow', event => { if (event.persisted) revealAll(); });
    } catch (_) {
        revealAll();
    }
})();
