(() => {
    const classSelect = document.getElementById('class');
    const programSelect = document.getElementById('program');
    const sessionSelect = document.getElementById('session');
    if (!classSelect || !programSelect || !sessionSelect) return;

    const classes = Array.from(classSelect.options).filter(option => option.value);
    const sessions = Array.from(sessionSelect.options);
    const updateClasses = () => {
        const selectedSession = sessionSelect.value;
        sessionSelect.replaceChildren(...sessions.filter(option =>
            (programSelect.value !== 'Chinese' || option.value === 'Evening') &&
            (programSelect.value !== 'Khmer' || option.value !== 'Evening')
        ));
        sessionSelect.value = programSelect.value === 'Chinese' ? 'Evening' : selectedSession;
        if (sessionSelect.selectedIndex < 0) sessionSelect.value = '';
        const selected = classSelect.value;
        const matching = classes.filter(option =>
            (!programSelect.value || option.dataset.program === programSelect.value) &&
            (!sessionSelect.value || option.dataset.session === sessionSelect.value)
        );
        classSelect.replaceChildren(new Option('All classes', ''), ...matching);
        classSelect.value = matching.some(option => option.value === selected) ? selected : '';
    };
    programSelect.addEventListener('change', updateClasses);
    sessionSelect.addEventListener('change', updateClasses);
    updateClasses();
})();
