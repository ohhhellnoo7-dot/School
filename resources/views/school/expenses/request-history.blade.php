@extends('school.layout')
@section('title', __('Salary Advance History'))

@push('styles')
<link href="{{ asset('css/expense-history.css') }}?v={{ filemtime(public_path('css/expense-history.css')) }}" rel="stylesheet">
@endpush

@section('content')
<div class="expense-overview advance-history">
    <header class="expense-overview-hero">
        <div class="expense-overview-heading">
            <a href="{{ route('dashboard') }}" class="expense-overview-back"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ __('Dashboard') }}</a>
            <div class="expense-overview-title">
                <i class="bi bi-wallet-fill" aria-hidden="true"></i>
                <div>
                    <h1>{{ __('Salary Advance History') }}</h1>
                    <p>{{ __('A record of all your salary advance requests and their status.') }}</p>
                </div>
            </div>
        </div>
        <div class="advance-history-art" aria-hidden="true">
            <img class="advance-history-calendar" src="{{ asset('images/payroll-calendar.png') }}" alt="">
            <img class="advance-history-wallet" src="{{ asset('images/request-money-wallet.png') }}" alt="" width="400" height="400">
        </div>
    </header>

    <form method="get" action="{{ route('dashboard.advance-requests.history') }}" class="expense-overview-filters advance-history-filters panel">
        @foreach([
            ['date', __('Request date'), 'date'],
            ['month', __('Request month'), 'month'],
            ['year', __('Request year'), 'text'],
        ] as [$key, $label, $type])
            <div class="advance-history-filter">
                <label class="form-label" for="request-{{ $key }}"><i class="bi bi-calendar3" aria-hidden="true"></i> {{ $label }}</label>
                <input class="form-control" id="request-{{ $key }}" name="{{ $key }}" type="{{ $type }}" value="{{ $filters[$key] ?? '' }}"
                    @if($key === 'year') inputmode="numeric" pattern="[0-9]{4}" maxlength="4" @endif>
            </div>
        @endforeach
        <div class="expense-overview-actions">
            <button class="btn btn-primary expense-overview-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> {{ __('Apply') }}</button>
            <a class="btn expense-overview-secondary" href="{{ route('dashboard.advance-requests.history') }}"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> {{ __('Clear') }}</a>
        </div>
    </form>

    <section class="expense-overview-history panel" aria-label="{{ __('Salary Advance History') }}">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Requested on') }}</th>
                        <th scope="col">{{ __('Amount (USD)') }}</th>
                        <th scope="col">{{ __('Status') }}</th>
                        <th scope="col">{{ __('Paid on') }}</th>
                        <th scope="col">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $advance)
                        @php
                            [$status, $statusIcon, $statusClass] = match (true) {
                                (bool) $advance->expense => [__('Paid'), 'check-circle-fill', 'paid'],
                                (bool) $advance->cancelled_at => [__('Cancelled'), 'x-circle-fill', 'cancelled'],
                                (bool) $advance->rejected_at => [__('Rejected'), 'slash-circle-fill', 'rejected'],
                                (bool) $advance->approved_at => [__('Approved'), 'check-circle', 'pending'],
                                default => [__('Pending'), 'clock-fill', 'pending'],
                            };
                        @endphp
                        <tr>
                            <td class="text-nowrap"><i class="bi bi-calendar3 expense-overview-row-icon" aria-hidden="true"></i>{{ $advance->created_at->translatedFormat('M j, Y') }}</td>
                            <td>
                                <span class="expense-overview-chip expense-overview-amount">${{ \App\Support\Money::display($advance->amount_cents) }}</span>
                                @if($advance->salary_month)
                                    <small class="d-block">{{ __('Early salary for :month', ['month' => $advance->salary_month->translatedFormat('F Y')]) }}</small>
                                @else
                                    @include('school.expenses._repayment-plan', ['amountCents' => $advance->amount_cents, 'months' => $advance->repayment_months])
                                @endif
                            </td>
                            <td><span class="advance-history-status advance-history-status--{{ $statusClass }}"><i class="bi bi-{{ $statusIcon }}" aria-hidden="true"></i>{{ $status }}</span></td>
                            <td class="text-nowrap">
                                @if($advance->expense)
                                    <i class="bi bi-calendar3 expense-overview-row-icon" aria-hidden="true"></i>{{ $advance->expense->spent_on->translatedFormat('M j, Y') }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn expense-overview-secondary advance-history-view" type="button" data-bs-toggle="collapse" data-bs-target="#advance-details-{{ $advance->id }}" aria-expanded="false" aria-controls="advance-details-{{ $advance->id }}" aria-label="{{ __('View request details') }}" title="{{ __('View request details') }}"><i class="bi bi-eye" aria-hidden="true"></i></button>
                                    @if($statusClass === 'pending')
                                        <form method="post" action="{{ route('dashboard.advance-requests.cancel', $advance) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">{{ __('Cancel') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr class="advance-history-detail-row">
                            <td colspan="5" class="p-0 border-0">
                                <div class="collapse" id="advance-details-{{ $advance->id }}">
                                    <div class="advance-history-details">
                                        <span><strong>{{ __('Requested on') }}</strong> {{ $advance->created_at->translatedFormat('M j, Y, H:i') }}</span>
                                        @if($advance->cancelled_at)
                                            <span><strong>{{ __('Cancelled on') }}</strong> {{ $advance->cancelled_at->translatedFormat('M j, Y, H:i') }}</span>
                                        @elseif($advance->rejected_at)
                                            <span><strong>{{ __('Rejected on') }}</strong> {{ $advance->rejected_at->translatedFormat('M j, Y, H:i') }}</span>
                                        @elseif($advance->expense)
                                            <span><strong>{{ __('Paid on') }}</strong> {{ $advance->expense->spent_on->translatedFormat('M j, Y') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">{{ __('No salary advance requests match these filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($history->hasPages())
            <div class="mt-3">{{ $history->links() }}</div>
        @endif
    </section>
</div>
@endsection
