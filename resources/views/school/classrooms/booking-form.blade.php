@extends('school.layout')
@section('title', $booking->exists ? 'Edit classroom booking' : 'Assign classroom')

@section('content')
<div class="page-heading"><div><div class="eyebrow">Conflict-safe scheduling</div><h1>{{ $booking->exists ? 'Edit classroom booking' : 'Assign a classroom' }}</h1><p>Reserve the room for its student cohort. Subject teachers and lesson times come from the shared timetable. An optional supervisor is a separate session commitment.</p></div><a class="btn btn-outline-secondary" href="{{ $booking->classroom_id ? route('classrooms.show', $booking->classroom_id) : route('classrooms.index') }}">Cancel</a></div>

<form class="panel entity-form booking-form" method="post" action="{{ $booking->exists ? route('classroom-bookings.update', $booking) : route('classroom-bookings.store') }}" data-booking-form>
    @csrf @if($booking->exists) @method('PUT') @endif
    <div class="panel-header"><div><h2>Booking information</h2><p>Choose one valid program session and one physical classroom.</p></div></div>
    <div class="panel-body form-section-grid">
        <div>
            <label class="form-label" for="booking-program">Academic program</label>
            <select class="form-select" id="booking-program" data-booking-program>
                <option value="">{{ __('All programs') }}</option>@foreach(['Khmer', 'English', 'Chinese'] as $program)<option value="{{ $program }}" @selected(old('program', $booking->classGroup?->program) === $program)>{{ $program }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="class_group_id">Class group <span aria-hidden="true">*</span></label>
            <select class="form-select @error('class_group_id') is-invalid @enderror" id="class_group_id" name="class_group_id" required data-booking-group>
                <option value="">Choose a class group</option>
                @foreach($classGroups as $classGroup)<option value="{{ $classGroup->id }}" data-program="{{ $classGroup->program }}" data-teacher="{{ $classGroup->teacher_id }}" @selected((string) old('class_group_id', $booking->class_group_id) === (string) $classGroup->id)>{{ $classGroup->display_name }}</option>@endforeach
            </select>
            @error('class_group_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="class_session_id">{{ __('Session') }} <span aria-hidden="true">*</span></label>
            <select class="form-select @error('class_session_id') is-invalid @enderror" id="class_session_id" name="class_session_id" required data-booking-session>
                <option value="">Choose a session</option>
                @foreach($classGroups as $classGroup)@foreach($classGroup->sessions as $session)<option value="{{ $session->id }}" data-group="{{ $classGroup->id }}" data-start="{{ substr((string) $session->starts_at, 0, 5) }}" data-end="{{ substr((string) $session->ends_at, 0, 5) }}" @selected((string) old('class_session_id', $booking->class_session_id) === (string) $session->id)>{{ $classGroup->display_name }} · {{ $session->session }} ({{ substr((string) $session->starts_at, 0, 5) }}-{{ substr((string) $session->ends_at, 0, 5) }})</option>@endforeach @endforeach
            </select>
            @error('class_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="classroom_id">Physical classroom <span aria-hidden="true">*</span></label>
            <select class="form-select @error('classroom_id') is-invalid @enderror" id="classroom_id" name="classroom_id" required>
                <option value="">Choose a room</option>
                @foreach($classrooms as $room)<option value="{{ $room->id }}" @selected((string) old('classroom_id', $booking->classroom_id) === (string) $room->id) @disabled($room->status === 'out_of_service' && (string) $booking->classroom_id !== (string) $room->id)>{{ $room->room_number }} · Capacity {{ $room->capacity }}{{ $room->status === 'out_of_service' ? ' · Out of service' : '' }}</option>@endforeach
            </select>
            @error('classroom_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="teacher_id">Session supervisor (optional)</label>
            <select class="form-select @error('teacher_id') is-invalid @enderror" id="teacher_id" name="teacher_id" data-booking-teacher><option value="">No supervisor - teachers come from lessons</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected((string) old('teacher_id', $booking->teacher_id) === (string) $teacher->id)>{{ $teacher->name }}{{ $teacher->program ? ' · '.$teacher->program : '' }}</option>@endforeach</select>
            @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="day_of_week">Day <span aria-hidden="true">*</span></label>
            <select class="form-select @error('day_of_week') is-invalid @enderror" id="day_of_week" name="day_of_week" required>@foreach($days as $day)<option value="{{ $day }}" @selected(old('day_of_week', $booking->day_of_week) === $day)>{{ $day }}</option>@endforeach</select>
            @error('day_of_week')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="starts_at">Start time <span aria-hidden="true">*</span></label>
            <input class="form-control @error('starts_at') is-invalid @enderror" id="starts_at" type="time" name="starts_at" value="{{ old('starts_at', $booking->starts_at ? substr((string) $booking->starts_at, 0, 5) : '') }}" required data-booking-start>
            @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div>
            <label class="form-label" for="ends_at">End time <span aria-hidden="true">*</span></label>
            <input class="form-control @error('ends_at') is-invalid @enderror" id="ends_at" type="time" name="ends_at" value="{{ old('ends_at', $booking->ends_at ? substr((string) $booking->ends_at, 0, 5) : '') }}" required data-booking-end>
            @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <aside class="booking-safety-note form-wide"><i class="bi bi-shield-check" aria-hidden="true"></i><span><strong>Conflict protection is on</strong><small>If this room, teacher, or class group is already occupied, the booking will not be saved and you will see the conflicting class and time.</small></span></aside>
    </div>
    <div class="form-footer"><a class="btn btn-light" href="{{ $booking->classroom_id ? route('classrooms.show', $booking->classroom_id) : route('classrooms.index') }}">Cancel</a><button class="btn btn-primary" type="submit">{{ $booking->exists ? 'Save booking' : 'Assign classroom' }}</button></div>
</form>

@if($booking->exists)
<div class="danger-zone"><div><strong>Remove this booking</strong><span>The room becomes available for this weekday and time.</span></div><form method="post" action="{{ route('classroom-bookings.destroy', $booking) }}" data-confirm="Remove this classroom booking?">@csrf @method('DELETE')<button class="btn btn-outline-danger">Remove booking</button></form></div>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-booking-form]');
    if (!form) return;
    const program = form.querySelector('[data-booking-program]');
    const group = form.querySelector('[data-booking-group]');
    const session = form.querySelector('[data-booking-session]');
    const teacher = form.querySelector('[data-booking-teacher]');
    const start = form.querySelector('[data-booking-start]');
    const end = form.querySelector('[data-booking-end]');

    const filterGroups = () => {
        [...group.options].forEach(option => option.hidden = Boolean(option.value && program.value && option.dataset.program !== program.value));
        if (group.selectedOptions[0]?.hidden) group.value = '';
        filterSessions(false);
    };
    const filterSessions = (chooseFirst = true) => {
        let first = null;
        [...session.options].forEach(option => {
            option.hidden = Boolean(option.value && option.dataset.group !== group.value);
            option.disabled = option.hidden;
            if (option.value && !option.hidden && !first) first = option;
        });
        if (session.selectedOptions[0]?.hidden || (chooseFirst && !session.value)) {
            session.value = first?.value || '';
            session.dispatchEvent(new Event('change'));
        }
    };
    program.addEventListener('change', filterGroups);
    group.addEventListener('change', () => {
        filterSessions();
        const selected = group.selectedOptions[0];

    });
    session.addEventListener('change', () => {
        const selected = session.selectedOptions[0];
        if (!selected?.value) return;
        start.value = selected.dataset.start || start.value;
        end.value = selected.dataset.end || end.value;
    });
    filterGroups();
    filterSessions(false);
})();
</script>
@endpush
