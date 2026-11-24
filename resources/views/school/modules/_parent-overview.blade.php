@php
    $totalParents = $parentSummary['total'];
    $genderShares = collect(['male', 'female', 'unspecified'])->mapWithKeys(fn ($key) => [
        $key => $totalParents ? $parentSummary[$key] / $totalParents * 100 : 0,
    ]);
    $largestProgram = max(1, $parentSummary['programs']->max());
@endphp

<header class="parent-directory-banner">
    <div class="parent-banner-art" aria-hidden="true"></div>
    <i class="bi bi-mortarboard-fill" aria-hidden="true"></i>
    <div>
        <h1>{{ __('Welcome to') }} <span>Scholar</span></h1>
        <p>{{ __('School Management System') }}</p>
    </div>
</header>

<section class="parent-overview-panel" aria-label="{{ __('Parent overview') }}">
    <div class="parent-total-ring">
        <div>
            <i class="bi bi-people-fill" aria-hidden="true"></i>
            <strong>{{ number_format($totalParents) }}</strong>
            <span>{{ __('Total parents') }}</span>
        </div>
    </div>
    <ul class="parent-gender-breakdown">
        @foreach(['male' => ['Male parents', 'gender-male'], 'female' => ['Female parents', 'gender-female'], 'unspecified' => ['Other / not provided', 'people-fill']] as $key => [$label, $icon])
            <li class="parent-gender-{{ $key }}">
                <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                <span>{{ __($label) }}</span>
                <strong>{{ number_format($parentSummary[$key]) }}</strong>
                <span>{{ number_format($genderShares[$key], 1) }}%</span>
            </li>
        @endforeach
    </ul>
    <section class="parent-program-chart" aria-labelledby="parent-program-chart-title">
        <h2 id="parent-program-chart-title"><i class="bi bi-bar-chart-line-fill" aria-hidden="true"></i> {{ __('Parents by Program') }}</h2>
        <ul>
            @foreach($parentSummary['programs'] as $program => $count)
                <li style="--program-share: {{ $count / $largestProgram * 100 }}%">
                    <div class="parent-program-plot"><strong>{{ number_format($count) }}</strong><span aria-hidden="true"></span></div>
                    <span>{{ __($program === 'Japan' ? 'Japanese' : $program) }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</section>
