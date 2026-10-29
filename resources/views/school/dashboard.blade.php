@extends('school.layout')
@section('title', auth()->user()->role === 'student' ? 'My school day' : 'Overview')
@section('content')
<link href="{{ asset('css/dashboard-report.css') }}?v={{ filemtime(public_path('css/dashboard-report.css')) }}" rel="stylesheet">
<div class="dashboard-sync d-flex flex-wrap align-items-center justify-content-end gap-2 mb-3">
    <span id="dashboard-sync-status" class="small text-secondary" role="status" aria-live="polite">Live data ? refreshes every 30 seconds</span>
    <button type="button" id="dashboard-refresh" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
</div>
<div id="dashboard-content" data-url="{{ route('dashboard.data') }}" aria-busy="false">
    @include('school.partials.dashboard-content')
</div>
@php($dashboardInitial = ['charts' => $charts, 'showRevenue' => in_array(auth()->user()->role, ['administrator', 'parent'])])
<script type="application/json" id="dashboard-initial-data">@json($dashboardInitial)</script>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chart/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/dashboard.js') }}?v={{ filemtime(public_path('js/dashboard.js')) }}" defer></script>
@endpush
