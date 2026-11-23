(() => {
    const rotation = document.querySelector('[data-family-rotation]');
    const controls = document.querySelector('[data-family-rotation-controls]');
    if (!rotation || !controls) return;

    const children = [...rotation.querySelectorAll('[data-family-child]')];
    const selectors = [...controls.querySelectorAll('[data-family-child-select]')];
    const showAll = controls.querySelector('[data-family-show-all]');
    const toggle = controls.querySelector('[data-family-rotation-toggle]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let selected = 0;
    let paused = reducedMotion.matches;
    let timer;

    function showChild(index) {
        selected = index;
        rotation.setAttribute('data-family-rotating', '');
        showAll.setAttribute('aria-pressed', 'false');
        children.forEach((child, position) => {
            child.hidden = position !== selected;
            selectors[position].setAttribute('aria-pressed', String(position === selected));
        });
    }

    function scheduleNext() {
        window.clearTimeout(timer);
        if (paused || document.hidden || rotation.matches(':hover, :focus-within')) return;
        timer = window.setTimeout(() => {
            showChild((selected + 1) % children.length);
            scheduleNext();
        }, 3000);
    }

    function setPaused(value) {
        paused = value;
        toggle.textContent = paused ? toggle.dataset.resumeLabel : toggle.dataset.pauseLabel;
        scheduleNext();
    }

    selectors.forEach((button, index) => button.addEventListener('click', () => {
        showChild(index);
        setPaused(true);
    }));
    showAll.addEventListener('click', () => {
        setPaused(true);
        rotation.removeAttribute('data-family-rotating');
        showAll.setAttribute('aria-pressed', 'true');
        children.forEach(child => { child.hidden = false; });
        selectors.forEach(button => button.setAttribute('aria-pressed', 'false'));
    });
    toggle.addEventListener('click', () => {
        if (paused) showChild(selected);
        setPaused(!paused);
    });
    rotation.addEventListener('pointerenter', scheduleNext);
    rotation.addEventListener('pointerleave', scheduleNext);
    rotation.addEventListener('focusin', scheduleNext);
    rotation.addEventListener('focusout', () => window.queueMicrotask(scheduleNext));
    document.addEventListener('visibilitychange', scheduleNext);
    reducedMotion.addEventListener('change', event => {
        if (event.matches) setPaused(true);
    });

    controls.hidden = false;
    showChild(0);
    setPaused(paused);
})();

(() => {
    const skeleton = document.querySelector('[data-family-skeleton]');
    const content = document.querySelector('[data-family-content]');
    if (!skeleton || !content) return;

    let pending;
    const showLoading = () => {
        window.clearTimeout(pending);
        pending = window.setTimeout(() => {
            skeleton.hidden = false;
            content.setAttribute('aria-busy', 'true');
            content.hidden = true;
        }, 220);
    };
    document.addEventListener('click', event => {
        const link = event.target.closest('a[data-family-navigation]');
        if (link && !event.defaultPrevented && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.button === 0) {
            showLoading();
        }
    });
    document.addEventListener('submit', event => {
        if (!event.defaultPrevented && event.target.matches('[data-family-navigation]')) showLoading();
    });
    window.addEventListener('pageshow', () => {
        window.clearTimeout(pending);
        skeleton.hidden = true;
        content.hidden = false;
        content.removeAttribute('aria-busy');
    });
})();
