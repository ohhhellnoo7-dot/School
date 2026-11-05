<section class="mt-4 mb-4" data-study-sessions>
    <h3 class="h6">Additional study sessions</h3>
    <p class="small text-secondary">Keep the class above as the primary class. Add up to two other programs, one per session. For example: Khmer morning, English afternoon, Chinese evening.</p>
    <input type="hidden" name="study_sessions_submitted" value="1">
    @php($additional = old('additional_class_ids', $student->exists ? $student->studyClasses()->where('school_classes.id', '!=', $student->school_class_id)->pluck('school_classes.id')->all() : []))
    @foreach([0, 1] as $index)
    <div class="mb-3" data-study-session-row @if(!isset($additional[$index])) hidden @endif>
        <div class="d-flex align-items-end gap-2">
            <div class="flex-grow-1" style="min-width:0">@include('school.students._session-class-picker', ['fieldId' => 'additional-class-'.$index, 'fieldName' => 'additional_class_ids[]', 'selectedClassId' => $additional[$index] ?? null, 'disabled' => !isset($additional[$index])])</div>
            <button class="btn btn-outline-secondary" type="button" data-remove-study-session aria-label="Remove additional session"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
    </div>
    @endforeach
    <button class="btn btn-outline-primary w-100" type="button" data-add-study-session><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add study session</button>
</section>
@push('scripts')
<script>
document.querySelectorAll('[data-study-sessions]').forEach(section => {
    const add = section.querySelector('[data-add-study-session]');
    const rows = [...section.querySelectorAll('[data-study-session-row]')];
    const update = () => { add.disabled = rows.every(row => !row.hidden); };
    add.addEventListener('click', () => {
        const row = rows.find(row => row.hidden);
        if (!row) return;
        row.hidden = false;
        const program = row.querySelector('[data-study-program]');
        program.disabled = false;
        program.dispatchEvent(new Event('change'));
        program.focus();
        update();
    });
    rows.forEach(row => row.querySelector('[data-remove-study-session]').addEventListener('click', () => {
        row.hidden = true;
        row.querySelectorAll('select').forEach(select => { select.disabled = true; select.value = ''; });
        update();
    }));
    update();
});
</script>
@endpush
