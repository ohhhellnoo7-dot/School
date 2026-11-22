@php
    $canEditTuition = auth()->user()->role === 'administrator';
    $tuitionByProgram = $programRates->groupBy(fn ($rates) => $rates->first()->program);
    $programAccents = ['Khmer' => 'mint', 'English' => 'blue', 'Chinese' => 'rose', 'Japan' => 'violet'];
    $sessionMap = \App\Models\Program::sessionMap();
    $programs = \App\Models\Program::whereIn('name', $tuitionByProgram->keys())->get()->keyBy('name');
@endphp

<section class="panel finance-tuition-panel mb-4" id="current-program-tuition" aria-labelledby="finance-tuition-title">
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
                @php
                    $programRecord = $programs->get($program);
                    $banner = $programRecord?->tuition_banner ? route('finance.programs.banner', ['program' => $program]) : null;
                    $programLabel = __($program === 'Japan' ? 'Japanese' : $program);
                @endphp
                <article class="finance-tuition-card" data-tone="{{ $programAccents[$program] ?? 'mint' }}">
                    <header class="finance-tuition-banner"
                        @if($canEditTuition && $programRecord)
                            data-tuition-customize="{{ route('finance.programs.banner.update', ['program' => $program]) }}"
                            data-program-label="{{ $programLabel }}" data-current-banner="{{ $banner }}"
                        @endif>
                        <span class="finance-tuition-art" aria-hidden="true"></span>
                        @if($banner)<img class="finance-tuition-custom-art" src="{{ $banner }}" alt="">@endif
                        <i class="bi bi-book-fill" aria-hidden="true"></i>
                        <div>
                            <h3>{{ $programLabel }}</h3>
                            <p>{{ __('Grades / levels') }}: {{ $tiers->first()->pluck('grade')->unique()->sort()->join(', ') }}</p>
                        </div>
                        @if($canEditTuition && $programRecord)
                            <button type="button" class="finance-tuition-customize" data-tuition-customize-button
                                aria-label="{{ __('Customize :program picture', ['program' => $programLabel]) }}">
                                <i class="bi bi-image" aria-hidden="true"></i> {{ __('Customize') }}
                            </button>
                        @endif
                    </header>
                    @foreach($tiers as $rates)
                        @php
                            $rate = $rates->first();
                            $grades = $rates->pluck('grade')->unique()->sort()->values();
                        @endphp
                        @if(!$loop->first)
                            <details class="finance-tuition-more">
                                <summary>{{ __('Grades / levels') }}: {{ $grades->join(', ') }} <small>{{ $rate->academic_year }}</small></summary>
                        @endif
                        <section class="finance-tuition-tier">
                            <h4><i class="bi bi-tag" aria-hidden="true"></i> {{ __('Billing Amounts') }}</h4>
                            <p>{{ $rate->academic_year }} &middot; {{ trans_choice(':count billable month|:count billable months', $rate->billable_months) }}</p>
                            @foreach($rate->ratesForSessions($sessionMap[$program] ?? ['Morning', 'Afternoon', 'Evening']) as $price)
                                <div class="finance-tuition-session">
                                    <h5><i class="bi bi-clock" aria-hidden="true"></i> {{ implode(' / ', array_map('__', $price['sessions'])) }}</h5>
                                    <dl>
                                        <div><dt><i class="bi bi-calendar3" aria-hidden="true"></i> {{ __('Monthly') }}</dt><dd>{{ $price['monthly'] }} <small>{{ $rate->currency }}</small></dd></div>
                                        <div><dt><i class="bi bi-layers" aria-hidden="true"></i> {{ __('6 Months') }}</dt><dd>{{ $price['six_month'] }} <small>{{ $rate->currency }}</small></dd>
                                            @if($price['package'])<dd class="finance-tuition-package">{{ __('Package price') }}</dd>@endif
                                        </div>
                                        <div><dt><i class="bi bi-award" aria-hidden="true"></i> {{ __('Annual') }}</dt><dd>{{ $price['annual'] }} <small>{{ $rate->currency }}</small></dd></div>
                                    </dl>
                                </div>
                            @endforeach
                            @if($canEditTuition)
                                <footer class="finance-tuition-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('finance.tuition.edit', ['structure' => $rate->id, 'grades' => $grades->all()]) }}"
                                        aria-label="{{ __('Edit :program tuition for grades or levels :grades', ['program' => $program === 'Japan' ? __('Japanese') : __($program), 'grades' => $grades->join(', ')]) }}">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> {{ __('Edit') }}
                                    </a>
                                    @if($loop->first)
                                        <a class="btn btn-sm finance-tuition-manage text-white" href="{{ route('finance.tuition.edit', ['program' => $program]) }}">
                                            <i class="bi bi-layers" aria-hidden="true"></i> {{ __('Manage Levels') }}
                                        </a>
                                    @endif
                                </footer>
                            @endif
                        </section>
                        @if(!$loop->first)</details>@endif
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

@if($canEditTuition && $programs->isNotEmpty())
    <dialog class="tuition-banner-dialog" data-tuition-banner-dialog aria-labelledby="tuition-banner-dialog-title">
        <form method="post" enctype="multipart/form-data">
            @csrf
            <h2 id="tuition-banner-dialog-title">{{ __('Customize picture') }} <span data-tuition-banner-program></span></h2>
            <img data-tuition-banner-preview alt="{{ __('Program picture preview') }}" hidden>
            <label for="tuition-banner-file" class="form-label">{{ __('Choose a picture') }}</label>
            <input id="tuition-banner-file" class="form-control" type="file" name="banner" accept="image/jpeg,image/png,image/webp,image/gif" required>
            <p class="form-text">{{ __('JPG, PNG, WebP or GIF, up to 2 MB.') }}</p>
            <div class="tuition-banner-dialog-actions">
                <button type="submit" class="btn btn-outline-secondary" name="action" value="reset" formnovalidate hidden>{{ __('Restore original') }}</button>
                <button type="button" class="btn btn-outline-secondary" data-tuition-banner-cancel>{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary" name="action" value="save">{{ __('Save picture') }}</button>
            </div>
        </form>
    </dialog>
    @push('scripts')
        <script src="{{ asset('js/tuition-banner.js') }}?v={{ filemtime(public_path('js/tuition-banner.js')) }}" defer></script>
    @endpush
@endif
