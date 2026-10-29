document.querySelectorAll('[data-class-picker]').forEach(picker => {
    const classSelect = picker.querySelector('[name="school_class_id"]');
    const gradeSelect = picker.querySelector('#class-grade');
    const dropdown = picker.querySelector('[data-program-dropdown]');
    const trigger = picker.querySelector('#class-program');
    const menu = picker.querySelector('#class-program-options');
    const summary = picker.querySelector('[data-grade-summary]');
    const classes = Array.from(classSelect.options).filter(option => option.value).map(option => ({
        id: option.value, label: option.textContent, program: option.dataset.program, grade: option.dataset.grade
    }));
    const original = classes.find(item => item.id === classSelect.value);
    let program = '';

    function setOpen(open) {
        menu.hidden = !open;
        trigger.setAttribute('aria-expanded', String(open));
    }

    function showClasses(selectedId = '') {
        const matching = classes.filter(item => item.program === program && item.grade === gradeSelect.value);
        classSelect.replaceChildren(new Option(matching.length ? 'Select section and session' : 'Select a grade first', ''));
        matching.forEach(item => classSelect.add(new Option(item.label, item.id)));
        classSelect.value = selectedId;
        // Keep the required field enabled so an incomplete choice prevents submission.
    }

    function selectProgram(value, selectedGrade = '', selectedId = '') {
        program = value;
        trigger.textContent = value === 'Unassigned' ? 'Other classes' : `${value} classes`;
        const grades = [...new Set(classes.filter(item => item.program === value).map(item => item.grade))]
            .sort((a, b) => a.localeCompare(b, undefined, {numeric: true}));
        gradeSelect.replaceChildren(new Option('Select grade', ''));
        grades.forEach(grade => gradeSelect.add(new Option(grade, grade)));
        gradeSelect.disabled = grades.length === 0;
        gradeSelect.required = grades.length > 0;
        gradeSelect.value = selectedGrade;
        summary.textContent = grades.length ? `${grades.length} grades available: ${grades.join(', ')}.` : 'No grades available for this program yet.';
        showClasses(selectedId);
        setOpen(false);
    }

    dropdown.addEventListener('mouseenter', () => setOpen(true));
    dropdown.addEventListener('mouseleave', () => setOpen(false));
    trigger.addEventListener('click', () => setOpen(menu.hidden));
    trigger.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            menu.querySelector('button')?.focus();
        }
    });
    dropdown.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            setOpen(false);
            trigger.focus();
        }
    });
    dropdown.addEventListener('focusout', event => {
        if (!dropdown.contains(event.relatedTarget)) setOpen(false);
    });
    document.addEventListener('click', event => {
        if (!dropdown.contains(event.target)) setOpen(false);
    });
    menu.querySelectorAll('[data-program]').forEach(button => button.addEventListener('click', () => {
        selectProgram(button.dataset.program);
        if (!gradeSelect.disabled) gradeSelect.focus();
        else trigger.focus();
    }));
    gradeSelect.addEventListener('change', () => showClasses());
    picker.querySelector('[data-program-controls]').hidden = false;
    picker.querySelector('[data-class-label]').textContent = 'Section and session *';
    if (original) selectProgram(original.program, original.grade, original.id);
    else showClasses();
});
