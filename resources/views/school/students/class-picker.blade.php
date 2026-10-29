<div data-class-picker>
    <div data-program-controls hidden>
        <label class="form-label" for="class-program">Class program *</label>
        <div class="position-relative mb-2" data-program-dropdown>
            <button type="button" class="form-select text-start" id="class-program" aria-expanded="false" aria-controls="class-program-options">Select Khmer, English or Chinese</button>
            <div id="class-program-options" class="position-absolute start-0 end-0 border rounded shadow p-2 bg-body" style="z-index:1050" hidden>
                @foreach(['Khmer', 'English', 'Chinese'] as $program)
                    @php($gradeCount = $classes->where('program', $program)->pluck('name')->unique()->count())
                    <button type="button" class="btn btn-light w-100 text-start mb-1" data-program="{{ $program }}">{{ $program }} <span class="small text-secondary">({{ $gradeCount }} grades)</span></button>
                @endforeach
                @if($classes->contains(fn ($class) => !$class->program))
                    <button type="button" class="btn btn-light w-100 text-start" data-program="Unassigned">Other classes</button>
                @endif
            </div>
        </div>
        <p class="small text-secondary" data-grade-summary aria-live="polite">Choose a program to see its grades.</p>
        <div class="mb-3">
            <label class="form-label" for="class-grade">Grade *</label>
            <select class="form-select" id="class-grade" disabled><option value="">Select a program first</option></select>
        </div>
    </div>
    <label class="form-label" for="school_class_id" data-class-label>Class *</label>
    <select class="form-select @error('school_class_id') is-invalid @enderror" id="school_class_id" name="school_class_id" required>
        <option value="">Select class</option>
        @foreach($classes as $class)
            <option value="{{ $class->id }}" data-program="{{ $class->program ?: 'Unassigned' }}" data-grade="{{ $class->name }}" @selected(old('school_class_id', $student->school_class_id) == $class->id)>{{ $class->label }}</option>
        @endforeach
    </select>
    @error('school_class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
@push('scripts')
<script src="{{ asset('js/class-picker.js') }}"></script>
@endpush
