@extends('school.layout')
@section('title', 'School map')
@section('content')
<link href="{{ asset('css/checkin-receipt.css') }}?v={{ filemtime(public_path('css/checkin-receipt.css')) }}" rel="stylesheet">
<link href="{{ asset('css/school-map.css') }}?v={{ filemtime(public_path('css/school-map.css')) }}" rel="stylesheet">
<div class="school-map-workspace">
<header class="campus-map-heading">
    <div><span class="campus-map-icon"><i class="bi bi-map" aria-hidden="true"></i></span><h1>School map</h1></div>
    @if(auth()->user()->role !== 'teacher')<a class="btn btn-outline-secondary" href="{{ route('classrooms.index') }}">Classroom availability</a>@endif
</header>
<div class="campus-map-grid">
<section class="campus-map-canvas" aria-label="Google Maps">
    @if($location !== '')
        <div class="campus-map-toolbar">
            <span><i class="bi bi-geo-alt" aria-hidden="true"></i> {{ $location }}</span>
            @if(auth()->user()->role !== 'teacher')<a class="btn btn-sm btn-outline-secondary" href="https://www.google.com/maps/dir/?{{ http_build_query(['api' => 1, 'destination' => $location]) }}" target="_blank" rel="noopener noreferrer">Get directions <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a>@endif
        </div>
        <div class="campus-map-frame" @if(auth()->user()->role === 'teacher') inert aria-label="Read-only school map" @endif>
            <iframe @if(auth()->user()->role === 'teacher') tabindex="-1" @endif title="Google Maps location: {{ $location }}" src="https://www.google.com/maps?{{ http_build_query(['q' => $location, 'output' => 'embed']) }}" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    @else
        <div class="campus-map-empty"><span class="campus-map-icon"><i class="bi bi-pin-map" aria-hidden="true"></i></span><h2>Where is your campus?</h2></div>
    @endif
</section>
<section id="attendance-check-in" class="campus-check-in" aria-labelledby="scan-location-heading">
    <div class="campus-zone-heading"><span class="campus-map-icon"><i class="bi bi-crosshair" aria-hidden="true"></i></span><h2 id="scan-location-heading">Attendance zone</h2></div>
    <div data-active-location-summary>
        @if($activeLocation)
            <p class="campus-zone-name">{{ $activeLocation->name }}</p>
            <dl class="campus-zone-facts">
                <div><dt>Radius</dt><dd>{{ $activeLocation->radius_meters }} m</dd></div>
                <div><dt>Coordinates</dt><dd>{{ $activeLocation->latitude }}, {{ $activeLocation->longitude }}</dd></div>
            </dl>
        @else
            <p>No active attendance location.</p>
        @endif
    </div>
    @if(auth()->user()->role === 'teacher')
        <p class="small text-secondary">This location is set by the administrator.</p>
    @endif
    @if($activeLocation)
        @if(in_array(auth()->user()->role, ['administrator', 'teacher'], true))
        <button type="button" class="btn btn-primary campus-scan-button" data-scan-location data-active-id="{{ $activeLocation->id }}" data-check-target="{{ auth()->user()->role === 'administrator' ? 'true' : 'false' }}" data-endpoint="{{ route('school.map.scan') }}"><i class="bi bi-crosshair me-1" aria-hidden="true"></i>Start scan location</button>
        <div class="campus-scan-result" data-scan-result role="status" aria-live="polite">{{ auth()->user()->role === 'administrator' ? 'Test the zone without recording attendance.' : 'Stand inside the zone and allow precise location access.' }}</div>
        @endif
    @endif
    @if(auth()->user()->role === 'administrator')
        <a class="campus-settings-link" href="#attendance-zone-settings"><i class="bi bi-sliders" aria-hidden="true"></i> Zone settings</a>
    @endif
