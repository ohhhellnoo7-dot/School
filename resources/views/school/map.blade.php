@extends('school.layout')
@section('title', 'School map')
@section('content')
<div class="page-heading">
    <div><div class="eyebrow">Campus location</div><h1>School map</h1><p>Find the campus and plan your journey.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('classrooms.index') }}">Classroom availability</a>
</div>
<section class="panel p-4 mb-4" aria-label="Find a location">
    <form method="get" action="{{ route('school.map') }}" class="d-flex flex-wrap align-items-end gap-3">
        <div class="flex-grow-1"><label class="form-label" for="map-location">Address or coordinates</label><input id="map-location" name="location" class="form-control" value="{{ $location }}" placeholder="Enter the school address or latitude, longitude" maxlength="300" required></div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>Show on map</button>
    </form>
</section>
<section class="panel" aria-label="Google Maps">
    @if($location !== '')
        <div class="panel-header"><div><h2><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ $location }}</h2><p>Explore the surrounding area with Google Maps.</p></div><a class="btn btn-outline-secondary" href="https://www.google.com/maps/dir/?{{ http_build_query(['api' => 1, 'destination' => $location]) }}" target="_blank" rel="noopener noreferrer">Get directions <i class="bi bi-box-arrow-up-right ms-1" aria-hidden="true"></i></a></div>
        <iframe title="Google Maps location: {{ $location }}" src="https://www.google.com/maps?{{ http_build_query(['q' => $location, 'output' => 'embed']) }}" width="100%" height="560" style="display:block;border:0;max-height:70vh;min-height:320px" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
    @else
        <div class="empty-state"><i class="bi bi-map" aria-hidden="true"></i><h2 class="h5">Where is your campus?</h2><p class="mb-0">Enter the school address above to display its location.</p></div>
    @endif
</section>
@endsection
