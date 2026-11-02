@extends('school.layout')
@section('title', $classroom->exists ? 'Edit classroom' : 'Add classroom')

@section('content')
<div class="page-heading room-editor-heading"><div><a class="room-back-link" href="{{ route('classrooms.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Classrooms</a><h1>{{ $classroom->exists ? 'Edit room '.$classroom->room_number : 'Add a classroom' }}</h1><p>Set up your room, then add a teaching schedule when you are ready.</p></div><a class="btn btn-outline-secondary" href="{{ $classroom->exists ? route('classrooms.show', $classroom) : route('classrooms.index') }}">Cancel</a></div>

<form class="room-editor" data-room-editor method="post" action="{{ $classroom->exists ? route('classrooms.update', $classroom) : route('classrooms.store') }}">
    @csrf @if($classroom->exists) @method('PUT') @endif
    <div class="room-editor-layout">
    <div class="room-editor-main">
    <section class="room-editor-card" aria-labelledby="room-details-heading">
    <div class="room-section-heading"><span class="room-step">01</span><div><h2 id="room-details-heading">Room details</h2><p>Give your classroom a name and a place.</p></div><span class="room-section-tag">Required</span></div>
    <div class="room-fields form-section-grid">
        <div><label class="form-label" for="room_number">Room number <span aria-hidden="true">*</span></label><input class="form-control @error('room_number') is-invalid @enderror" id="room_number" name="room_number" value="{{ old('room_number', $classroom->room_number) }}" maxlength="30" placeholder="e.g. 7A" required aria-describedby="room-number-help"><small id="room-number-help" class="room-field-help">A unique number or code for this room.</small>@error('room_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div><label class="form-label" for="name">Room name <span aria-hidden="true">*</span></label><input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $classroom->name) }}" maxlength="100" placeholder="e.g. Grade 7 classroom" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div><label class="form-label" for="building">Building <span aria-hidden="true">*</span></label><select class="form-select @error('building') is-invalid @enderror" id="building" name="building" required>@foreach($buildings as $building)<option value="{{ $building->code }}" @selected(old('building', $classroom->building ?: 'A') === $building->code)>{{ $building->name }}</option>@endforeach<option value="__new" @selected(old('building') === '__new')>+ Create another building</option></select>@error('building')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="mt-3" data-new-building @if(old('building') !== '__new') hidden @endif><label class="form-label" for="new_building_name">New building name</label><input class="form-control" id="new_building_name" name="new_building_name" maxlength="100" placeholder="Building C" value="{{ old('new_building_name') }}">@error('new_building_name')<div class="text-danger">{{ $message }}</div>@enderror</div>
        </div>
        <div><label class="form-label" for="capacity">Student capacity <span aria-hidden="true">*</span></label><input class="form-control @error('capacity') is-invalid @enderror" id="capacity" type="number" name="capacity" min="1" max="500" value="{{ old('capacity', $classroom->capacity ?: 30) }}" required>@error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div><label class="form-label" for="status">Room status <span aria-hidden="true">*</span></label><select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required><option value="active" @selected(old('status', $classroom->status ?: 'active') === 'active')>{{ __('Active') }}</option><option value="out_of_service" @selected(old('status', $classroom->status) === 'out_of_service')>Out of service</option></select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <details class="room-notes form-wide" @if(old('notes', $classroom->notes) || $errors->has('notes')) open @endif><summary><i class="bi bi-card-text" aria-hidden="true"></i> Add notes <span>Optional</span></summary><div><div><label class="form-label" for="notes">Notes</label><textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3" maxlength="1000" placeholder="Accessibility, equipment, or maintenance information">{{ old('notes', $classroom->notes) }}</textarea>@error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror</div></div></details>
    </div>
    </section>
    @include('school.classrooms._setup-planner')
    </div>
    <aside class="room-editor-summary" aria-label="Room summary">
        <div class="room-summary-icon"><i class="bi bi-door-open" aria-hidden="true"></i></div>
        <span class="room-summary-eyebrow">Your classroom</span>
        <h2 data-room-summary-name>{{ old('name', $classroom->name) ?: 'New classroom' }}</h2>
        <p data-room-summary-number>{{ old('room_number', $classroom->room_number) ?: 'Room number not set' }}</p>
        <dl><div><dt>Building</dt><dd data-room-summary-building></dd></div><div><dt>Capacity</dt><dd data-room-summary-capacity></dd></div><div><dt>Status</dt><dd data-room-summary-status></dd></div><div><dt>Lesson setup</dt><dd data-room-summary-plan>Room only</dd></div></dl>
        <div class="room-summary-tip"><i class="bi bi-info-circle" aria-hidden="true"></i><p>One room, multiple programs. Add lessons for different classes whenever their times do not overlap.</p></div>
    </aside>
    </div>
    <div class="room-editor-actions"><span><i class="bi bi-check2-circle" aria-hidden="true"></i> You can update these details anytime.</span><div><a class="btn btn-light" href="{{ route('classrooms.index') }}">Cancel</a><button class="btn btn-primary" type="submit">{{ $classroom->exists ? 'Save changes' : 'Create classroom' }} <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></button></div></div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('js/classroom-setup.js') }}?v={{ filemtime(public_path('js/classroom-setup.js')) }}"></script>
@endpush
