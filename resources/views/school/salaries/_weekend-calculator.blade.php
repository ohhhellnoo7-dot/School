@php
    $weekendErrors = $errors->getBag('weekendSalary');
    $selectedWeekendTeacher = old('teacher_id', session('weekend_salary_teacher', $weekendOptions->first()['id'] ?? null));
    $defaultWeekendRate = \App\Support\Money::decimal($rates['weekend_hourly']);
    $calendarMonth = $month->copy()->startOfMonth();
    $weekendDayCount = (int) $calendarMonth->diffInWeekendDays($calendarMonth->copy()->addMonth());
@endphp
<section id="salary-calculator-weekend" class="salary-calculator-form" hidden
    @if($weekendErrors->any() || session()->has('weekend_salary_teacher')) data-reopen @endif>
    <form method="post" action="{{ route('employee-salaries.weekend-settings.update') }}" class="d-grid gap-3">
        @csrf
        @method('put')
        <div>
            <label class="form-label" for="weekend-salary-teacher">{{ __('Weekend teacher') }}</label>
            <select id="weekend-salary-teacher" name="teacher_id" class="form-select" required>
                @forelse($weekendOptions as $option)
                    <option value="{{ $option['id'] }}" data-rate="{{ $option['rate'] }}" data-hours="{{ $option['hours'] }}"
                        @selected((string) $selectedWeekendTeacher === (string) $option['id'])>{{ $option['name'] }}</option>
                @empty
                    <option value="" data-rate="{{ $defaultWeekendRate }}" data-hours="3">{{ __('No teachers with weekend working hours') }}</option>
                @endforelse
            </select>
        </div>
        <div class="row g-2">
            <div class="col-sm-6">
                <label class="form-label" for="weekend-approved-rate">{{ __('Hourly rate (USD)') }}</label>
                <input id="weekend-approved-rate" name="hourly_rate" class="form-control" type="number" min="0" max="100000" step="0.01"
                    value="{{ old('hourly_rate', $defaultWeekendRate) }}" required>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="weekend-approved-hours">{{ __('Daily teaching hours') }}</label>
                <input id="weekend-approved-hours" name="daily_hours" class="form-control" type="number" min="0" max="24" step="0.01"
                    value="{{ old('daily_hours', 3) }}" required>
            </div>
        </div>
        @foreach($weekendErrors->all() as $error)
            <p class="text-danger mb-0">{{ $error }}</p>
        @endforeach
        <p class="small text-secondary mb-0">{{ __('Saved settings apply from the current payroll month. Pay uses scheduled weekend hours up to the approved daily hours.') }}</p>
        <button class="btn btn-primary" type="submit" @disabled($weekendOptions->isEmpty())>{{ __('Save Changes') }}</button>
    </form>

    <dl class="salary-calculator-breakdown weekend-salary-summary" aria-live="polite">
        @foreach(['hour' => ['clock', 'Per Hour'], 'day' => ['sun', 'Per Day'], 'weekend' => ['calendar-week', 'Per Weekend'], 'monthly' => ['calendar-month', 'Average Monthly Salary']] as $key => [$icon, $label])
            <div><dt><i class="bi bi-{{ $icon }}" aria-hidden="true"></i> {{ __($label) }}</dt><dd data-weekend-summary="{{ $key }}"></dd></div>
        @endforeach
    </dl>

    <fieldset class="d-grid gap-3">
        <legend class="h6">{{ __('Monthly estimate') }}</legend>
        <div>
            <label class="form-label" for="weekend-estimate-rate">{{ __('Estimate hourly rate (USD)') }}</label>
            <input id="weekend-estimate-rate" class="form-control" type="number" min="0" max="100000" step="0.01" value="{{ $defaultWeekendRate }}" required>
        </div>
        <div class="salary-slider">
            <div class="salary-slider-heading">
                <label for="weekend-estimate-hours">{{ __('Hours per day') }}</label>
                <output class="salary-hour-chip" id="weekend-hours-per-day" for="weekend-estimate-hours"></output>
            </div>
            <input id="weekend-estimate-hours" type="range" min="1" max="24" step="1" value="3">
            <div class="salary-slider-scale" aria-hidden="true"><span>1 h</span><span>24 h</span></div>
        </div>
        <div>
            <label class="form-label" for="weekend-estimate-days">{{ __('Weekend working days') }}</label>
            <select id="weekend-estimate-days" class="form-select">
                @foreach([8, 9, 10] as $count)
                    <option value="{{ $count }}" @selected($count === $weekendDayCount)>{{ $count }}</option>
                @endforeach
            </select>
        </div>
        <div class="salary-calculator-total" aria-live="polite">
            <span>{{ __('Estimated monthly salary') }}</span>
            <output id="weekend-estimated-pay" for="weekend-estimate-rate weekend-estimate-hours weekend-estimate-days"></output>
            <p class="salary-calculator-formula">{{ __('Total working hours') }} <output id="weekend-estimated-hours" for="weekend-estimate-hours weekend-estimate-days"></output></p>
        </div>
    </fieldset>
    <form method="dialog" class="salary-calculator-actions">
        <button class="btn btn-primary" type="submit">{{ __('Close') }}</button>
    </form>
</section>
