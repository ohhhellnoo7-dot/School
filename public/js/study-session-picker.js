document.querySelectorAll('[data-session-class-picker]').forEach(picker => {
    const program = picker.querySelector('[data-study-program]');
    const classes = picker.querySelector('[data-study-class]');
    const options = [...classes.options].filter(option => option.value);
    const filter = () => {
        const selected = classes.value;
        const matches = options.filter(option => option.dataset.program === program.value);
        const prompt = !program.value ? 'Choose a program first' : matches.length ? 'Choose a class and session' : 'No classes available';
        classes.replaceChildren(new Option(prompt, ''), ...matches.map(option => option.cloneNode(true)));
        classes.value = matches.some(option => option.value === selected) ? selected : '';
        classes.disabled = program.disabled || !program.value;
    };
    program.addEventListener('change', filter);
    filter();
});
