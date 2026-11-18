<dialog id="subject-swap-dialog" style="width:min(640px,90vw);border:1px solid var(--border-color);border-radius:24px;background:var(--surface);color:var(--text-primary)">
    <form method="post" action="{{ route('class-groups.swap-subject-teacher', $classGroup) }}" class="p-3">
        @csrf
        <h2 class="h5">Swap subject teachers</h2>
        <p><mark class="rounded px-2 py-1 fw-semibold bg-warning-subtle text-warning-emphasis" data-subject-swap-highlight></mark> <span data-subject-swap-summary></span></p>
        <p class="small text-secondary">Choose a teacher who teaches this subject in another Khmer class in the selected session. The status previews both teachers’ availability for the lesson times they would take over. Timetable conflicts are checked when you swap. Save class edits before swapping.</p>
        <input type="hidden" name="source_subject_id" data-source-subject>
        <input type="hidden" name="source_teacher_id" data-source-teacher>
        <input type="hidden" name="target_teacher_id" data-target-teacher>
        <fieldset>
            <legend class="form-label fs-6">Teacher and class</legend>
            <div id="target-subject" class="border rounded overflow-auto" style="max-height:min(38vh,22rem)" hidden></div>
        </fieldset>
        <p data-subject-swap-empty class="form-text" role="status"></p>
        @foreach(['subject_swap', 'source_subject_id', 'target_subject_id', 'source_teacher_id', 'target_teacher_id'] as $field)
        @error($field)<p class="text-danger" role="alert">{{ $message }}</p>@enderror
        @endforeach
        <div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn btn-light" id="close-subject-swap">Cancel</button><button type="submit" class="btn btn-primary" id="submit-subject-swap">Swap teachers</button></div>
    </form>
</dialog>
@push('scripts')
<script type="application/json" id="subject-swap-data">@json($subjectSwaps)</script>
<script>
(() => {
    const subjects = JSON.parse(document.getElementById('subject-swap-data').textContent);
    const subject = document.getElementById('khmer-subject');
    const target = document.getElementById('target-subject');
    const dialog = document.getElementById('subject-swap-dialog');
    const open = document.getElementById('open-subject-swap');
    const sessionButtons = [...document.querySelectorAll('[data-session-preview="Khmer"] [data-subject-session]')];
    const previousSubject = @json((string) old('source_subject_id', ''));
    const highlight = document.querySelector('[data-subject-swap-highlight]');
    const submit = document.getElementById('submit-subject-swap');
    const colors = { Free: 'success', Busy: 'danger', Unavailable: 'warning' };
    let activeSession = subjects.find(item => String(item.id) === previousSubject)?.session || sessionButtons[0]?.dataset.subjectSession || 'Morning';
    const syncTarget = () => {
        const option = target.querySelector('input:checked');
        document.querySelector('[data-target-teacher]').value = option?.dataset.teacher || '';
        submit.disabled = !option;
    };
    const sync = () => {
        const selected = subjects.find(item => String(item.id) === subject.value);
        const teacherDetails = document.getElementById('khmer-subject-teacher');
        const selectedName = document.createElement('mark');
        selectedName.className = highlight.className;
        selectedName.textContent = selected?.name || '';
        teacherDetails.replaceChildren();
        if (selected) teacherDetails.append(selectedName, ` Teacher: ${selected.teacher}. ${selected.times || 'No scheduled lessons yet.'}`);
        open.disabled = !selected;
        document.querySelector('[data-source-subject]').value = selected?.id || '';
        document.querySelector('[data-source-teacher]').value = selected?.teacher_id || '';
        highlight.textContent = selected?.name || '';
        document.querySelector('[data-subject-swap-summary]').textContent = selected ? `${selected.session} - ${selected.teacher}. ${selected.times}` : '';
        target.replaceChildren();
        (selected?.candidates || []).forEach(item => {
            const row = document.createElement('label');
            row.className = 'd-flex align-items-center gap-2 px-3 py-2 border-bottom';
            const option = document.createElement('input');
            option.type = 'radio';
            option.name = 'target_subject_id';
            option.value = item.id;
            option.className = 'form-check-input flex-shrink-0 mt-0';
            option.required = true;
            option.dataset.teacher = item.teacher_id;
            const name = document.createElement('span');
            name.className = 'flex-grow-1';
            name.textContent = item.label;
            const badge = document.createElement('span');
            badge.className = `badge text-bg-${colors[item.availability] || 'secondary'}`;
            badge.textContent = item.availability;
            row.append(option, name, badge);
            target.append(row);
        });
        target.hidden = !selected?.candidates.length;
        document.querySelector('[data-subject-swap-empty]').textContent = selected?.candidates.length ? '' : 'No other active teacher is assigned to this subject in another Khmer class in the selected session.';
        syncTarget();
    };
    const filterSession = (wantedSubject = '') => {
        const previous = subjects.find(item => String(item.id) === subject.value);
        const visible = subjects.filter(item => item.session === activeSession);
        subject.replaceChildren(new Option(visible.length ? 'Choose a subject' : 'No subjects in this session', ''), ...visible.map(item => new Option(`${item.teacher} - ${item.label}`, item.id)));
        const selected = visible.find(item => String(item.id) === wantedSubject)
            || visible.find(item => item.name === previous?.name);
        subject.value = selected ? String(selected.id) : '';
        sessionButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.subjectSession === activeSession)));
        sync();
    };
    sessionButtons.forEach(button => button.addEventListener('click', () => {
        activeSession = button.dataset.subjectSession;
        filterSession();
    }));
    subject.addEventListener('change', sync);
    target.addEventListener('change', syncTarget);
    open.addEventListener('click', () => dialog.showModal());
    document.getElementById('close-subject-swap').addEventListener('click', () => dialog.close());
    filterSession(previousSubject);
    @if($errors->hasAny(['subject_swap', 'source_subject_id', 'target_subject_id']))
    dialog.showModal();
    @endif
})();
</script>
@endpush
