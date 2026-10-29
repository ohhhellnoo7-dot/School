(() => {
    const program = document.getElementById('filter');
    const session = document.getElementById('class-session');
    if (!program || !session) return;

    const sessions = Array.from(session.options);
    function updateSessions() {
        const selected = session.value;
        session.replaceChildren(...sessions.filter(option =>
            (program.value !== 'Chinese' || option.value === 'Evening') &&
            (program.value !== 'Khmer' || option.value !== 'Evening')
        ));
        session.value = program.value === 'Chinese' ? 'Evening' : selected;
        if (session.selectedIndex < 0) session.value = '';
    }

    program.addEventListener('change', updateSessions);
    updateSessions();
})();
