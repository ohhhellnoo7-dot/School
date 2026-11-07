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
        const calculate = () => {
            if (program.value === 'Khmer') return;
            let hours = 0;
            if (working.value === 'EnglishFullTime') hours = 40;
            else if (working.value === 'EnglishPartTime') hours = 10;
            else if (working.value === 'Custom') {
                document.querySelectorAll('.teacher-period-fields:not(:disabled)').forEach(row => {
                    const fields = row.querySelectorAll('input[type="time"]');
                    const unavailable = row.querySelector('select[aria-label="Working or unavailable"]');
                    const minutes = value => Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5));
                    if (unavailable.value === '0') hours += Math.max(0, minutes(fields[1].value) - minutes(fields[0].value)) / 60;
                });
            } else {
                const sessions = working.value === 'Both' ? ['Morning', 'Afternoon'] : [working.value];
                sessions.forEach(session => {
                    const window = config[program.value]?.sessions[session];
                    if (!window) return;
                    const minutes = value => Number(value.slice(0, 2)) * 60 + Number(value.slice(3, 5));
                    hours += (minutes(window[1]) - minutes(window[0])) / 60 * 5;
                });
            }
            weekly.value = Math.round(hours * 100) / 100;
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
            weekly.readOnly = !khmer;
            const wantedSession = initial ? working.value : '';
            working.replaceChildren(...Object.entries(options.sessions).map(([name, window]) => new Option(`${name} · ${window[0]}–${window[1]}`, name)));
            if (options.sessions.Morning && options.sessions.Afternoon) working.add(new Option('Morning and Afternoon', 'Both'));
            if (program.value === 'English') {
                working.add(new Option('English full-time — 40 hours/week', 'EnglishFullTime'));
                working.add(new Option('English part-time — 10 hours/week', 'EnglishPartTime'));
            }
            if (['Khmer', 'English'].includes(program.value)) working.add(new Option('Custom weekly hours', 'Custom'));
            if ([...working.options].some(option => option.value === wantedSession)) working.value = wantedSession;
            syncWorking();
        };
        program.addEventListener('change', () => syncProgram());
        working.addEventListener('change', syncWorking);
        custom.addEventListener('change', calculate);
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
