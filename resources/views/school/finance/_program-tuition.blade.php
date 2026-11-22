@php
    $canEditTuition = auth()->user()->role === 'administrator';
    $tuitionByProgram = $programRates->groupBy(fn ($rates) => $rates->first()->program);
    $programAccents = ['Khmer' => 'mint', 'English' => 'blue', 'Chinese' => 'rose', 'Japan' => 'violet'];
    $sessionMap = \App\Models\Program::sessionMap();
@endphp

<section class="panel finance-tuition-panel mb-4" aria-labelledby="finance-tuition-title">
    <header class="panel-header">
        <div class="finance-collection-title">
            <i class="bi bi-mortarboard" aria-hidden="true"></i>
            <h2 id="finance-tuition-title">{{ __('Current program tuition') }}</h2>
        </div>
        @if($canEditTuition)
            <a class="btn btn-outline-primary" href="{{ route('finance.tuition.edit') }}">
                <i class="bi bi-pencil-square" aria-hidden="true"></i> {{ __('Edit tuition') }}
            </a>
        @endif
    </header>

    @if($tuitionByProgram->isNotEmpty())
        <div class="finance-tuition-grid">
            @foreach($tuitionByProgram as $program => $tiers)
                <article class="finance-tuition-card" data-tone="{{ $programAccents[$program] ?? 'mint' }}">
                    <h3>
                        <i class="bi bi-book" aria-hidden="true"></i>
                        {{ __($program === 'Japan' ? 'Japanese' : $program) }}
                    </h3>
                    @foreach($tiers as $rates)
                        @php
                            $rate = $rates->first();
                            $grades = $rates->pluck('grade')->unique()->sort()->values();
                        @endphp
                        <section class="finance-tuition-tier">
                            <header>
                                <div>
                                    <h4>{{ __('Grades / levels') }} {{ $grades->join(', ') }}</h4>
                                    <p>{{ $rate->academic_year }} &middot; {{ trans_choice(':count billable month|:count billable months', $rate->billable_months) }}</p>
                                </div>
                                @if($canEditTuition)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('finance.tuition.edit', ['structure' => $rate->id, 'grades' => $grades->all()]) }}"
                                        aria-label="{{ __('Edit :program tuition for grades or levels :grades', ['program' => $program === 'Japan' ? __('Japanese') : __($program), 'grades' => $grades->join(', ')]) }}">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> {{ __('Edit') }}
                                    </a>
                                @endif
                            </header>
                            @foreach($rate->ratesForSessions($sessionMap[$program] ?? ['Morning', 'Afternoon', 'Evening']) as $price)
                                <div class="finance-tuition-session">
                                    <h5><i class="bi bi-clock" aria-hidden="true"></i> {{ implode(' / ', array_map('__', $price['sessions'])) }}</h5>
                                    <dl>
                                        <div><dt>{{ __('Monthly') }}</dt><dd>{{ $price['monthly'] }} <small>{{ $rate->currency }}</small></dd></div>
                                        <div><dt>{{ __('6 months') }}</dt><dd>{{ $price['six_month'] }} <small>{{ $rate->currency }}</small></dd>
                                            @if($price['package'])<dd class="finance-tuition-package">{{ __('Package price') }}</dd>@endif
                                        </div>
                                        <div><dt>{{ __('Annual') }}</dt><dd>{{ $price['annual'] }} <small>{{ $rate->currency }}</small></dd></div>
                                    </dl>
                                </div>
                            @endforeach
                        </section>
                    @endforeach
                </article>
            @endforeach
        </div>
        <p class="form-text px-4 pb-3 mb-0">{{ __('Discounts and additional charges are shown on individual invoices.') }}</p>
    @else
        <p class="p-4 mb-0">
            {{ __('No approved program tuition configured yet.') }}
            @if($canEditTuition)<a href="{{ route('finance.settings') }}">{{ __('Review program fee settings') }}</a>@endif
        </p>
    @endif
</section>
