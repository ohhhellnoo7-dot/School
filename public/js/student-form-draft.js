(() => {
    const form = document.querySelector('[data-student-draft]');
    if (!form) return;
    const key = form.dataset.studentDraft;
    const fields = [...form.querySelectorAll('input, select, textarea')].filter(field =>
        field.id && !['file', 'password', 'hidden', 'submit'].includes(field.type) &&
        !field.id.includes('password') && !field.readOnly);
    const primary = form.querySelector('[data-class-picker]');
    let restoring = false;
    const save = () => {
        if (restoring) return;
        const values = Object.fromEntries(fields.map(field => [field.id, field.value]));
        const selected = form.querySelector('#school_class_id')?.selectedOptions[0];
        const draft = {values, program: selected?.dataset.program,
            grade: form.querySelector('#class-grade')?.value,
            rows: [...form.querySelectorAll('[data-study-session-row]')].map(row => !row.hidden)};
        // Preserve a program choice even before a class has been selected.
        draft.program = primary?.dataset.draftProgram || draft.program;
        try { sessionStorage.setItem(key, JSON.stringify(draft)); } catch (_) { /* Storage may be disabled. */ }
    };
    const restore = () => {
        let draft;
        try { draft = JSON.parse(sessionStorage.getItem(key)); } catch (_) { return; }
        if (!draft?.values || form.dataset.hasErrors === '1') return;
        restoring = true;
        draft.rows?.forEach((visible, index) => {
            if (visible) form.querySelector('[data-add-study-session]')?.click();
        });
        primary?.dispatchEvent(new CustomEvent('restore-class-draft', {detail: {
            program: draft.program || '', grade: draft.grade || '', classId: draft.values.school_class_id || ''
        }}));
        fields.forEach(field => {
            if (!(field.id in draft.values) || ['school_class_id', 'class-grade'].includes(field.id)) return;
            field.value = draft.values[field.id];
            if (field.matches('[data-study-program]')) field.dispatchEvent(new Event('change'));
        });
        restoring = false;
    };
    window.addEventListener('DOMContentLoaded', restore);
    form.addEventListener('input', save);
    form.addEventListener('change', save);
    form.addEventListener('click', () => queueMicrotask(save));
    window.addEventListener('pagehide', save);
})();
