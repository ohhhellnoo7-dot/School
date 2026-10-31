@extends('school.layout')
@section('title', $classGroup->exists ? 'Edit class group' : 'Add class group')

@section('content')
<div class="page-heading">
    <div><div class="eyebrow">Class management</div><h1>{{ $classGroup->exists ? 'Edit '.$classGroup->name : 'Create class group' }}</h1><p>Class identity stays the same when students attend at different times.</p></div>
    <a class="btn btn-outline-secondary" href="{{ $classGroup->exists ? route('class-groups.show', $classGroup) : route('class-groups.index') }}">Cancel</a>
</div>

<form class="panel entity-form" method="post" action="{{ $classGroup->exists ? route('class-groups.update', $classGroup) : route('class-groups.store') }}">
    @csrf
    @if($classGroup->exists) @method('PUT') @endif
    <div class="panel-header"><div><h2>Class group details</h2><p>Sessions are assigned automatically according to the selected academic program.</p></div></div>
    <div class="panel-body form-section-grid">
        <div>
            <label class="form-label" for="name">Class name <span aria-hidden="true">*</span></label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $classGroup->name) }}" maxlength="100" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="program">Academic program <span aria-hidden="true">*</span></label>
            <select class="form-select @error('program') is-invalid @enderror" id="program" name="program" required data-program-select>
                <option value="">Choose a program</option>
                @foreach(array_keys($programSessions) as $program)<option value="{{ $program }}" @selected(old('program', $classGroup->program) === $program)>{{ $program }}</option>@endforeach
            </select>
            @error('program')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="grade">{{ __('Grade') }} <span aria-hidden="true">*</span></label>
            <select class="form-select @error('grade') is-invalid @enderror" id="grade" name="grade" required>
                <option value="">Choose a grade</option>
                @for($grade = 1; $grade <= 12; $grade++)<option value="{{ $grade }}" @selected((string) old('grade', $classGroup->grade) === (string) $grade)>Grade {{ $grade }}</option>@endfor
            </select>
            @error('grade')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="section">Section</label>
            <select class="form-select @error('section') is-invalid @enderror" id="section" name="section">
                <option value="">No section</option>
                @foreach(['A', 'B', 'C', 'D', 'E'] as $section)<option value="{{ $section }}" @selected(old('section', $classGroup->section) === $section)>{{ $section }}</option>@endforeach
            </select>
            <div class="form-text">Khmer uses sections A–E. English and Chinese have one identity per grade.</div>
            @error('section')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="teacher_id">Class teacher</label>
            <select class="form-select @error('teacher_id') is-invalid @enderror" id="teacher_id" name="teacher_id">
                <option value="">Unassigned</option>
                @foreach($teachers as $teacher)<option value="{{ $teacher->id }}" data-program="{{ $teacher->program }}" @selected((string) old('teacher_id', $classGroup->teacher_id) === (string) $teacher->id)>{{ $teacher->name }}{{ $teacher->program ? ' · '.$teacher->program : '' }}</option>@endforeach
            </select>
            @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="capacity">Student capacity <span aria-hidden="true">*</span></label>
            <input class="form-control @error('capacity') is-invalid @enderror" id="capacity" type="number" name="capacity" min="1" max="500" value="{{ old('capacity', $classGroup->capacity ?: 30) }}" required>
            @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="status">{{ __('Status') }} <span aria-hidden="true">*</span></label>
            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                <option value="active" @selected(old('status', $classGroup->status ?: 'active') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(old('status', $classGroup->status) === 'inactive')>{{ __('Inactive') }}</option>
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <aside class="session-preview" aria-live="polite">
            <strong>Program sessions</strong>
            @foreach($programSessions as $program => $sessions)
                <div data-session-preview="{{ $program }}" @if(old('program', $classGroup->program) !== $program) hidden @endif>
                    @foreach($sessions as $session => [$start, $end])<span><i class="bi bi-clock" aria-hidden="true"></i> {{ $session }} · {{ $start }}–{{ $end }}</span>@endforeach
                </div>
            @endforeach
            <div data-session-empty @if(old('program', $classGroup->program)) hidden @endif>Select a program to see its sessions.</div>
        </aside>
    </div>
    <div class="form-footer"><a class="btn btn-light" href="{{ route('class-groups.index') }}">Cancel</a><button class="btn btn-primary" type="submit">{{ $classGroup->exists ? 'Save changes' : 'Create class group' }}</button></div>
</form>
@endsection

@push('scripts')
<script>
document.querySelector('[data-program-select]')?.addEventListener('change', event => {
    document.querySelectorAll('[data-session-preview]').forEach(item => item.hidden = item.dataset.sessionPreview !== event.target.value);
    const empty = document.querySelector('[data-session-empty]');
    if (empty) empty.hidden = Boolean(event.target.value);
});
</script>
@endpush
