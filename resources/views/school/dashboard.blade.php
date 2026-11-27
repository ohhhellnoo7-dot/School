@extends('school.layout')
@section('title', auth()->user()->role === 'student' ? 'My school day' : 'Overview')
@if(auth()->user()->role === 'teacher')
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/teaching-attendance.css') }}?v={{ filemtime(public_path('css/teaching-attendance.css')) }}">
    @endpush
@endif
@section('content')
<div id="dashboard-content" data-url="{{ route('dashboard.data') }}" aria-busy="false">
    @include('school.partials.dashboard-content')
</div>
@if(auth()->user()->role === 'teacher')
    @include('school.attendance.teacher-excuse-form')
    <div class="modal fade" id="request-money-modal" tabindex="-1" aria-labelledby="request-money-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable request-money-dialog">
            <form class="modal-content" method="post" action="{{ route('dashboard.advance-requests.store') }}" data-request-money-form data-early-salary-amount="{{ \App\Support\Money::decimal($earlySalaryCents) }}" data-plan-minimum-cents="{{ \App\Services\EmployeeSalaryAdvances::PLAN_MINIMUM_CENTS }}" @if($errors->hasAny(['request_type', 'requested_amount', 'repayment_months'])) data-reopen-request @endif>
                @csrf
                <input type="hidden" name="submission_key" value="{{ \Illuminate\Support\Str::uuid() }}">
                <div class="request-money-header">
                    <span class="request-money-emblem" aria-hidden="true"><i class="bi bi-hand-index-thumb"></i><i class="bi bi-currency-dollar"></i></span>
                    <div class="request-money-heading">
                        <h2 class="modal-title" id="request-money-title">{{ __('Request Money') }}</h2>
                        <p>{{ __('Create a money request to the administration.') }}</p>
                    </div>
                    <span class="request-money-leaves" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                    <img class="request-money-wallet" src="{{ asset('images/request-money-wallet.png') }}" alt="" width="400" height="400">
                    <button type="button" class="request-money-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <div class="request-money-body modal-body">
                    <label class="form-label" for="money-request-type">{{ __('Request type') }}</label>
                    <select class="form-select mb-3" id="money-request-type" name="request_type">
                        <option value="advance">{{ __('Salary advance') }}</option>
                        <option value="early_salary" @selected(old('request_type') === 'early_salary')>{{ __('Early salary') }}</option>
                    </select>
                    <p class="small text-secondary" data-early-salary-note hidden>
                        {{ $earlySalaryCents > 0
                            ? __('Request the available salary due on :date. After approval and payout, the amount is credited against that salary.', ['date' => $earlySalaryMonth->translatedFormat('j F Y')])
                            : __('No early salary is available for :month.', ['month' => $earlySalaryMonth->translatedFormat('F Y')]) }}
                    </p>
                    @foreach(['request_type', 'requested_amount', 'repayment_months'] as $field)
                        @error($field)<p class="small text-danger">{{ $message }}</p>@enderror
                    @endforeach
                    <label class="form-label" for="requested-amount"><i class="bi bi-coin" aria-hidden="true"></i>{{ __('Amount (USD)') }}</label>
                    <div class="request-money-field">
                        <span aria-hidden="true">$</span>
                        <input id="requested-amount" name="requested_amount" type="number" inputmode="decimal" min="0.01" max="9999999999.99" step="0.01" value="{{ old('requested_amount') }}" required>
                    </div>
                    <fieldset class="mt-3 mb-1" data-repayment-plan hidden disabled>
                        <legend class="h6">{{ __('Repayment plan') }}</legend>
                        <label class="form-label" for="repayment-months">{{ __('Repay over') }}</label>
                        <select class="form-select" id="repayment-months" name="repayment_months" required>
                            <option value="">{{ __('Choose repayment duration') }}</option>
                            <option value="1" @selected(old('repayment_months') == 1)>{{ __('This month only') }}</option>
                            @for($months = 2; $months <= \App\Services\EmployeeSalaryAdvances::PLAN_MAX_MONTHS; $months++)
                                <option value="{{ $months }}" @selected(old('repayment_months') == $months)>{{ __(':count months', ['count' => $months]) }}</option>
                            @endfor
                        </select>
                        <p class="small mt-2 mb-0" aria-live="polite" data-repayment-summary
                            data-single="{{ __(':amount from the first unpaid salary month after payout.') }}"
                            data-installments="{{ __(':amount per month for :months months.') }}"
                            data-final="{{ __('The final installment is :amount.') }}"></p>
                        <p class="small text-secondary mt-1 mb-0">{{ __('If a salary cannot cover an installment, the unpaid balance carries forward.') }}</p>
                    </fieldset>
                </div>
                <div class="request-money-actions">
                    <a class="btn request-money-history" href="{{ route('dashboard.advance-requests.history') }}"><i class="bi bi-clock-history" aria-hidden="true"></i>{{ __('Request History') }}</a>
                    <button class="btn request-money-submit" type="submit"><i class="bi bi-send" aria-hidden="true"></i>{{ __('Send Request') }}</button>
                </div>
                <div class="request-money-landscape" aria-hidden="true"></div>
            </form>
        </div>
    </div>
@endif
<div class="dashboard-sync d-flex flex-wrap align-items-center justify-content-end gap-2 mt-3">
    <span id="dashboard-sync-status" class="small text-secondary" role="status" aria-live="polite">Live data &middot; refreshes every 30 seconds</span>
    <button type="button" id="dashboard-refresh" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
</div>
@php($dashboardInitial = ['charts' => $charts, 'showRevenue' => auth()->user()->role === 'administrator'])
<script type="application/json" id="dashboard-initial-data">@json($dashboardInitial)</script>
@endsection
@push('scripts')
<script src="{{ asset('vendor/chart/chart.umd.min.js') }}"></script>
@include('school.partials.khmer-calendar-scripts')
<script src="{{ asset('js/dashboard.js') }}?v={{ filemtime(public_path('js/dashboard.js')) }}" defer></script>
@if(auth()->user()->role === 'teacher')
    <script src="{{ asset('js/request-money.js') }}?v={{ filemtime(public_path('js/request-money.js')) }}" defer></script>
@endif
@endpush
