(() => {
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
