(() => {
    const tables = [...document.querySelectorAll('[data-attendance-grid]')];
    const headers = tables.map((table) => {
        const viewport = table.closest('.table-responsive');
        const strip = document.createElement('div');
        strip.className = 'attendance-sticky-dates';
        strip.style.scrollBehavior = 'auto';
        strip.setAttribute('aria-hidden', 'true');
        const copy = document.createElement('table');
        copy.className = table.className;
        copy.style.display = 'block';
        copy.style.boxSizing = 'border-box';
        copy.append(table.tHead.cloneNode(true));
        copy.tHead.style.display = 'block';
        const copyRow = copy.tHead.rows[0];
        copyRow.style.display = 'grid';
        for (const cell of copyRow.cells) {
            cell.style.setProperty('width', 'auto', 'important');
            cell.style.setProperty('min-width', '0', 'important');
            cell.style.setProperty('max-width', 'none', 'important');
            cell.style.setProperty('height', '100%', 'important');
            cell.style.display = 'grid';
            cell.style.alignContent = 'center';
        }
        strip.append(copy);
        viewport.before(strip);
        table.classList.add('attendance-header-copied');
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
            strip.scrollLeft = left;
        }, { passive: true });
        return { table, strip, copy, viewport };
    });
    const sizeHeaders = () => {
        const top = Math.max(0, document.querySelector('.topbar')?.getBoundingClientRect().bottom || 0);
        headers.forEach(({ table, strip, copy, viewport }) => {
            const cells = [...table.tHead.rows[0].cells];
            const widths = cells.map((cell) => cell.getBoundingClientRect().width);
            copy.style.width = `${table.getBoundingClientRect().width}px`;
            strip.style.width = `${viewport.clientWidth}px`;
            const row = copy.tHead.rows[0];
            row.style.gridTemplateColumns = widths.map(width => `${width}px`).join(' ');
            row.style.height = `${table.tHead.offsetHeight}px`;
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
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(sizeHeaders);
        headers.forEach(({table, viewport}) => {
            observer.observe(table);
            observer.observe(viewport);
        });
    }
    sizeHeaders();

})();
