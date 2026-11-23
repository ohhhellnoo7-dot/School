@php
    $affectedClass = $rateIssue['class'];
    $program = $affectedClass->program;
    $level = $rateIssue['level'];
    $neededMonth = $rateIssue['month']->translatedFormat('F Y');
@endphp
<details class="alert alert-warning mb-0">
    <summary class="fw-semibold">{{ $problem }} {{ __('View details') }}</summary>
    <div class="mt-3">
        <p class="mb-2"><strong>{{ $program }} · {{ $affectedClass->name }} {{ $affectedClass->section }} · {{ __('Level') }} {{ $level ?? '—' }} · {{ $affectedClass->session }}</strong></p>
        @if($rateIssue['matching'] > 1)
            <p>{{ __(':count active, approved rates cover :month. Review their billing dates so exactly one rate covers this month.', ['count' => $rateIssue['matching'], 'month' => $neededMonth]) }}</p>
        @else
            <p>{{ __('No active, approved rate available today covers :month. Set up tuition for the academic year that includes this month, then reopen the payment preview.', ['month' => $neededMonth]) }}</p>
        @endif
        @if($rateIssue['rates']->isNotEmpty())
            <ul class="mb-3">
                @foreach($rateIssue['rates'] as $rate)
                    <li>{{ $rate->academic_year }} · {{ $rate->starts_on->translatedFormat('F Y') }} – {{ $rate->starts_on->copy()->addMonthsNoOverflow($rate->billable_months - 1)->translatedFormat('F Y') }}</li>
                @endforeach
            </ul>
        @endif
        <div class="d-flex flex-wrap gap-2">
            @if(in_array($program, ['Khmer', 'English', 'Chinese', 'Japan'], true) && $level >= 1 && $level <= 12)
                <a class="btn btn-outline-primary" href="{{ route('finance.tuition.edit', ['program' => $program, 'grades' => [$level]]) }}">{{ __('Edit tuition for :program level :level', ['program' => $program, 'level' => $level]) }}</a>
            @endif
            <a class="btn btn-outline-primary" href="{{ route('finance.settings') }}">{{ __('Review fee settings') }}</a>
        </div>
    </div>
</details>
