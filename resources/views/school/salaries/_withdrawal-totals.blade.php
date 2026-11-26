<div class="row g-2 flex-grow-1" aria-label="{{ __('Employee payroll totals in USD') }}">
    <div class="col">
        <h3>{{ __('Payroll remaining') }}</h3>
        <strong data-payroll-withdrawal-remaining>${{ \App\Support\Money::display($withdrawalTotals['remaining']) }}</strong>
    </div>
    <div class="col">
        <h3>{{ __('Paid to employees') }}</h3>
        <strong data-payroll-withdrawal-paid>${{ \App\Support\Money::display($withdrawalTotals['paid']) }}</strong>
    </div>
</div>
