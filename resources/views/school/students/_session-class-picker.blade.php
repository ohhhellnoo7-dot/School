@php($selectedProgram = $classes->firstWhere('id', $selectedClassId ?? null)?->program)
<div data-session-class-picker>
    <label class="form-label" for="{{ $fieldId }}-program">Program</label>
    <select id="{{ $fieldId }}-program" class="form-select mb-3" data-study-program required @disabled($disabled ?? false)>
        <option value="">Choose a program</option>
        @foreach($classes->pluck('program')->filter()->unique()->sort() as $program)
        <option value="{{ $program }}" @selected($selectedProgram === $program)>{{ $program }}</option>
        @endforeach
    </select>
    <label class="form-label" for="{{ $fieldId }}">Class and session</label>
    <select id="{{ $fieldId }}" name="{{ $fieldName }}" class="form-select" data-study-class required @disabled(($disabled ?? false) || !$selectedProgram)>
        <option value="">Choose a program first</option>
        @foreach($classes as $class)
        <option value="{{ $class->id }}" data-program="{{ $class->program }}" @selected((string) ($selectedClassId ?? '') === (string) $class->id)>{{ $class->label }}</option>
        @endforeach
    </select>
</div>
@once
@push('scripts')
<script src="{{ asset('js/study-session-picker.js') }}?v={{ filemtime(public_path('js/study-session-picker.js')) }}"></script>
@endpush
@endonce
