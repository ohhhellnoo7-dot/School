@extends('school.layout')

@php
    $studentSettings = $user->role === 'student';
@endphp
@section('title', $studentSettings ? __('Account Settings') : __('Edit profile'))

@if($studentSettings)
    @push('styles')
        <link href="{{ asset('css/student-workspace.css') }}?v={{ filemtime(public_path('css/student-workspace.css')) }}" rel="stylesheet">
    @endpush
@endif

@section('content')
@php
    $accountPhotoUrl = $user->account_photo_url;
    $fields = [
        ['name', 'Name', 'text', 'person', 'name', 255],
        ['email', 'Email', 'email', 'envelope', 'email', 255],
        ['phone', 'Phone', 'tel', 'telephone', 'tel', 30],
        ['address', 'Address', 'textarea', 'geo-alt', 'street-address', 1000],
    ];
@endphp

<div class="{{ $studentSettings ? 'student-timetable student-invoice account-settings' : '' }}">
    @if($studentSettings)
        <header class="student-timetable-hero as-hero">
            <span class="st-calendar-icon"><i class="bi bi-gear" aria-hidden="true"></i></span>
            <div>
                <h1>{{ __('Account Settings') }}</h1>
                <p>{{ __('Update your personal details and profile photo.') }}</p>
            </div>
        </header>
    @else
        <div class="page-heading"><div><h1>{{ __('Your profile') }}</h1><p>{{ __('Update your personal details and choose a profile photo.') }}</p></div></div>
    @endif

    <form action="{{ route('profile.update') }}" method="post" enctype="multipart/form-data"
        class="{{ $studentSettings ? 'as-form' : 'panel' }}" @unless($studentSettings) style="max-width:800px" @endunless>
        @csrf
        @method('PUT')
        @unless($studentSettings)
            <div class="panel-header"><h2>{{ __('Personal information') }}</h2><span class="status-badge">{{ __(ucfirst($user->role)) }}</span></div>
            <div class="panel-body">
        @endunless

        <section class="{{ $studentSettings ? 'si-card si-panel as-photo' : 'd-flex align-items-center gap-3 flex-wrap my-3' }}" @if($studentSettings) aria-labelledby="as-photo-title" @endif>
            @if($studentSettings)
                <h2 id="as-photo-title"><i class="bi bi-camera" aria-hidden="true"></i>{{ __('Profile Photo') }}</h2>
            @endif
            <div class="{{ $studentSettings ? 'as-portrait' : '' }}">
                <div class="user-avatar profile-preview" id="profile-initial" @if($accountPhotoUrl) hidden @endif>{{ mb_substr($user->name, 0, 1) }}</div>
                <img class="user-avatar profile-preview" id="profile-preview" @if($accountPhotoUrl) src="{{ $accountPhotoUrl }}" @else hidden @endif alt="{{ __('Your profile photo') }}">
            </div>
            @if($studentSettings)
                <strong class="as-name">{{ $user->name }}</strong>
                <span class="as-role">{{ __(ucfirst($user->role)) }}</span>
            @endif
            <div class="as-upload">
                <label for="photo" class="form-label">{{ __('Choose profile photo') }}</label>
                <input type="file" class="form-control @error('photo') is-invalid @enderror" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-help photo-error @error('photo') photo-server-error @enderror">
                <small id="photo-help">{{ __('JPG, PNG or WebP. Maximum 2 MB.') }}</small>
                @error('photo')<div class="text-danger" id="photo-server-error">{{ $message }}</div>@enderror
                <div id="photo-error" class="text-danger" role="alert"></div>
                @if($user->profile_photo_path)
                    <button type="submit" form="remove-profile-photo" class="btn btn-outline-secondary btn-sm mt-2">{{ __('Remove profile photo') }}</button>
                @endif
            </div>
        </section>

        <section class="{{ $studentSettings ? 'si-card si-panel as-details' : '' }}" @if($studentSettings) aria-labelledby="as-details-title" @endif>
            @if($studentSettings)
                <h2 id="as-details-title"><i class="bi bi-person-vcard" aria-hidden="true"></i>{{ __('Personal Information') }}</h2>
            @endif
            <div class="{{ $studentSettings ? 'as-fields' : 'row g-3' }}">
                @foreach($fields as [$field, $label, $type, $icon, $autocomplete, $limit])
                    <div class="{{ $field === 'address' ? 'col-12 as-address' : 'col-sm-6' }}">
                        <label class="form-label" for="{{ $field }}">{{ __($label) }}</label>
                        <div class="as-control">
                            @if($studentSettings)<i class="bi bi-{{ $icon }}" aria-hidden="true"></i>@endif
                            @if($type === 'textarea')
                                <textarea class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" rows="3" maxlength="{{ $limit }}" autocomplete="{{ $autocomplete }}" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>{{ old($field, $user->$field) }}</textarea>
                            @else
                                <input class="form-control @error($field) is-invalid @enderror" type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $user->$field) }}" @required(in_array($field, ['name', 'email'], true)) maxlength="{{ $limit }}" autocomplete="{{ $autocomplete }}" @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                            @endif
                        </div>
                        @error($field)<div class="text-danger mt-1" id="{{ $field }}-error">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </div>
            <div class="{{ $studentSettings ? 'as-actions' : 'd-flex gap-2 mt-4' }}">
                <button class="btn btn-primary" type="submit">@if($studentSettings)<i class="bi bi-check2" aria-hidden="true"></i>@endif {{ __('Save changes') }}</button>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
            </div>
        </section>
        @unless($studentSettings)</div>@endunless
    </form>
    @if($user->profile_photo_path)
        <form id="remove-profile-photo" action="{{ route('profile.photo.destroy') }}" method="post">
            @csrf
            @method('DELETE')
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('photo');
    const preview = document.getElementById('profile-preview');
    const initial = document.getElementById('profile-initial');
    const feedback = document.getElementById('photo-error');
    const original = preview.getAttribute('src');
    let previewUrl;

    input.addEventListener('change', () => {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = null;
        }

        const file = input.files[0];
        const invalid = file && (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024);
        feedback.textContent = invalid ? @js(__('Choose a JPG, PNG or WebP image up to 2 MB.')) : '';
        if (invalid) input.value = '';

        if (file && !invalid) {
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
        } else if (original) {
            preview.src = original;
        } else {
            preview.removeAttribute('src');
        }

        preview.hidden = !(previewUrl || original);
        initial.hidden = !preview.hidden;
    });
})();
</script>
@endpush