</section>
</div>
@if(auth()->user()->role !== 'teacher')
<section id="attendance-zone-settings" class="campus-zone-settings" aria-labelledby="zone-settings-heading">
    <h2 id="zone-settings-heading">{{ auth()->user()->role === 'administrator' ? 'Zone settings' : 'Find a location' }}</h2>
    <form method="{{ auth()->user()->role === 'administrator' ? 'post' : 'get' }}" action="{{ auth()->user()->role === 'administrator' ? route('school.map.set-location') : route('school.map') }}" class="d-flex flex-wrap align-items-end gap-3">
        @if(auth()->user()->role === 'administrator') @csrf @endif
        <div class="flex-grow-1 position-relative" data-location-history data-history-key="school-map-history-{{ auth()->id() }}" data-saved-locations="{{ $locations->map(fn ($site) => ['label' => $site->name.($site->is_active ? ' - active attendance location' : ' - history only'), 'value' => $site->latitude.','.$site->longitude])->values()->toJson() }}">
            <label class="form-label" for="map-location">{{ auth()->user()->role === 'administrator' ? 'Attendance location coordinates' : 'Address or coordinates' }}</label>
            <input id="map-location" name="location" class="form-control" value="{{ $location }}" placeholder="{{ auth()->user()->role === 'administrator' ? 'Latitude, longitude' : 'Address or coordinates' }}" maxlength="300" autocomplete="off" aria-expanded="false" aria-controls="map-location-history" required>
            <div id="map-location-history" class="position-absolute start-0 end-0 bg-body border rounded-3 shadow p-2" style="z-index:1050;max-height:300px;overflow-y:auto" aria-label="Location history" hidden></div>
        </div>
        @if($selectedLocation)<input type="hidden" name="site" value="{{ $selectedLocation->id }}">@endif
        <button class="btn btn-primary" type="submit"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ auth()->user()->role === 'administrator' ? 'Set attendance location' : 'Show on map' }}</button>
    </form>
    @if(auth()->user()->role === 'administrator')
    <p class="small text-secondary mt-2">Saved locations keep their radius. New locations use 100 m.</p>
    <details class="campus-zone-option" data-click-only @if($errors->hasAny(['name', 'latitude', 'longitude', 'radius_meters'])) open @endif>
    <summary><i class="bi bi-plus-circle" aria-hidden="true"></i> Set a custom radius</summary>
    <form method="post" action="{{ route('school.map.locations.store') }}" class="row g-3" id="school-location-form">
        @csrf
        <div class="col-md-4"><label for="school-location-name" class="form-label">Location name</label><input id="school-location-name" name="name" class="form-control" value="{{ old('name') }}" maxlength="120" placeholder="Chroy Changva campus" required></div>
        <div class="col-md-3"><label for="school-latitude" class="form-label">Latitude</label><input id="school-latitude" name="latitude" class="form-control" type="number" step="any" min="-90" max="90" value="{{ old('latitude') }}" required></div>
        <div class="col-md-3"><label for="school-longitude" class="form-label">Longitude</label><input id="school-longitude" name="longitude" class="form-control" type="number" step="any" min="-180" max="180" value="{{ old('longitude') }}" required></div>
        <div class="col-md-2"><label for="school-radius" class="form-label">Radius (metres)</label><input id="school-radius" name="radius_meters" class="form-control" type="number" min="20" max="5000" value="{{ old('radius_meters', 100) }}" required></div>
        <div class="col-12 d-flex gap-2 flex-wrap"><button class="btn btn-primary">Save and activate</button><button type="button" class="btn btn-outline-secondary" data-copy-map-coordinates>Copy coordinates</button></div>
        <p class="small mb-0" data-coordinate-status role="status" aria-live="polite"></p>
    </form>
    </details>
    <p class="small mb-0" data-remove-location-status role="status" aria-live="polite"></p>
    <details class="campus-zone-option" data-click-only data-saved-location-list><summary><i class="bi bi-clock-history" aria-hidden="true"></i> Manage saved locations</summary><ul class="list-unstyled mt-3 mb-0">@foreach($savedLocations as $site)<li data-saved-location-row data-attendance-eligible="{{ $site->is_active && ! $site->isLegacyTest() ? 'true' : 'false' }}" class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom"><span>{{ $site->name }} <strong>{{ $site->is_active ? '(active)' : '(history only)' }}</strong> @if($site->isLegacyTest())<strong class="text-danger">(blocked for attendance)</strong>@endif <small class="text-secondary">{{ $site->latitude }}, {{ $site->longitude }} / {{ $site->radius_meters }} m</small></span><div class="d-flex flex-wrap gap-2">
        @if(! $site->is_active && ! $site->isLegacyTest())
        <form method="post" action="{{ route('school.map.locations.activate', $site) }}">@csrf<button class="btn btn-sm btn-outline-primary">Use for attendance</button></form>
        @endif
        <details data-click-only><summary class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil" aria-hidden="true"></i> Edit radius</summary><form method="post" action="{{ route('school.map.locations.radius', $site) }}" class="border rounded-3 p-3 mt-2">@csrf @method('PATCH')<label class="form-label" for="radius-{{ $site->id }}">Radius (metres) for {{ $site->name }}</label><div class="d-flex gap-2"><input id="radius-{{ $site->id }}" name="radius_meters" class="form-control" type="number" min="20" max="5000" step="1" value="{{ $site->radius_meters }}" required><button class="btn btn-primary btn-sm" type="submit">Save radius</button></div><small class="text-secondary">20–5,000 metres. Applies to new scans.</small></form></details>
        <form method="post" action="{{ route('school.map.locations.destroy', $site) }}" data-remove-location data-confirm="Remove this attendance location?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Remove</button></form></div></li>@endforeach</ul></details>
    @endif
</section>
@endif
</div>
@endsection

@push('scripts')
@if(!in_array(auth()->user()->role, ['administrator', 'teacher'], true))
<script src="{{ asset('js/school-map.js') }}?v={{ filemtime(public_path('js/school-map.js')) }}" defer></script>
@endif
@endpush
