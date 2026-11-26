@if(today()->day === 1 && today()->gte(\App\Services\EmployeeSalaryDeductions::FIRST_CARRY_FORWARD_MONTH))
    <a class="btn btn-sm btn-primary" href="{{ route('employee-salaries.index', ['withdrawal' => 1]) }}#payday-withdrawal">{{ __('Withdraw money for employees') }}</a>
@else
    <button class="btn btn-sm btn-outline-primary" type="button" disabled>{{ __('Withdraw money for employees') }}</button>
    <small class="align-self-center">{{ __('Available on the 1st of each month') }}</small>
@endif
