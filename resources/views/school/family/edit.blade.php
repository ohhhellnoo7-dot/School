@extends('school.layout')
@section('title', __('Edit child profile'))
@section('content')
<div class="family-page">
    <a class="family-back" href="{{ route('students.family.show', $student) }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ $student->name }}</a>
    <section class="family-panel family-profile-form" aria-labelledby="child-edit-heading">
        <h1 id="child-edit-heading">{{ __('Edit child profile') }}</h1>
        <form method="post" action="{{ route('students.family.update', $student) }}" enctype="multipart/form-data" class="row g-3 mt-1">
            @csrf
            @method('PATCH')
            <div class="col-md-6">
                <label class="form-label" for="family-child-name">{{ __('Full name') }}</label>
                <input class="form-control @error('name') is-invalid @enderror" id="family-child-name" name="name" value="{{ old('name', $student->name) }}" maxlength="255" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="family-child-phone">{{ __('Phone') }}</label>
                <input class="form-control @error('phone') is-invalid @enderror" id="family-child-phone" type="tel" name="phone" value="{{ old('phone', $student->phone) }}" maxlength="30">
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="family-child-address">{{ __('Address') }}</label>
                <textarea class="form-control @error('address') is-invalid @enderror" id="family-child-address" name="address" rows="3" maxlength="2000">{{ old('address', $student->address) }}</textarea>
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="family-child-photo">{{ __('Profile photo') }}</label>
                <input class="form-control @error('photo') is-invalid @enderror" id="family-child-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" aria-describedby="family-child-photo-hint">
                <div class="form-text" id="family-child-photo-hint">{{ __('JPG, PNG, or WebP. Maximum 2 MB.') }}</div>
                @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary family-button" type="submit">{{ __('Save changes') }}</button>
                <a class="btn btn-outline-secondary" href="{{ route('students.family.show', $student) }}">{{ __('Cancel') }}</a>
            </div>
        </form>
    </section>
</div>
@endsection
