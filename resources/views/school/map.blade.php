@extends('school.layout')
@section('title', 'School map')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">Campus location</div><h1>School map</h1><p>Find the campus, verify your location and check in for today.</p></div>
    @if(auth()->user()->role !== 'teacher')<a class="btn btn-outline-secondary" href="{{ route('classrooms.index') }}">Classroom availability</a>@endif
</div>
@if(auth()->user()->role === 'teacher')
<section class="panel p-4 mb-4" aria-label="School attendance location">
    <h2 class="h6">School attendance location</h2>
    <p class="mb-1">{{ $activeLocation?->name ?? 'No active attendance location' }}</p>
    @if($activeLocation)<p class="text-secondary mb-2">{{ $location }} &middot; {{ $activeLocation->radius_meters }} m radius</p>@endif
    <p class="small text-secondary mb-0">This location is set by the administrator. You can only scan to check in.</p>
</section>
@else
<section class="panel p-4 mb-4" aria-label="Find a location">
    <form method="{{ auth()->user()->role === 'administrator' ? 'post' : 'get' }}" action="{{ auth()->user()->role === 'administrator' ? route('school.map.set-location') : route('school.map') }}" class="d-flex flex-wrap align-items-end gap-3">
        @if(auth()->user()->role === 'administrator') @csrf @endif
        <div class="flex-grow-1 position-relative" data-location-history data-history-key="school-map-history-{{ auth()->id() }}" data-saved-locations="{{ $locations->map(fn ($site) => ['label' => $site->name.($site->is_active ? ' - active attendance location' : ' - history only'), 'value' => $site->latitude.','.$site->longitude])->values()->toJson() }}">
            <label class="form-label" for="map-location">{{ auth()->user()->role === 'administrator' ? 'Attendance location coordinates' : 'Address or coordinates' }}</label>
            <input id="map-location" name="location" class="form-control" value="{{ $location }}" placeholder="Enter an address or coordinates, or choose from history" maxlength="300" autocomplete="off" aria-expanded="false" aria-controls="map-location-history" required>
            <div id="map-location-history" class="position-absolute start-0 end-0 bg-body border rounded-3 shadow p-2" style="z-index:1050;max-height:300px;overflow-y:auto" aria-label="Location history" hidden></div>
        </div>
        @if($selectedLocation)<input type="hidden" name="site" value="{{ $selectedLocation->id }}">@endif
        <button class="btn btn-primary" type="submit"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ auth()->user()->role === 'administrator' ? 'Set attendance location' : 'Show on map' }}</button>
    </form>
    <p class="small text-secondary mt-2 mb-0">@if(auth()->user()->role === 'administrator') Enter latitude, longitude or choose history, then click Set attendance location. This replaces the active zone. Existing locations keep their saved radius; new locations use 100 m. @else This previews the map. Attendance uses the location set by the administrator. @endif</p>
</section>
@endif
@if(auth()->user()->role === 'administrator')
<section class="panel p-4 mb-4" aria-labelledby="save-location-heading">
    <h2 id="save-location-heading" class="h5">Set a school attendance location</h2>
    <p class="text-secondary small">Set any location you choose using its latitude and longitude. Set the radius to the area where check-in is allowed (for example, 100 metres). Saving activates this location and replaces the previous attendance zone. Older locations remain in history but cannot allow check-in.</p>
    <form method="post" action="{{ route('school.map.locations.store') }}" class="row g-3" id="school-location-form">
        @csrf
        <div class="col-md-4"><label for="school-location-name" class="form-label">Location name</label><input id="school-location-name" name="name" class="form-control" value="{{ old('name') }}" maxlength="120" placeholder="Chroy Changva campus" required></div>
        <div class="col-md-3"><label for="school-latitude" class="form-label">Latitude</label><input id="school-latitude" name="latitude" class="form-control" type="number" step="any" min="-90" max="90" value="{{ old('latitude') }}" required></div>
        <div class="col-md-3"><label for="school-longitude" class="form-label">Longitude</label><input id="school-longitude" name="longitude" class="form-control" type="number" step="any" min="-180" max="180" value="{{ old('longitude') }}" required></div>
        <div class="col-md-2"><label for="school-radius" class="form-label">Radius (metres)</label><input id="school-radius" name="radius_meters" class="form-control" type="number" min="20" max="5000" value="{{ old('radius_meters', 100) }}" required></div>
        <div class="col-12 d-flex gap-2 flex-wrap"><button class="btn btn-primary">Save and activate attendance location</button><button type="button" class="btn btn-outline-secondary" data-copy-map-coordinates>Copy coordinates from map box</button></div>
        <p class="small mb-0" data-coordinate-status role="status" aria-live="polite"></p>
    </form>
