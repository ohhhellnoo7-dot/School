(() => {
    const dashboard = document.querySelector('[data-classroom-dashboard]');
    const form = document.querySelector('[data-availability-filters]');
    const results = document.querySelector('[data-availability-results]');
    const status = document.querySelector('[data-refresh-status]');

    if (!dashboard || !form || !results) return;

    const endpoint = dashboard.dataset.endpoint;
    const interval = Number(dashboard.dataset.interval || 30000);
    let followNow = dashboard.dataset.followNow === '1';
    let requestController = null;
    let lessonFilter = 'all';
    const roomPrograms = new Map();
    const roomPreviewTimers = new Map();
    const clearRoomPreview = roomId => {
        clearTimeout(roomPreviewTimers.get(roomId));
        roomPreviewTimers.delete(roomId);
        roomPrograms.delete(roomId);
    };
    const selectRoomProgram = (card, program) => {
        const panels = [...card.querySelectorAll('[data-room-program-panel]')];
        const selected = panels.find(panel => panel.dataset.roomProgramPanel === program);
        if (!selected) return;
        panels.forEach(panel => { panel.hidden = panel !== selected; });
        card.querySelectorAll('[data-room-program-overview]').forEach(panel => {
            panel.hidden = panel.dataset.roomProgramOverview !== program;
        });
        card.querySelectorAll('[data-room-teacher-panel]').forEach(panel => {
            panel.hidden = panel.dataset.roomTeacherPanel !== program;
        });
        card.querySelectorAll('[data-room-program]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.roomProgram === program)));
        const count = card.querySelector('[data-room-enrolled]');
        if (count) count.textContent = selected.dataset.enrolledCount;
        const detailLink = card.querySelector('.room-card-footer a');
        if (detailLink) {
            const url = new URL(detailLink.href);
            url.searchParams.set('program', program);
            if (selected.dataset.programSession) url.searchParams.set('session', selected.dataset.programSession);
            else url.searchParams.delete('session');
            detailLink.href = url.toString();
        }
    };
    results.addEventListener('click', event => {
        const button = event.target.closest('[data-room-program]');
        if (!button) return;
        const card = button.closest('[data-room-card]');
        const roomId = card.dataset.roomCard;
        const program = button.dataset.roomProgram;
        clearRoomPreview(roomId);
        selectRoomProgram(card, program);
        if (program === card.dataset.activeProgram) return;
        const expiresAt = card.dataset.activeProgram ? Date.now() + 2000 : null;
        roomPrograms.set(roomId, { program, context: card.dataset.roomContext, expiresAt });
        if (expiresAt) {
            roomPreviewTimers.set(roomId, setTimeout(() => {
                const currentCard = [...results.querySelectorAll('[data-room-card]')].find(room => room.dataset.roomCard === roomId);
                if (currentCard?.dataset.activeProgram) selectRoomProgram(currentCard, currentCard.dataset.activeProgram);
                clearRoomPreview(roomId);
            }, 2000));
        }
    });

    const applyLessonFilter = () => {
        const cards = [...results.querySelectorAll('[data-lesson-state]')];
        results.querySelectorAll('[data-lesson-filter]').forEach(button => {
            const value = button.dataset.lessonFilter;
            button.setAttribute('aria-pressed', String(value === lessonFilter));
            button.querySelector('[data-lesson-count]').textContent = cards.filter(card => value === 'all' || card.dataset.lessonState === value).length;
        });
        cards.forEach(card => { card.hidden = lessonFilter !== 'all' && card.dataset.lessonState !== lessonFilter; });
        const empty = results.querySelector('[data-lesson-empty]');
        if (empty) empty.hidden = lessonFilter === 'all' || cards.some(card => !card.hidden);
        const selection = results.querySelector('[data-lesson-selection]');
        if (selection) selection.textContent = lessonFilter === 'all' ? '' : ` Showing: ${{studying: 'Studying', free: 'Free period', incomplete: 'Incomplete timetable'}[lessonFilter] || lessonFilter}.`;
    };
    const openLessonPicker = open => {
        results.querySelector('.lesson-picker')?.classList.toggle('is-open', open);
        results.querySelector('[data-lesson-toggle]')?.setAttribute('aria-expanded', String(open));
    };
    results.addEventListener('mouseover', event => {
        if (event.target.closest('.lesson-picker')) openLessonPicker(true);
    });
    results.addEventListener('mouseout', event => {
        const picker = event.target.closest('.lesson-picker');
        if (picker && !picker.contains(event.relatedTarget)) openLessonPicker(false);
    });
    results.addEventListener('focusin', event => {
        if (event.target.closest('.lesson-picker')) openLessonPicker(true);
    });
    results.addEventListener('focusout', event => {
        const picker = event.target.closest('.lesson-picker');
        if (picker && !picker.contains(event.relatedTarget)) openLessonPicker(false);
    });
    results.addEventListener('keydown', event => {
        if (event.key === 'Escape' && event.target.closest('.lesson-picker')) {
            results.querySelector('[data-lesson-toggle]').focus();
            openLessonPicker(false);
        }
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.lesson-picker')) openLessonPicker(false);
    });
    results.addEventListener('click', event => {
        if (event.target.closest('[data-lesson-toggle]')) openLessonPicker(true);
        const button = event.target.closest('[data-lesson-filter]');
        if (!button) return;
        lessonFilter = button.dataset.lessonFilter;
        applyLessonFilter();
        openLessonPicker(false);
    });
    applyLessonFilter();

    const localDateParts = () => {
        const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', {
            timeZone: 'Asia/Bangkok', year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
        }).formatToParts(new Date()).map(part => [part.type, part.value]));
        return {date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}`};
    };

    const useCurrentMoment = () => {
        if (!followNow) return;
        const current = localDateParts();
        const date = form.querySelector('[name="date"]');
        const time = form.querySelector('[name="time"]');
        if (date) date.value = current.date;
        if (time) time.value = current.time;
    };

    const params = () => {
        const query = new URLSearchParams();
        new FormData(form).forEach((value, key) => {
            if (String(value).trim() !== '') query.set(key, value);
        });
        if (followNow) query.set('live', '1');
        return query;
    };

    const refresh = async ({ updateHistory = false } = {}) => {
        useCurrentMoment();
        const query = params();
        const url = `${endpoint}?${query.toString()}`;

        requestController?.abort();
        requestController = new AbortController();
        results.setAttribute('aria-busy', 'true');
        if (status) status.textContent = 'Refreshing classroom availability...';

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
                signal: requestController.signal,
            });
            if (!response.ok) throw new Error(`Availability request failed (${response.status})`);

            const payload = await response.json();
            results.innerHTML = payload.html;
            results.querySelectorAll('[data-room-card]').forEach(card => {
                const selected = roomPrograms.get(card.dataset.roomCard);
                if (selected?.context === card.dataset.roomContext && (!selected.expiresAt || selected.expiresAt > Date.now())) selectRoomProgram(card, selected.program);
                else clearRoomPreview(card.dataset.roomCard);
            });
            applyLessonFilter();
            if (status) status.textContent = `Showing room status for ${payload.label}. Refreshed just now; refreshes every 30 seconds.`;
            if (updateHistory) {
                const browserUrl = `${form.action}?${query.toString()}`;
                window.history.replaceState({}, '', browserUrl);
            }
        } catch (error) {
            if (error.name !== 'AbortError' && status) {
                status.textContent = 'Classroom status could not be refreshed. The last successful results are still shown.';
            }
        } finally {
            results.removeAttribute('aria-busy');
        }
    };

    const programPicker = document.querySelector('.program-picker');
    const programToggle = programPicker?.querySelector('[data-program-toggle]');
    const openProgram = open => {
        programPicker?.classList.toggle('is-open', open);
        programToggle?.setAttribute('aria-expanded', String(open));
    };
    programToggle?.addEventListener('click', () => openProgram(!programPicker.classList.contains('is-open')));
    programPicker?.addEventListener('mouseenter', () => openProgram(true));
    programPicker?.addEventListener('mouseleave', () => { if (!programPicker.contains(document.activeElement)) openProgram(false); });
    programPicker?.addEventListener('focusin', () => openProgram(true));
    programPicker?.addEventListener('focusout', () => setTimeout(() => { if (!programPicker.contains(document.activeElement)) openProgram(false); }, 0));
    programPicker?.addEventListener('keydown', event => {
        if (event.key === 'Escape') { programToggle.focus(); openProgram(false); }
    });
    document.addEventListener('click', event => { if (!programPicker?.contains(event.target)) openProgram(false); });
    document.querySelectorAll('[data-program]').forEach(button => {
        button.addEventListener('click', () => {
            form.elements.program.value = button.dataset.program;
            document.querySelector('[data-program-label]').textContent = button.dataset.program || 'Program';
            button.blur();
            openProgram(false);
            refresh({ updateHistory: true });
        });
    });
    form.elements.program?.addEventListener('change', () => {
        const label = document.querySelector('[data-program-label]');
        if (label) label.textContent = form.elements.program.value || 'Program';
    });

    const picker = document.querySelector('.building-picker');
    const toggle = picker?.querySelector('[data-building-toggle]');
    const setOpen = open => {
        picker?.classList.toggle('is-open', open);
        toggle?.setAttribute('aria-expanded', String(open));
    };
    toggle?.addEventListener('click', () => setOpen(!picker.classList.contains('is-open')));
    picker?.addEventListener('mouseenter', () => toggle.setAttribute('aria-expanded', 'true'));
    picker?.addEventListener('mouseleave', () => { if (!picker.contains(document.activeElement)) setOpen(false); });
    picker?.addEventListener('keydown', event => {
        if (event.key === 'Escape') { toggle.focus(); setOpen(false); }
    });
    document.addEventListener('click', event => { if (!picker?.contains(event.target)) setOpen(false); });
    document.querySelectorAll('[data-building]').forEach(button => {
        button.addEventListener('click', () => {
            form.elements.building.value = button.dataset.building;
            document.querySelector('[data-building-label]').textContent = button.dataset.building ? button.dataset.buildingName : 'Building';
            button.blur();
            setOpen(false);
            refresh({ updateHistory: true });
        });
    });

    form.addEventListener('submit', event => {
        event.preventDefault();
        followNow = false;
        refresh({ updateHistory: true });
    });
    form.querySelectorAll('[name="date"], [name="time"]').forEach(input => {
        input.addEventListener('input', () => { followNow = false; });
    });
    document.querySelector('[data-availability-refresh]')?.addEventListener('click', () => refresh());

    window.setInterval(() => {
        if (!document.hidden) refresh();
    }, Math.max(interval, 10000));
})();
