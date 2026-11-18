<dialog id="salary-calculator" class="salary-calculator" aria-labelledby="salary-calculator-title" aria-describedby="salary-calculator-description" data-hourly-cents="{{ $salaryHourlyCents }}">
    <header class="salary-calculator-header">
        <i class="bi bi-calculator salary-calculator-icon" aria-hidden="true"></i>
        <div>
            <h2 id="salary-calculator-title">Salary Calculator</h2>
            <p id="salary-calculator-description">Estimate monthly base salary at ${{ \App\Support\Money::decimal($salaryHourlyCents) }} per assigned weekly hour.</p>
        </div>
    </header>

    <form method="dialog" class="salary-calculator-form">
        @foreach([['daytime', 'Daytime', 'sun-fill', 40, 40], ['evening', 'Evening', 'moon-stars-fill', 10, 0]] as [$key, $label, $icon, $max, $initial])
            <div class="salary-slider salary-slider-{{ $key }}" style="--slider-progress: {{ $initial ? 100 : 0 }}%">
                <div class="salary-slider-heading">
                    <label for="salary-{{ $key }}-hours"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i> Weekly {{ strtolower($label) }} hours</label>
                    <span class="salary-hour-chip" data-hours="{{ $key }}">{{ $initial }} h</span>
                </div>
                <input id="salary-{{ $key }}-hours" name="{{ $key }}" type="range" min="0" max="{{ $max }}" step="1" value="{{ $initial }}" @if($loop->first) autofocus @endif>
                <div class="salary-slider-scale" aria-hidden="true"><span>0 h</span><span>{{ $max }} h</span></div>
            </div>
        @endforeach

        <dl class="salary-calculator-breakdown">
            <div><dt>Daytime salary</dt><dd data-estimate="daytime">${{ \App\Support\Money::decimal($salaryHourlyCents * 40) }}</dd></div>
            <div><dt>Evening salary</dt><dd data-estimate="evening">$0.00</dd></div>
            <div><dt>Total weekly hours</dt><dd data-estimate="hours">40 h</dd></div>
        </dl>
        <div class="salary-calculator-total">
            <label for="salary-monthly-estimate">Estimated monthly salary</label>
            <output id="salary-monthly-estimate" for="salary-daytime-hours salary-evening-hours" data-estimate="total">${{ \App\Support\Money::decimal($salaryHourlyCents * 40) }}</output>
        </div>

        <footer class="salary-calculator-actions">
            <button class="btn btn-outline-secondary" type="reset"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Reset calculator</button>
            <button class="btn btn-primary" type="submit">Close</button>
        </footer>
    </form>
</dialog>