</section>
@endif
<section id="attendance-check-in" class="panel p-4 mb-4" aria-labelledby="scan-location-heading">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><h2 id="scan-location-heading" class="h5">School location check-in</h2><p class="small text-secondary mb-0">Check-in succeeds only inside the active attendance zone. Your device location and its reported accuracy must fit within the allowed radius. Allow precise location access on your device.</p></div></div>
    <p class="small" data-active-location-summary>
        @if($activeLocation)
        Active attendance location: <strong>{{ $activeLocation->name }}</strong> &middot; {{ $activeLocation->latitude }}, {{ $activeLocation->longitude }} &middot; {{ $activeLocation->radius_meters }} m radius
        @else
        No active attendance location. An administrator must save or activate one before scanning.
        @endif
    </p>
    @if($activeLocation)
    @if(in_array(auth()->user()->role, ['administrator', 'teacher'], true))
    <button type="button" class="btn btn-primary mb-3" data-scan-location data-active-id="{{ $activeLocation->id }}" data-check-target="{{ auth()->user()->role === 'administrator' ? 'true' : 'false' }}" data-endpoint="{{ route('school.map.scan') }}"><i class="bi bi-crosshair me-1" aria-hidden="true"></i>Start scan location</button>
    @endif
    <div class="p-3 border rounded-3" data-scan-result role="status" aria-live="polite">Start scanning to check your device location against the active attendance zone.@if(auth()->user()->role === 'administrator') Administrator scans are tests; teacher scans record attendance.@endif</div>
    @else
    <p class="text-secondary mb-0">No attendance locations saved yet. An administrator must set the school location before teachers can scan.</p>
    @endif
    @if(auth()->user()->role === 'administrator')
    <p class="small mt-3 mb-0" data-remove-location-status role="status" aria-live="polite"></p>
    <details class="mt-3" data-click-only data-saved-location-list><summary class="small">Manage saved locations</summary><ul class="list-unstyled mt-3 mb-0">@foreach($savedLocations as $site)<li data-saved-location-row data-attendance-eligible="{{ $site->is_active && ! $site->isLegacyTest() ? 'true' : 'false' }}" class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom"><span>{{ $site->name }} <strong>{{ $site->is_active ? '(active)' : '(history only)' }}</strong> @if($site->isLegacyTest())<strong class="text-danger">(blocked for attendance)</strong>@endif <small class="text-secondary">{{ $site->latitude }}, {{ $site->longitude }} / {{ $site->radius_meters }} m</small></span><div class="d-flex gap-2">
        @if(! $site->is_active && ! $site->isLegacyTest())
        <form method="post" action="{{ route('school.map.locations.activate', $site) }}">@csrf<button class="btn btn-sm btn-outline-primary">Use for attendance</button></form>
        @endif
        <form method="post" action="{{ route('school.map.locations.destroy', $site) }}" data-remove-location data-confirm="Remove this attendance location?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form></div></li>@endforeach</ul></details>
    @endif
</section>
<section class="panel" aria-label="Google Maps">
    @if($location !== '')
        <div class="panel-header"><div><h2><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $location }}</h2><p>Explore the surrounding area with Google Maps.</p></div>@if(auth()->user()->role !== 'teacher')<a class="btn btn-outline-secondary" href="https://www.google.com/maps/dir/?{{ http_build_query(['api' => 1, 'destination' => $location]) }}" target="_blank" rel="noopener noreferrer">Get directions <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a>@endif</div>
        <div @if(auth()->user()->role === 'teacher') inert aria-label="Read-only school map" @endif>
        <iframe @if(auth()->user()->role === 'teacher') tabindex="-1" @endif title="Google Maps location: {{ $location }}" src="https://www.google.com/maps?{{ http_build_query(['q' => $location, 'output' => 'embed']) }}" width="100%" height="560" style="display:block;border:0;max-height:70vh;min-height:320px" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    @else
        <div class="empty-state"><i class="bi bi-map" aria-hidden="true"></i><h2 class="h5">Where is your campus?</h2><p class="mb-0">Enter the school address above to display its location.</p></div>
    @endif
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/school-map.js') }}?v={{ filemtime(public_path('js/school-map.js')) }}" defer></script>
@endpush
