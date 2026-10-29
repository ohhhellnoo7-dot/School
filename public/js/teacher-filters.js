(() => {
    const program = document.getElementById('program');
    const session = document.getElementById('teacher-session');
    const classes = document.getElementById('teacher-class');
    if (!program || !session || !classes) return;
    const sessions = Array.from(session.options);
    const options = Array.from(classes.options).filter(option => option.value);
    function updateClasses() {
        const selected = classes.value;
        const matching = options.filter(option =>
            (!program.value || option.dataset.program === program.value) &&
            (!session.value || !option.dataset.session || option.dataset.session === session.value) &&
            (program.value !== 'Khmer' || option.dataset.session !== 'Evening') &&
            (program.value !== 'Chinese' || !option.dataset.session || option.dataset.session === 'Evening')
        );
        classes.replaceChildren(new Option('All classes', ''), ...matching);
        classes.value = matching.some(option => option.value === selected) ? selected : '';
    }
    function updateSessions() {
        const selected = session.value;
        session.replaceChildren(...sessions.filter(option =>
            (program.value !== 'Chinese' || option.value === 'Evening') &&
            (program.value !== 'Khmer' || option.value !== 'Evening')
        ));
        session.value = program.value === 'Chinese' ? 'Evening' : selected;
        if (session.selectedIndex < 0) session.value = '';
        updateClasses();
    }
    program.addEventListener('change', updateSessions);
    session.addEventListener('change', updateClasses);
    updateSessions();
})();
