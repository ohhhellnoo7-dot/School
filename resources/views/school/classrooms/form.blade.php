@extends('school.layout')
@section('title', $classroom->exists ? 'Edit classroom' : 'Add classroom')

@section('content')
<div class="page-heading"><div><div class="eyebrow">Physical classroom</div><h1>{{ $classroom->exists ? 'Edit room '.$classroom->room_number : 'Add physical classroom' }}</h1><p>A room can be reused by different class groups whenever their times do not overlap.</p></div><a class="btn btn-outline-secondary" href="{{ $classroom->exists ? route('classrooms.show', $classroom) : route('classrooms.index') }}">Cancel</a></div>

<form class="panel entity-form" method="post" action="{{ $classroom->exists ? route('classrooms.update', $classroom) : route('classrooms.store') }}">
    @csrf @if($classroom->exists) @method('PUT') @endif
    <div class="panel-header"><div><h2>Room details</h2><p>Availability is calculated from maintenance status and actual bookings.</p></div></div>
    <div class="panel-body form-section-grid">
        <div><label class="form-label" for="room_number">Room number <span aria-hidden="true">*</span></label><input class="form-control @error('room_number') is-invalid @enderror" id="room_number" name="room_number" value="{{ old('room_number', $classroom->room_number) }}" maxlength="30" placeholder="7A or E01" required>@error('room_number')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div><label class="form-label" for="name">Room name <span aria-hidden="true">*</span></label><input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $classroom->name) }}" maxlength="100" placeholder="Room 7A" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div><label class="form-label" for="capacity">Capacity <span aria-hidden="true">*</span></label><input class="form-control @error('capacity') is-invalid @enderror" id="capacity" type="number" name="capacity" min="1" max="500" value="{{ old('capacity', $classroom->capacity ?: 30) }}" required>@error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div><label class="form-label" for="status">Maintenance status <span aria-hidden="true">*</span></label><select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required><option value="active" @selected(old('status', $classroom->status ?: 'active') === 'active')>{{ __('Active') }}</option><option value="out_of_service" @selected(old('status', $classroom->status) === 'out_of_service')>Out of service</option></select>@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="form-wide"><label class="form-label" for="notes">Notes</label><textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="4" maxlength="1000" placeholder="Accessibility, equipment, or maintenance information">{{ old('notes', $classroom->notes) }}</textarea>@error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </div>
    <div class="form-footer"><a class="btn btn-light" href="{{ route('classrooms.index') }}">Cancel</a><button class="btn btn-primary" type="submit">{{ $classroom->exists ? 'Save changes' : 'Create classroom' }}</button></div>
</form>
@endsection
