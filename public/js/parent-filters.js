(() => {
    const program = document.getElementById('parent-program');
    const session = document.getElementById('parent-session');
    if (!program || !session) return;
    const options = Array.from(session.options);
    const grade = document.getElementById('parent-grade');
    const grades = Array.from(grade.options).filter(option => option.value);
    function updateGrades() {
        const selected = grade.value;
        const seen = new Set();
        const matching = grades.filter(option => {
            if (program.value && option.dataset.program !== program.value) return false;
            if (session.value && option.dataset.session && option.dataset.session !== session.value) return false;
            if (program.value === 'Khmer' && option.dataset.session === 'Evening') return false;
            if (seen.has(option.value)) return false;
            seen.add(option.value);
            return true;
        });
        grade.replaceChildren(new Option('All grades / classes', ''), ...matching);
        grade.value = seen.has(selected) ? selected : '';
    }
    function updateSessions() {
        const selected = session.value;
        session.replaceChildren(...options.filter(option =>
            (program.value !== 'Chinese' || option.value === 'Evening') &&
            (program.value !== 'Khmer' || option.value !== 'Evening')
        ));
        session.value = program.value === 'Chinese' ? 'Evening' : selected;
        if (session.selectedIndex < 0) session.value = '';
        updateGrades();
    }
    program.addEventListener('change', updateSessions);
    session.addEventListener('change', updateGrades);
    updateSessions();
})();
