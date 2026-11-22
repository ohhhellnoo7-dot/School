@if($periods->isNotEmpty())
    <form class="family-filter" action="{{ $profile }}" method="get" data-family-navigation>
        <input type="hidden" name="tab" value="results">
        <div>
            <label class="form-label" for="family-period">{{ __('Exam date period') }}</label>
            <select class="form-select" name="period" id="family-period">
                <option value="">{{ __('All periods') }}</option>
                @foreach($periods as $key)
                    <option value="{{ $key }}" @selected($key === $period)>{{ str_ends_with($key, '-1') ? __('January–June') : __('July–December') }} {{ substr($key, 0, 4) }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn family-button" type="submit">{{ __('Show results') }}</button>
    </form>
@endif
<ul class="family-records family-result-list">
    @forelse($grades as $grade)
        <li>
            <div>
                <strong>{{ $grade->examination->subject?->name }} · {{ $grade->examination->name }}</strong>
                <small>{{ $grade->examination->date->translatedFormat('M j, Y') }}</small>
                @if($grade->remarks)<p>{{ $grade->remarks }}</p>@endif
            </div>
            <div class="family-result-score">
                <strong>{{ $grade->marks }} / {{ $grade->examination->total_marks }} · {{ $grade->letter_grade }}</strong>
                <progress class="family-progress" max="100" value="{{ min(100, max(0, $grade->percentage)) }}" aria-label="{{ $grade->examination->name }} {{ round($grade->percentage) }}%"></progress>
            </div>
        </li>
    @empty
        <li>{{ __('No exam results recorded for this period.') }}</li>
    @endforelse
</ul>
