@php
    $rates = app(\App\Services\EmployeeSalaryRates::class)->amounts();
    $rateErrors = $errors->getBag('salaryRates');
@endphp
<dialog id="salary-rates-dialog" class="salary-calculator d-print-none" aria-labelledby="salary-rates-title" @if($rateErrors->any()) data-reopen @endif>
    <form method="post" action="{{ route('employee-salaries.salary-rates.update') }}" class="salary-calculator-form">
        @csrf
        @method('put')
        <h2 class="h5 mb-0" id="salary-rates-title">{{ __('Edit salary') }}</h2>
        <p class="text-secondary mb-0">{{ __('These USD rates apply to all teachers with unpaid salary calculations. Session pay is prorated by scheduled teaching hours.') }}</p>
        @foreach([
            __('Monthly session pay') => ['morning' => 'Morning', 'afternoon' => 'Afternoon', 'evening' => 'Evening (when assigned)'],
            __('Attendance deductions') => ['late' => 'Late per day', 'excused' => 'Excused per day after the first free excuse', 'absence_one' => 'One daytime session missed (two assigned)', 'absence_two' => 'Both daytime sessions missed', 'absence_single_session' => 'Only assigned session missed'],
        ] as $heading => $fields)
            <fieldset>
                <legend class="h6 mb-2">{{ $heading }}</legend>
                <div class="row g-2">
                    @foreach($fields as $key => $label)
                        <div class="col-sm-6">
                            <label class="form-label" for="salary-rate-{{ $key }}">{{ __($label) }}</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input id="salary-rate-{{ $key }}" name="rates[{{ $key }}]"
                                    class="form-control @if($rateErrors->has('rates.'.$key)) is-invalid @endif"
                                    type="number" min="0" max="100000" step="0.01" required
                                    value="{{ old('rates.'.$key, \App\Support\Money::decimal($rates[$key])) }}"
                                    @if($rateErrors->has('rates.'.$key)) aria-invalid="true" aria-describedby="salary-rate-error-{{ $key }}" @endif>
                                @if($rateErrors->has('rates.'.$key))
                                    <div class="invalid-feedback" id="salary-rate-error-{{ $key }}">{{ $rateErrors->first('rates.'.$key) }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
        <p class="small text-secondary mb-0">{{ __('The single-session charge applies to teachers who work only Morning, Afternoon, or Evening.') }}</p>
        <div class="salary-calculator-actions">
            <button class="btn btn-outline-secondary" type="button" data-cancel-salary-rates>{{ __('Cancel') }}</button>
            <button class="btn btn-primary" type="submit">{{ __('Save rates') }}</button>
        </div>
    </form>
</dialog>
