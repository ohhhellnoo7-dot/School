<form method="post" action="{{ route('reports.expenses.advances.store') }}">
    @csrf
    @if($errors->any() && old('request_key'))
        <ul class="alert alert-danger ps-4" role="alert">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
    <label class="form-label" for="advance-search">Search teachers by name</label>
    <input class="form-control mb-3" id="advance-search" type="search" placeholder="Type the first letters of a name" autocomplete="off">
    <label class="form-label" for="advance-employee">Select teacher</label>
    <select class="form-select" id="advance-employee" name="employee_id" size="6" required>
        @foreach($teachers as $teacher)
            <option value="{{ $teacher->id }}" data-name="{{ $teacher->name }}" @selected((string) old('employee_id') === (string) $teacher->id)>{{ $teacher->name }}@if($teacher->profile_number) &middot; {{ $teacher->profile_number }}@endif</option>
        @endforeach
    </select>
    <p class="small text-secondary mt-2" id="advance-search-status" role="status"></p>
    <fieldset class="mt-3" id="advance-details">
        <div class="row g-3">
            <div class="col-sm-6">
                <label class="form-label" for="advance-amount">Amount (USD)</label>
                <input class="form-control" id="advance-amount" name="amount" type="number" min="0.01" max="9999999999.99" step="0.01" value="{{ old('amount') }}" placeholder="20.00" required>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="advance-date">Date paid out</label>
                <input class="form-control" id="advance-date" name="spent_on" type="date" value="{{ old('spent_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="advance-note">Note (optional)</label>
                <textarea class="form-control" id="advance-note" name="note" rows="2" maxlength="1000">{{ old('note') }}</textarea>
            </div>
        </div>
        <button class="btn btn-primary mt-3">Record advance</button>
    </fieldset>
</form>

@push('scripts')
<script>
(() => {
    const search = document.getElementById('advance-search');
    const picker = document.getElementById('advance-employee');
    const details = document.getElementById('advance-details');
    const status = document.getElementById('advance-search-status');
    const options = Array.from(picker.options);
    function updateDetails() {
        details.hidden = !picker.value;
        details.disabled = details.hidden;
    }
    search.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.isComposing) {
            event.preventDefault();
            picker.focus();
        }
    });
    search.addEventListener('input', () => {
        const selected = picker.value;
        const prefix = search.value.trim().toLocaleLowerCase();
        const matches = options.filter(option => option.dataset.name.toLocaleLowerCase().startsWith(prefix));
        picker.replaceChildren(...matches);
        picker.value = selected;
        status.textContent = matches.length ? '' : @js(__('No teachers match that name.'));
        updateDetails();
    });
    picker.addEventListener('change', updateDetails);
    updateDetails();

    const modal = search.closest('.modal');
    if (modal) {
        modal.addEventListener('shown.bs.modal', () => search.focus());
        if (modal.hasAttribute('data-reopen-advance')) bootstrap.Modal.getOrCreateInstance(modal).show();
    }
})();
</script>
@endpush
