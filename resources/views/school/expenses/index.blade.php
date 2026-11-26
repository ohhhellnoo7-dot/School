@extends('school.layout')
@section('title', __('Money Taken Out'))

@push('styles')
<link href="{{ asset('css/expense-history.css') }}?v={{ filemtime(public_path('css/expense-history.css')) }}" rel="stylesheet">
@endpush

@section('content')
<div class="expense-overview">
    <header class="expense-overview-hero">
        <div class="expense-overview-heading">
            <a class="expense-overview-back" href="{{ route('reports') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> {{ __('Reports') }}</a>
            <div class="expense-overview-title">
                <i class="bi bi-database-fill" aria-hidden="true"></i>
                <div>
                    <h1>{{ __('Money Taken Out') }}</h1>
                    <p>{{ __('Expense details for :month', ['month' => $month->translatedFormat('F Y')]) }} &middot; {{ $currency }} &middot; {{ __('All academic years') }}</p>
                </div>
            </div>
        </div>
        <img class="expense-overview-art" src="{{ asset('images/request-money-wallet.png') }}" alt="" width="400" height="400">
        <div class="expense-overview-actions">
            <a class="btn expense-overview-secondary" href="{{ route('reports.expenses.advances.create') }}" data-bs-toggle="modal" data-bs-target="#employee-advance-modal" aria-haspopup="dialog">
                <i class="bi bi-person" aria-hidden="true"></i> {{ __('Employee advance') }}
                <span class="badge rounded-pill text-bg-danger" data-expense-live-count="button" aria-label="{{ trans_choice(':count pending request|:count pending requests', $pendingAdvanceCount) }}" @if(!$pendingAdvanceCount) hidden @endif>{{ $pendingAdvanceCount }}</span>
            </a>
            <a class="btn btn-primary expense-overview-primary" href="{{ route('reports.expenses.create', ['currency' => $currency]) }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> {{ __('Add expense & reason') }}</a>
        </div>
    </header>

    <form method="get" class="expense-overview-filters panel">
        <div class="expense-overview-filter">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <div>
                <label for="expense-month" class="form-label">{{ __('Month') }}</label>
                <input id="expense-month" type="month" name="month" class="form-control" value="{{ $month->format('Y-m') }}" required>
            </div>
        </div>
        <div class="expense-overview-filter">
            <i class="bi bi-database" aria-hidden="true"></i>
            <div>
                <label for="expense-currency" class="form-label">{{ __('Currency') }}</label>
                <select id="expense-currency" name="currency" class="form-select">
                    @foreach(['USD', 'KHR'] as $code)
                        <option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <button class="btn btn-primary expense-overview-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i> {{ __('View expenses') }}</button>
    </form>

    @fragment('expense-live-metrics')
    <dl class="expense-overview-metrics" data-expense-live="metrics">
        @foreach([
            ['receipts', __('Net receipts'), 'file-earmark-text', 'bar-chart-fill', $total['receipts'] - $total['refunds']],
            ['expenses', __('Money taken out'), 'cash-coin', 'graph-up', $total['expenses']],
            ['remaining', __('Money remaining'), 'wallet2', 'activity', $total['remaining']],
        ] as [$key, $label, $icon, $accent, $cents])
            <div class="expense-overview-metric expense-overview-metric--{{ $key }}">
                <i class="bi bi-{{ $icon }} expense-overview-metric-icon" aria-hidden="true"></i>
                <div>
                    <dt>{{ $label }}</dt>
                    <dd>{{ \App\Support\Money::display($cents) }} {{ $currency }}</dd>
                </div>
                <i class="bi bi-{{ $accent }} expense-overview-metric-accent" aria-hidden="true"></i>
            </div>
        @endforeach
    </dl>
    @endfragment

    @fragment('expense-live-history')
    <section class="expense-overview-history panel" aria-labelledby="expense-history-title" data-expense-live="history">
        <header>
            <h2 id="expense-history-title"><i class="bi bi-clipboard-check" aria-hidden="true"></i> {{ __('Expense History') }}</h2>
            <span class="expense-overview-chip"><i class="bi bi-layers" aria-hidden="true"></i> {{ trans_choice(':count record|:count records', $expenses->total()) }}</span>
        </header>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Date spent') }}</th>
                        <th scope="col">{{ __('Amount') }}</th>
                        <th scope="col">{{ __('Reason / note') }}</th>
                        <th scope="col">{{ __('Recorded by') }}</th>
                        <th scope="col">{{ __('Recorded at') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $expense)
                        <tr>
                            <td class="text-nowrap"><i class="bi bi-calendar3 expense-overview-row-icon" aria-hidden="true"></i>{{ $expense->spent_on->translatedFormat('d M Y') }}</td>
                            <td><span class="expense-overview-chip expense-overview-amount">{{ \App\Support\Money::display(\App\Support\Money::cents($expense->amount)) }} {{ $expense->currency }}</span></td>
                            <td class="expense-overview-note">
                                @if($expense->employee_id)
                                    <div><strong>{{ __('Employee advance') }}</strong> &middot; {{ $expense->employee?->name }}</div>
                                    <small class="text-secondary">{{ __('Outstanding :amount', ['amount' => '$'.\App\Support\Money::display(\App\Support\Money::cents($expense->amount) - $expense->advance_recovered_cents)]) }}</small>
                                    @include('school.expenses._repayment-plan', ['amountCents' => \App\Support\Money::cents($expense->amount), 'months' => (int) $expense->repayment_months])
                                @endif
                                @if($expense->note || !$expense->employee_id)
                                    <div class="expense-overview-note-text">{{ $expense->note ?: __('No reason provided') }}</div>
                                @endif
                            </td>
                            <td><span class="expense-overview-chip"><i class="bi bi-person" aria-hidden="true"></i>{{ $expense->recorder?->name ?? __('Unavailable') }}</span></td>
                            <td class="text-nowrap"><i class="bi bi-clock expense-overview-row-icon" aria-hidden="true"></i>{{ $expense->created_at->translatedFormat('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center p-4 text-secondary">{{ __('No expenses recorded for this month and currency.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($expenses->hasPages())
            <div class="mt-3">{{ $expenses->links() }}</div>
        @endif
    </section>
    @endfragment
</div>
<div class="modal fade" id="employee-advance-modal" tabindex="-1" aria-labelledby="employee-advance-title" aria-hidden="true" @if($errors->any() && old('request_key')) data-reopen-advance @endif>
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="employee-advance-title">
                    Employee advance
                    <span class="badge rounded-pill text-bg-danger ms-2" data-expense-live-count="modal" @if(!$pendingAdvanceCount) hidden @endif>{{ trans_choice(':count pending request|:count pending requests', $pendingAdvanceCount) }}</span>
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body p-4">
                @fragment('expense-live-queue')
                <div data-expense-live="queue">
                @include('school.expenses._pending-requests')
                </div>
                @endfragment
                <p class="text-secondary">Record money paid to a teacher before payroll. The amount will be deducted from their salary.</p>
                @include('school.expenses._advance-form')
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/expense-live.js') }}?v={{ filemtime(public_path('js/expense-live.js')) }}" defer></script>
@endpush
