@if($amountCents >= \App\Services\EmployeeSalaryAdvances::PLAN_MINIMUM_CENTS)
    @php
        $installment = \App\Services\EmployeeSalaryAdvances::installmentCents($amountCents, $months);
        $lastInstallment = $amountCents - $installment * ($months - 1);
    @endphp
    <span class="d-block small text-secondary">
        @if($months === 1)
            {{ __('Repayment: :amount in one month', ['amount' => '$'.\App\Support\Money::display($amountCents)]) }}
        @else
            {{ __('Repayment: :amount per month for :months months', ['amount' => '$'.\App\Support\Money::display($installment), 'months' => $months]) }}
            @if($lastInstallment !== $installment)
                {{ __('(final installment :amount)', ['amount' => '$'.\App\Support\Money::display($lastInstallment)]) }}
            @endif
        @endif
    </span>
@endif
