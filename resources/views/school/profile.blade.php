@extends('school.layout')
@section('title', 'Edit profile')
@section('content')
<div class="page-heading"><div><h1>Your profile</h1><p>Update your personal details and choose a profile photo.</p></div></div>
<form action="{{ route('profile.update') }}" method="post" enctype="multipart/form-data" class="panel" style="max-width:800px">
    @csrf
    @method('PUT')
    <div class="panel-header"><h2>Personal information</h2><span class="status-badge">{{ ucfirst($user->role) }}</span></div>
    <div class="panel-body">
        <div class="d-flex align-items-center gap-3 flex-wrap my-3">
            <div class="user-avatar profile-preview" id="profile-initial" @if($user->profile_photo_path) hidden @endif>{{ mb_substr($user->name, 0, 1) }}</div>
            <img class="user-avatar profile-preview" id="profile-preview" @if($user->profile_photo_path) src="{{ route('profile.photo') }}" @else hidden @endif alt="{{ __('Your profile photo') }}">
            <div><label for="photo" class="form-label">Choose profile photo</label><input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-help"><small id="photo-help" class="text-muted">JPG, PNG or WebP. Maximum 2 MB.</small><div id="photo-error" class="text-danger" role="alert"></div></div>
        </div>
        <div class="row g-3">
            <div class="col-sm-6"><label class="form-label" for="name">{{ __('Name') }}</label><input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name"></div>
            <div class="col-sm-6"><label class="form-label" for="email">{{ __('Email') }}</label><input class="form-control" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email"></div>
            <div class="col-sm-6"><label class="form-label" for="phone">{{ __('Phone') }}</label><input class="form-control" type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" autocomplete="tel"></div>
            <div class="col-12"><label class="form-label" for="address">Address</label><textarea class="form-control" id="address" name="address" rows="3" maxlength="1000" autocomplete="street-address">{{ old('address', $user->address) }}</textarea></div>
        </div>
        <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit">Save changes</button><a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Cancel</a></div>
    </div>
</form>
@endsection
@push('scripts')
<script>
(() => {
    const input = document.getElementById('photo');
    const preview = document.getElementById('profile-preview');
    const initial = document.getElementById('profile-initial');
    const original = preview.getAttribute('src');
    let previewUrl;
    input.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        const file = input.files[0];
        const invalid = file && (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024);
        document.getElementById('photo-error').textContent = invalid ? 'Choose a JPG, PNG or WebP image smaller than 2 MB.' : '';
        if (invalid) input.value = '';
        if (file && !invalid) {
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.hidden = false;
            initial.hidden = true;
        } else {
            if (original) preview.src = original;
            else preview.removeAttribute('src');
            preview.hidden = !original;
            initial.hidden = !!original;
        }
    });
})();
</script>
@endpush
