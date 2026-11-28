@extends('school.layout')
@section('title', __('My Children'))
@section('content')
@php
    $home = route('students.index', $guardian->role === 'parent' ? [] : ['my_children' => 1]);
    $profile = route('students.family.show', $student);
    $tabs = ['overview' => ['Overview', 'person'], 'attendance' => ['Attendance', 'calendar-check'], 'timetable' => ['Timetable', 'calendar3'], 'results' => ['Academic Results', 'bar-chart'], 'fees' => ['School Fees', 'receipt']];
    $latestInvoice = $tab === 'fees' ? $fees->sortByDesc('id')->first() : null;
    $payments = $tab === 'fees' ? $fees->flatMap->payments->sortBy([['paid_on', 'desc'], ['id', 'desc']]) : collect();
    $latestReceipt = $payments->first();
@endphp
<div @class(['family-page', 'family-timetable-page' => $tab === 'timetable', 'family-fees-page' => $tab === 'fees'])>
    <header class="family-profile-header">
        @include('school.partials.avatar', ['person' => $student, 'size' => 80])
        <div>
            <h1>{{ $student->name }}</h1>
            <p>{{ $student->student_number }}</p>
            <div class="family-edit-link d-flex flex-wrap gap-2">
                <a class="family-pill family-action-link" href="{{ route('students.family.edit', $student) }}"><i class="bi bi-pencil" aria-hidden="true"></i> {{ __('Edit Profile') }}</a>
                @if($latestInvoice)
                    <a class="family-pill family-action-link" href="{{ route('finance.invoice', ['fee' => $latestInvoice, 'family' => 1]) }}">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i> {{ __('Latest Invoice') }}
                    </a>
                    @if($latestReceipt)
                        <a class="family-pill family-action-link" href="{{ route('finance.receipt', ['payment' => $latestReceipt, 'family' => 1]) }}">
                            <i class="bi bi-receipt" aria-hidden="true"></i> {{ __('Latest Receipt') }}
                        </a>
                    @endif
                @endif
            </div>
            <a class="family-back d-inline-flex align-items-center gap-1 mt-3" href="{{ $home }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ __('My Children') }}</a>
        </div>
        @if($siblings->count() > 1)
            <details class="family-switch">
                <summary class="btn family-button">{{ __('Switch child') }} <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                <ul class="family-switch-list">
                    @foreach($siblings as $sibling)
                        <li><a href="{{ route('students.family.show', [$sibling, 'tab' => $tab]) }}" @if($sibling->is($student)) aria-current="page" @endif>{{ $sibling->name }}</a></li>
                    @endforeach
                </ul>
            </details>
        @endif
    </header>
    <nav class="family-tabs" aria-label="{{ __('Child profile sections') }}">
        @foreach($tabs as $key => [$label, $icon])
            <a href="{{ route('students.family.show', [$student, 'tab' => $key]) }}" @if($tab === $key) aria-current="page" @endif data-family-navigation><i class="bi bi-{{ $icon }}" aria-hidden="true"></i>{{ __($label) }}</a>
        @endforeach
    </nav>
    <div class="family-skeleton family-panel" data-family-skeleton hidden role="status" aria-label="{{ __('Loading child profile') }}"><span></span><span></span><span></span></div>
    <section @class(['family-panel', 'family-tab-panel', 'family-timetable-panel' => $tab === 'timetable']) data-family-content aria-labelledby="family-tab-heading">
        @if(!in_array($tab, ['timetable', 'fees'], true))
            <h2 id="family-tab-heading">{{ __($tabs[$tab][0]) }}</h2>
        @endif
        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif
        @include('school.family.tabs.'.$tab)
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/family-dashboard.js') }}?v={{ filemtime(public_path('js/family-dashboard.js')) }}" defer></script>
@endpush
