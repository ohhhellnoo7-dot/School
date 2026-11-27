@php
    $rates = app(\App\Services\EmployeeSalaryRates::class)->amounts();
    $sessionHours = \App\Services\EmployeeSalaryRates::SESSION_WEEKLY_HOURS;
    $days = \App\Services\EmployeeSalaryRates::ESTIMATE_DAYS;
    $daytimePay = \App\Support\Money::decimal($rates['morning'] + $rates['afternoon']);
@endphp
<dialog id="salary-calculator" class="salary-calculator" aria-labelledby="salary-calculator-title" aria-describedby="salary-calculator-description" data-default-days="{{ $days }}">
    <header class="salary-calculator-header">
        <i class="bi bi-calculator salary-calculator-icon" aria-hidden="true"></i>
        <div>
            <h2 id="salary-calculator-title">Salary Calculator</h2>
            <p id="salary-calculator-description">Estimates use the saved monthly pay for each session. Choose the scheduled hours and days to count.</p>
        </div>
    </header>

    <form method="dialog" class="salary-calculator-form">
        @foreach(['morning' => 'sunrise', 'afternoon' => 'sun-fill', 'evening' => 'moon-stars-fill'] as $key => $icon)
            @php
                $max = $key === 'evening' ? 10 : $sessionHours[$key];
                $initial = $key === 'evening' ? 0 : $max;
                $hourlyRate = rtrim(rtrim(number_format($rates[$key] / ($sessionHours[$key] * $days * 100), 4, '.', ''), '0'), '.');
            @endphp
            <div class="salary-slider salary-slider-{{ $key }}" style="--slider-progress: {{ $initial ? 100 : 0 }}%">
                <div class="salary-slider-heading">
                    <label for="salary-{{ $key }}-hours"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i> Weekly {{ $key }} hours <small class="text-secondary">(${{ $hourlyRate }}/h)</small></label>
                    <span class="salary-hour-chip" data-hours="{{ $key }}">{{ $initial }} h/week</span>
                </div>
                <input id="salary-{{ $key }}-hours" name="{{ $key }}" type="range" min="0" max="{{ $max }}" step="1" value="{{ $initial }}" data-monthly-cents="{{ $rates[$key] }}" data-session-hours="{{ $sessionHours[$key] }}" @if($loop->first) autofocus @endif>
                <div class="salary-slider-scale" aria-hidden="true"><span>0 h</span><span>{{ $max }} h</span></div>
            </div>
        @endforeach

        <div class="salary-slider" style="--slider-progress: 66.667%">
            <div class="salary-slider-heading">
                <label for="salary-days"><i class="bi bi-calendar-week" aria-hidden="true"></i> Days to count</label>
                <span class="salary-hour-chip" data-hours="days">{{ $days }} days</span>
            </div>
            <input id="salary-days" name="days" type="range" min="1" max="7" step="1" value="{{ $days }}">
            <div class="salary-slider-scale" aria-hidden="true"><span>1 day</span><span>7 days</span></div>
        </div>

        <dl class="salary-calculator-breakdown">
            <div><dt>Estimated daytime pay</dt><dd data-estimate="daytime">${{ $daytimePay }}</dd></div>
            <div><dt>Estimated evening pay</dt><dd data-estimate="evening">$0.00</dd></div>
            <div><dt>Total weekly hours</dt><dd data-estimate="hours">40 h/week</dd></div>
        </dl>
        <div class="salary-calculator-total">
            <label for="salary-monthly-estimate">Estimated monthly base salary</label>
            <output id="salary-monthly-estimate" for="salary-morning-hours salary-afternoon-hours salary-evening-hours salary-days" data-estimate="total">${{ $daytimePay }}</output>
            <p class="salary-calculator-formula">Each session’s hours × its hourly rate × <span data-estimate="formula-days">{{ $days }} days</span></p>
        </div>

        <footer class="salary-calculator-actions">
            <button class="btn btn-outline-secondary" type="reset"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Reset calculator</button>
            <button class="btn btn-primary" type="submit">Close</button>
        </footer>
    </form>
</dialog>
