(() => {
    const tables = [...document.querySelectorAll('[data-attendance-grid]')];
    const headers = tables.map((table) => {
        const viewport = table.closest('.table-responsive');
        const strip = document.createElement('div');
        strip.className = 'attendance-sticky-dates';
        // Header synchronization must be immediate even if the theme enables
        // smooth scrolling. Coalesce trackpad events into one write per frame.
        strip.style.scrollBehavior = 'auto';
        strip.setAttribute('aria-hidden', 'true');
        const copy = document.createElement('table');
        copy.className = table.className;
        copy.append(table.tHead.cloneNode(true));
        strip.append(copy);
        viewport.before(strip);
        table.classList.add('attendance-header-copied');
        let scrollFrame = 0;
        let scrollEndTimer = 0;
        let lastScrollLeft = viewport.scrollLeft;
        const finishScroll = () => {
            table.classList.remove('attendance-is-scrolling');
        };
        viewport.addEventListener('scroll', () => {
            const left = viewport.scrollLeft;
            if (left === lastScrollLeft) return;
            lastScrollLeft = left;
            if (!scrollEndTimer) table.classList.add('attendance-is-scrolling');
            clearTimeout(scrollEndTimer);
            scrollEndTimer = setTimeout(() => {
                scrollEndTimer = 0;
                finishScroll();
            }, 120);
            if (scrollFrame) return;
            scrollFrame = requestAnimationFrame(() => {
                scrollFrame = 0;
                strip.scrollLeft = viewport.scrollLeft;
            });
        }, { passive: true });
        return { table, strip, copy, viewport };
    });
    const sizeHeaders = () => {
        const top = Math.max(0, document.querySelector('.topbar')?.getBoundingClientRect().bottom || 0);
        headers.forEach(({ table, strip, copy, viewport }) => {
            const cells = [...table.tHead.rows[0].cells];
            const widths = cells.map((cell) => cell.getBoundingClientRect().width);
            copy.style.width = `${table.getBoundingClientRect().width}px`;
            [...copy.tHead.rows[0].cells].forEach((cell, index) => {
                cell.style.width = `${widths[index]}px`;
                cell.style.minWidth = `${widths[index]}px`;
                cell.style.maxWidth = `${widths[index]}px`;
            });
            // Ocean's fixed sky hides content above the waterline. Keep the
            // date header below its foreground wave; other themes use topbar.
            strip.style.top = `max(${top}px, calc(var(--ocean-surface-y, 0px) + 16px))`;
            strip.style.marginBottom = `-${table.tHead.offsetHeight}px`;
            strip.scrollLeft = viewport.scrollLeft;
        });
    };
    window.addEventListener('resize', sizeHeaders, { passive: true });
    window.addEventListener('load', sizeHeaders, { once: true });
    const sidebar = document.querySelector('#sidebar');
    if (sidebar && typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(sizeHeaders).observe(sidebar);
    }
    sizeHeaders();

})();
