(() => {
    const family = document.querySelector('#family-excuse-dialog');
    if (family) {
        const form = family.querySelector('[data-excuse-form]');
        const chosenChild = form.elements.child_id;
        const session = form.elements.session;
        const placeholder = session.options[0];
        const sessionsByChild = new Map(
            Array.from(family.querySelectorAll('[data-excuse-sessions]'), template => [template.dataset.excuseSessions, template.content]),
        );
        const choose = (id, previousSession = '') => {
            chosenChild.value = id;
            form.hidden = false;
            family.querySelectorAll('[data-excuse-child]').forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.excuseChild === id));
                button.classList.toggle('active', button.dataset.excuseChild === id);
            });
            session.replaceChildren(placeholder);
            const options = sessionsByChild.get(id);
            if (options) session.append(options.cloneNode(true));
            session.value = session.options.length === 2 ? session.options[1].value : previousSession;
            if (session.selectedIndex === -1) session.selectedIndex = 0;
        };
        document.querySelectorAll('[data-open-excuse]').forEach(button => {
            button.addEventListener('click', () => family.showModal());
        });
        family.querySelectorAll('[data-excuse-child]').forEach(button => {
            button.addEventListener('click', () => {
                choose(button.dataset.excuseChild);
                session.focus();
            });
        });
        if (family.hasAttribute('data-reopen')) {
            choose(chosenChild.value, session.dataset.selected);
            family.showModal();
        }
    }

    const countUrl = document.querySelector('[data-count-url]')?.dataset.countUrl;
    if (countUrl) {
        const formatter = new Intl.NumberFormat(document.documentElement.lang || undefined);
        let count = 0;
        let updating = false;
        const renderCount = () => {
            document.querySelectorAll('[data-excuse-count]').forEach(badge => {
                badge.textContent = count > 0 ? formatter.format(count) : '';
                badge.hidden = count === 0;
            });
        };
        const refreshCount = async () => {
            if (document.hidden || updating) return;
            updating = true;
            try {
                const response = await fetch(countUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) return;
                const next = await response.json();
                if (next.count !== count) {
                    count = next.count;
                    renderCount();
                    document.dispatchEvent(new Event('student-excuse-count-changed'));
                }
            } catch {
                // Keep the last count visible until the next refresh succeeds.
            } finally {
                updating = false;
            }
        };
        document.addEventListener('student-attendance-summary-replaced', renderCount);
        document.addEventListener('student-excuse-notifications-seen', refreshCount);
        document.addEventListener('visibilitychange', refreshCount);
        setInterval(refreshCount, 1000);
        refreshCount();
    }

    const report = document.querySelector('#student-excuse-report');
    if (!report) return;
    const date = report.querySelector('input[type="date"]');
    const results = report.querySelector('[data-excuse-results]');
    let pending;
    let timer;
    let displayed;
    let acknowledge = false;

    const refresh = async (showLoading = false) => {
        clearTimeout(timer);
        pending?.abort();
        if (!date.checkValidity()) {
            results.replaceChildren();
            displayed = undefined;
            return;
        }
        const request = new AbortController();
        pending = request;
        const url = new URL(report.dataset.reportUrl, location.href);
        url.searchParams.set('date', date.value);
        if (showLoading) {
            displayed = undefined;
            results.textContent = report.dataset.loading;
        }
        try {
            const response = await fetch(url, {
                cache: 'no-store',
                headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                signal: request.signal,
            });
            if (!response.ok || response.redirected) throw new Error('Report unavailable');
            const html = await response.text();
            if (!request.signal.aborted && html !== displayed) {
                displayed = html;
                results.innerHTML = html;
            }
            if (!request.signal.aborted && acknowledge) {
                acknowledge = false;
                const through = response.headers.get('X-Excuse-Notifications-Through');
                if (through && through !== '0') {
                    const seen = await fetch(report.dataset.seenUrl, {
                        method: 'POST',
                        keepalive: true,
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ through }),
                    }).catch(() => null);
                    if (seen?.ok) {
                        document.dispatchEvent(new Event('student-excuse-notifications-seen'));
                    }
                }
            }
        } catch (error) {
            if (!request.signal.aborted) {
                displayed = undefined;
                results.textContent = report.dataset.error;
            }
        } finally {
            if (!request.signal.aborted && report.open) timer = setTimeout(refresh, 5000);
        }
    };

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-open-excuse-report]');
        if (!button) return;
        date.value = button.dataset.openExcuseReport;
        acknowledge = true;
        report.showModal();
        refresh(true);
    });
    date.addEventListener('change', () => refresh(true));
    document.addEventListener('student-excuse-count-changed', () => {
        if (report.open) refresh();
    });
    report.addEventListener('close', () => {
        clearTimeout(timer);
        pending?.abort();
    });
})();
