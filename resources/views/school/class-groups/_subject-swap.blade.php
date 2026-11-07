<dialog id="subject-swap-dialog" style="width:min(640px,90vw);border:1px solid var(--border-color);border-radius:24px;background:var(--surface);color:var(--text-primary)">
    <form method="post" action="{{ route('class-groups.swap-subject-teacher', $classGroup) }}" class="p-3">
        @csrf
        <h2 class="h5">Swap subject teachers</h2>
        <p data-subject-swap-summary></p>
        <p class="small text-secondary">Choose another Khmer class teaching this subject at the same weekday, time and academic year. Both subject assignments and their lessons exchange teachers. Save class edits before swapping.</p>
        <input type="hidden" name="source_subject_id" data-source-subject>
        <input type="hidden" name="source_teacher_id" data-source-teacher>
        <input type="hidden" name="target_teacher_id" data-target-teacher>
        <label class="form-label" for="target-subject">Other class and teacher</label>
        <select id="target-subject" class="form-select" name="target_subject_id" required></select>
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
    subjects.forEach(item => subject.add(new Option(item.label, item.id)));
    subject.value = @json((string) old('source_subject_id', ''));
    const syncTarget = () => {
        document.querySelector('[data-target-teacher]').value = target.selectedOptions[0]?.dataset.teacher || '';
    };
    const sync = () => {
        const selected = subjects.find(item => String(item.id) === subject.value);
        document.getElementById('khmer-subject-teacher').textContent = selected ? `Teacher: ${selected.teacher}. ${selected.times || 'No scheduled lessons yet.'}` : '';
        open.disabled = !selected;
        document.querySelector('[data-source-subject]').value = selected?.id || '';
        document.querySelector('[data-source-teacher]').value = selected?.teacher_id || '';
        document.querySelector('[data-subject-swap-summary]').textContent = selected ? `${selected.label} - ${selected.teacher}. ${selected.times}` : '';
        target.replaceChildren(new Option('Choose another class and teacher', ''));
        (selected?.candidates || []).forEach(item => {
            const option = new Option(item.label, item.id);
            option.dataset.teacher = item.teacher_id;
            target.add(option);
        });
        document.querySelector('[data-subject-swap-empty]').textContent = selected?.candidates.length ? '' : 'No other teacher has this subject with matching lesson times.';
        document.getElementById('submit-subject-swap').disabled = !selected?.candidates.length;
        syncTarget();
    };
    subject.addEventListener('change', sync);
    target.addEventListener('change', syncTarget);
    open.addEventListener('click', () => dialog.showModal());
    document.getElementById('close-subject-swap').addEventListener('click', () => dialog.close());
    sync();
    @if($errors->hasAny(['subject_swap', 'source_subject_id', 'target_subject_id']))
    dialog.showModal();
    @endif
})();
</script>
@endpush
