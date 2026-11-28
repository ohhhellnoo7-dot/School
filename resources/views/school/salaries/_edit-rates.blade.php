@php
    $rates = app(\App\Services\EmployeeSalaryRates::class)->amounts();
    $rateErrors = $errors->getBag('salaryRates');
    $sessionHours = \App\Services\EmployeeSalaryRates::SESSION_WEEKLY_HOURS;
    $estimateDays = \App\Services\EmployeeSalaryRates::ESTIMATE_DAYS;
@endphp
<dialog id="salary-rates-dialog" class="salary-calculator d-print-none" aria-labelledby="salary-rates-title" @if($rateErrors->any()) data-reopen @endif>
    <form method="post" action="{{ route('employee-salaries.salary-rates.update') }}" class="salary-calculator-form">
        @csrf
        @method('put')
        <h2 class="h5 mb-0" id="salary-rates-title">{{ __('Edit salary') }}</h2>
        <p class="text-secondary mb-0">{{ __('These USD rates apply to all teachers with unpaid salary calculations. Session pay is prorated by scheduled teaching hours.') }}</p>
        <div class="btn-group" role="group" aria-label="{{ __('Salary schedule') }}">
            <button class="btn btn-primary" type="button" data-salary-view="weekday" aria-controls="salary-weekday" aria-pressed="true">{{ __('Weekday') }}</button>
            <button class="btn btn-outline-primary" type="button" data-salary-view="weekend" aria-controls="salary-weekend" aria-pressed="false">{{ __('Weekend') }}</button>
        </div>
        <div class="d-grid gap-3">
        @foreach([
            __('Monthly session pay') => ['morning' => 'Morning', 'afternoon' => 'Afternoon', 'evening' => 'Evening (when assigned)'],
            __('Attendance deductions') => ['late' => 'Late per day', 'excused' => 'Excused per day after the first free excuse', 'absence_one' => 'One daytime session missed (two assigned)', 'absence_two' => 'Both daytime sessions missed', 'absence_single_session' => 'Only assigned session missed'],
        ] as $heading => $fields)
            <fieldset @if($loop->first) id="salary-weekday" @endif>
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
                                    @if(isset($sessionHours[$key])) data-session-hours="{{ $sessionHours[$key] }}" data-estimate-days="{{ $estimateDays }}" @endif
                                    @if($rateErrors->has('rates.'.$key)) aria-invalid="true" @endif
                                    aria-describedby="{{ trim((isset($sessionHours[$key]) ? 'salary-rate-preview-'.$key.' ' : '').($rateErrors->has('rates.'.$key) ? 'salary-rate-error-'.$key : '')) }}">
                                @if($rateErrors->has('rates.'.$key))
                                    <div class="invalid-feedback" id="salary-rate-error-{{ $key }}">{{ $rateErrors->first('rates.'.$key) }}</div>
                                @endif
                            </div>
                            @if(isset($sessionHours[$key]))
                                <output class="form-text d-block" id="salary-rate-preview-{{ $key }}" for="salary-rate-{{ $key }}"></output>
                            @endif
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endforeach
        <p class="small text-secondary mb-0">{{ __('Attendance deduction rates apply to both weekdays and weekends.') }}</p>
        <p class="small text-secondary mb-0">{{ __('The single-session charge applies to teachers who work only Morning, Afternoon, or Evening.') }}</p>
        </div>
        <section id="salary-weekend" hidden>
            @php
                $hourlyCents = $rates['weekend_hourly'];
                $sessionCents = 3 * $hourlyCents;
                $saturdays = $sundays = 0;
                for ($date = $month->copy()->startOfMonth(); $date->isSameMonth($month); $date->addDay()) {
                    $saturdays += (int) $date->isSaturday();
                    $sundays += (int) $date->isSunday();
                }
                $money = fn ($cents) => '$'.\App\Support\Money::decimal($cents);
            @endphp
            <h3 class="h6">{{ __('Weekend monthly session pay') }}</h3>
            <div class="mb-3">
                <label class="form-label" for="salary-rate-weekend">{{ __('Weekend hourly pay (USD)') }}</label>
                <input id="salary-rate-weekend" name="rates[weekend_hourly]" class="form-control @if($rateErrors->has('rates.weekend_hourly')) is-invalid @endif"
                    type="number" min="0" max="100000" step="0.01" required value="{{ old('rates.weekend_hourly', \App\Support\Money::decimal($rates['weekend_hourly'])) }}">
                @if($rateErrors->has('rates.weekend_hourly'))
                    <div class="invalid-feedback">{{ $rateErrors->first('rates.weekend_hourly') }}</div>
                @endif
            </div>
            <p class="text-secondary">{{ __('Example for a 3-hour session on both days. Teacher pay uses their scheduled weekend hours.') }}</p>
            <div class="table-responsive">
                <table class="table table-sm">
                    <caption class="caption-top">{{ $month->format('F Y') }} · {{ trans_choice('{1} :count Saturday|[2,*] :count Saturdays', $saturdays) }} · {{ trans_choice('{1} :count Sunday|[2,*] :count Sundays', $sundays) }}</caption>
                    <thead><tr><th scope="col">{{ __('Session') }}</th><th scope="col">{{ __('Saturday') }}</th><th scope="col">{{ __('Sunday') }}</th><th scope="col">{{ __('Per weekend') }}</th><th scope="col">{{ __('This month') }}</th></tr></thead>
                    <tbody>
                        @foreach(['Morning', 'Afternoon', 'Evening'] as $session)
                            <tr><th scope="row">{{ __($session) }}</th>
                                @foreach([1, 1, 2, $saturdays + $sundays] as $days)
                                    <td data-weekend-pay-hours="{{ 3 * $days }}">{{ $money($days * $sessionCents) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th scope="row">{{ __('Total') }}</th>
                        @foreach([1, 1, 2, $saturdays + $sundays] as $days)
                            <td data-weekend-pay-hours="{{ 9 * $days }}">{{ $money(3 * $days * $sessionCents) }}</td>
                        @endforeach
                    </tr></tfoot>
                </table>
            </div>
            <h4 class="h6">{{ __('Weekend working hours') }}</h4>
            @forelse($rows->where('weekend_work_hours', '>', 0) as $row)
                <div class="d-flex flex-wrap justify-content-between gap-2 border-top py-2">
                    <a href="{{ route('teacher-management.edit', $row['teacher']) }}#teacher-weekend-hours">{{ $row['teacher']->name }}</a>
                    <span>{{ round($row['weekend_work_hours'], 2) }} h weekend · {{ round($row['weekly_work_hours'], 2) }} h total/week</span>
                    @if(isset($row['salary']['weekend_cents']))
                        <span>${{ \App\Support\Money::decimal($row['salary']['weekend_cents']) }} {{ __('this month') }}</span>
                    @endif
                </div>
            @empty
                <p class="text-secondary mb-0">{{ __('No weekend hours to show for this payroll view.') }}</p>
            @endforelse
        </section>
        <div class="salary-calculator-actions">
            <button class="btn btn-outline-secondary" type="button" data-cancel-salary-rates>{{ __('Cancel') }}</button>
            <button class="btn btn-primary" type="submit" data-save-salary-rates>{{ __('Save rates') }}</button>
        </div>
    </form>
</dialog>
