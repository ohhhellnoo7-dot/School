(() => {
    const configElement = document.querySelector('[data-teacher-program-options]');
    if (configElement) {
        const config = JSON.parse(configElement.textContent);
        const program = document.getElementById('program');
        const first = document.getElementById('subject1');
        const second = document.getElementById('subject2');
        const additional = document.getElementById('additional-subjects');
        const working = document.getElementById('working_sessions');
        const weekly = document.getElementById('weekly_hours');
        const partTime = document.getElementById('part_time_start');
        const custom = document.querySelector('details.my-4');
        const weekend = document.querySelector('[data-teacher-weekend]');
        const total = weekend.querySelector('[data-teacher-work-total]');
        const minutes = value => Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5));
        const workHours = (container, selector) => {
            const days = new Map();
            container.querySelectorAll(selector).forEach(row => {
                const day = row.querySelector('[name$="[weekday]"]').value;
                const start = minutes(row.querySelector('[name$="[start_time]"]').value);
                const end = minutes(row.querySelector('[name$="[end_time]"]').value);
                const unavailable = row.querySelector('[name$="[unavailable]"]').value === '1';
                if (end <= start) return;
                if (!days.has(day)) days.set(day, []);
                days.get(day).push({start, end, unavailable});
            });
            let duration = 0;
            for (const periods of days.values()) {
                const boundaries = [...new Set(periods.flatMap(period => [period.start, period.end]))].sort((a, b) => a - b);
                boundaries.slice(0, -1).forEach((start, index) => {
                    const end = boundaries[index + 1];
                    const covering = periods.filter(period => period.start <= start && period.end >= end);
                    if (covering.length && !covering.some(period => period.unavailable)) duration += end - start;
                });
            }
            return duration / 60;
        };
        const calculate = () => {
            if (program.value === 'Khmer') return;
            let hours = 0;
            if (working.value === 'EnglishFullTime') hours = 40;
            else if (working.value === 'EnglishPartTime') hours = 10;
            else if (working.value === 'Custom') {
                hours = workHours(custom, '.teacher-period-fields:not(:disabled)');
            } else {
                const sessions = working.value === 'Both' ? ['Morning', 'Afternoon'] : [working.value];
                sessions.forEach(session => {
                    const window = config[program.value]?.sessions[session];
                    if (!window) return;
                    hours += (minutes(window[1]) - minutes(window[0])) / 60 * 5;
                });
            }
            weekly.value = Math.round(hours * 100) / 100;
            const weekendHours = workHours(weekend, '[data-weekend-work-fields]:not(:disabled)');
            const display = value => Number(value.toFixed(2));
            total.textContent = `${display(hours)} weekday + ${display(weekendHours)} weekend = ${display(hours + weekendHours)} total hours per week`;
        };
        const syncWorking = () => {
            partTime.closest('.col-md-6').hidden = working.value !== 'EnglishPartTime';
            custom.hidden = working.value !== 'Custom';
            calculate();
        };
        const syncProgram = (initial = false) => {
            const khmer = program.value === 'Khmer';
            const options = config[program.value] || {subjects: [], sessions: {}};
            [first, second].forEach(select => {
                const wanted = initial ? select.value : '';
                select.replaceChildren(new Option('Choose a verified subject', ''), ...options.subjects.map(name => new Option(name, name)));
                select.value = wanted;
                select.closest('.col-md-6').hidden = !khmer;
                select.disabled = !khmer;
            });
            first.closest('[data-teacher-subject-options]').hidden = !khmer;
            first.required = khmer;
            const selected = initial ? [...additional.selectedOptions].map(option => option.value).concat(first.value, second.value) : options.subjects;
            additional.replaceChildren(...options.subjects.map(name => new Option(name, name, false, selected.includes(name))));
            additional.closest('.col-md-6').hidden = khmer;
            additional.disabled = khmer;
            additional.required = !khmer;
            weekly.disabled = !khmer;
            weekend.hidden = khmer;
            weekend.disabled = khmer;
            const wantedSession = initial ? working.value : '';
            working.replaceChildren(...Object.entries(options.sessions).map(([name, window]) => new Option(`${name} · ${window[0]}–${window[1]}`, name)));
            if (options.sessions.Morning && options.sessions.Afternoon) working.add(new Option('Morning and Afternoon', 'Both'));
            if (program.value === 'English') {
                working.add(new Option('English full-time — 40 hours/week', 'EnglishFullTime'));
                working.add(new Option('English part-time — 10 hours/week', 'EnglishPartTime'));
            }
            working.add(new Option('Custom weekday hours', 'Custom'));
            if ([...working.options].some(option => option.value === wantedSession)) working.value = wantedSession;
            syncWorking();
        };
        program.addEventListener('change', () => syncProgram());
        working.addEventListener('change', syncWorking);
        custom.addEventListener('change', calculate);
        weekend.querySelectorAll('[data-weekend-work-fields]').forEach(row => {
            const start = row.querySelector('[name$="[start_time]"]');
            const end = row.querySelector('[name$="[end_time]"]');
            const duration = row.querySelector('[data-work-duration]');
            const updateDuration = () => {
                end.setCustomValidity(end.value && end.value <= start.value ? 'End time must be after the start time.' : '');
                duration.value = end.value && start.value && end.value > start.value ? Number(((minutes(end.value) - minutes(start.value)) / 60).toFixed(4)) : '';
            };
            const updateEnd = () => {
                const finish = minutes(start.value) + Math.round(Number(duration.value) * 60);
                const valid = start.value && duration.value && finish > minutes(start.value) && finish < 1440;
                end.setCustomValidity(valid ? '' : 'Enter a duration that ends before midnight.');
                end.value = valid ? `${String(Math.floor(finish / 60)).padStart(2, '0')}:${String(finish % 60).padStart(2, '0')}` : '';
            };
            start.addEventListener('change', updateEnd);
            duration.addEventListener('change', updateEnd);
            end.addEventListener('change', updateDuration);
            updateDuration();
        });
        weekend.addEventListener('change', calculate);
        syncProgram(true);
    }
    document.querySelectorAll('[data-period]').forEach(input => input.addEventListener('change', () => { document.getElementById(input.dataset.period).disabled = !input.checked; }));
    const grade = document.getElementById('assign-grade');
    const session = document.getElementById('assign-session');
    const classes = document.getElementById('assign-class');
    const subjects = document.getElementById('assign-subject');
    const update = () => {
        if (!classes || !subjects) return;
        [...classes.options].forEach(o => { o.hidden = o.value && ((grade.value && o.dataset.grade !== grade.value) || (session.value && o.dataset.session !== session.value)); });
        if (classes.selectedOptions[0]?.hidden) classes.value = '';
        [...subjects.options].forEach(o => { o.hidden = o.value && ((classes.value && o.dataset.class !== classes.value) || (grade.value && o.dataset.grade !== grade.value) || (session.value && o.dataset.session !== session.value)); o.disabled = o.hidden; });
        if (subjects.selectedOptions[0]?.hidden) subjects.value = '';
    };
    [grade, session, classes].forEach(input => input?.addEventListener('change', update));
    const times = document.getElementById('teacher-available-time');
    const day = document.getElementById('assign-day');
    let requestVersion = 0;
    const loadTimes = async () => {
        if (!times || !subjects || !day) return;
        const version = ++requestVersion;
        times.replaceChildren(new Option('Choose an available time', ''));
        const status = document.getElementById('teacher-time-status');
        if (!subjects.value) { status.textContent = 'Choose a class subject first.'; return; }
        status.textContent = 'Checking availability…';
        try {
            const url = new URL(times.dataset.url, location.origin);
            url.search = new URLSearchParams({subject_id: subjects.value, day: day.value}).toString();
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            const result = await response.json();
            if (version !== requestVersion) return;
            if (!response.ok) throw new Error(result.message || 'Availability could not be checked.');
            result.times.forEach(t => times.add(new Option(`${t.start}–${t.end}`, `${t.start}|${t.end}`)));
            status.textContent = result.times.length ? 'Choose a time. Availability is checked again when saving.' : 'No valid one-hour times. Review qualifications, working hours, breaks, and existing lessons.';
        } catch (error) { if (version === requestVersion) status.textContent = error.message; }
    };
    if (times) {
        document.getElementById('teacher-time-picker').hidden = false;
        subjects.addEventListener('change', loadTimes);
        day.addEventListener('change', loadTimes);
        times.addEventListener('change', () => { if (times.value) { const [start, end] = times.value.split('|'); document.getElementById('starts_at').value = start; document.getElementById('ends_at').value = end; } });
    }
    if (location.hash) {
        const tab = document.querySelector(`[data-bs-target="${CSS.escape(location.hash)}"]`);
        if (tab && window.bootstrap) bootstrap.Tab.getOrCreateInstance(tab).show();
    }
})();
