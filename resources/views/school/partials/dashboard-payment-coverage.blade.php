<section class="panel h-100 family-payment-panel" aria-labelledby="family-payment-coverage-title">
    <header class="panel-header family-payment-heading">
        <div class="dashboard-panel-title">
            <span class="family-payment-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
            <div>
                <h2 id="family-payment-coverage-title">{{ __('Tuition Payment Coverage') }}</h2>
                <p>{{ __('Time remaining in each child’s paid period') }}</p>
            </div>
        </div>
    </header>
    <div class="panel-body pt-0">
        <ul class="family-payment-list">
            @foreach($dashboardChildren as $child)
                @php
                    $coverage = $paymentCoverage->get($child->id);
                    $balances = $familyFeeTotals->get($child->id);
                    $active = $coverage && $coverage['daysLeft'] > 0;
                @endphp
                <li class="family-payment-child">
                    <div class="family-payment-entry">
                        <div class="family-payment-ring" style="--coverage: {{ $coverage['percent'] ?? 0 }}%">
                            <div>
                                <i class="bi bi-mortarboard-fill family-ring-cap" aria-hidden="true"></i>
                                @if($coverage)
                                    <strong>{{ $coverage['daysLeft'] }}</strong>
                                    <small>{{ trans_choice('day left|days left', $coverage['daysLeft']) }}</small>
                                    <span class="family-ring-date"><i class="bi bi-calendar-event" aria-hidden="true"></i> {{ __('Until :date', ['date' => $coverage['end']->translatedFormat('M j, Y')]) }}</span>
                                @else
                                    <small>{{ __('No paid period') }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="family-payment-copy">
                            <h3><i class="bi bi-person" aria-hidden="true"></i> {{ $child->name }}</h3>
                            @if($coverage)
                                <p class="family-payment-status" data-covered="{{ $active ? 'yes' : 'no' }}">
                                    <i class="bi bi-{{ $active ? 'check-circle-fill' : 'exclamation-circle-fill' }}" aria-hidden="true"></i>
                                    {{ $active ? __('Paid period active') : __('Paid period ended') }}
                                </p>
                                <p><i class="bi bi-calendar3" aria-hidden="true"></i> {{ trans_choice(':count month|:count months', $coverage['months']) }}
                                    @if($coverage['invoice']->program) &middot; {{ __($coverage['invoice']->program === 'Japan' ? 'Japanese' : $coverage['invoice']->program) }} @endif
                                </p>
                                <p><i class="bi bi-calendar-check" aria-hidden="true"></i> {{ __('Renewal date') }} <time datetime="{{ $coverage['end']->toDateString() }}">{{ $coverage['end']->translatedFormat('M j, Y') }}</time></p>
                            @else
                                <p>{{ __('No dated tuition payment with a known period is on record.') }}</p>
                            @endif
                            <a class="btn btn-primary" href="{{ route('students.family.show', [$child, 'tab' => 'fees']) }}">{{ __('View Fees') }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        </div>
                        <img class="family-payment-art" src="{{ asset('images/student-fees-banner.png') }}" alt="">
                    </div>

                    <dl class="family-payment-metrics" aria-label="{{ __('Fee totals for :name', ['name' => $child->name]) }}">
                        @foreach([
                            'billed' => ['Total Fee', 'database-fill'],
                            'paid' => ['Paid', 'check-circle'],
                            'remaining' => ['Remaining', 'hourglass-split'],
                        ] as $key => [$label, $icon])
                            <div data-metric="{{ $key }}">
                                <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                                <dt>{{ __($label) }}</dt>
                                @forelse($balances as $currency => $totals)
                                    <dd>{{ \App\Support\Money::display($totals[$key]) }} <small>{{ $currency }}</small></dd>
                                @empty
                                    <dd>&mdash;</dd>
                                @endforelse
                            </div>
                        @endforeach
                        <div data-metric="coverage">
                            <i class="bi bi-percent" aria-hidden="true"></i>
                            <dt>{{ __('Coverage') }}</dt>
                            <dd>{{ $coverage ? round($coverage['percent']).'%' : '—' }}</dd>
                        </div>
                    </dl>

                    @if($coverage)
                        <section class="family-payment-timeline" aria-label="{{ __('Payment Timeline') }}">
                            <header>
                                <h3><i class="bi bi-calendar-week" aria-hidden="true"></i> {{ __('Payment Timeline') }}</h3>
                                <span><i class="bi bi-calendar3" aria-hidden="true"></i> {{ trans_choice('Billing cycle: :count month|Billing cycle: :count months', $coverage['months']) }}</span>
                            </header>
                            <ol>
                                <li data-step="paid"><span><i class="bi bi-check-lg" aria-hidden="true"></i></span><time datetime="{{ $coverage['start']->toDateString() }}">{{ $coverage['start']->translatedFormat('M j, Y') }}</time><small>{{ __('Paid') }}</small></li>
                                @if($active)
                                    <li data-step="current"><span><i class="bi bi-check-lg" aria-hidden="true"></i></span><time datetime="{{ $paymentToday->toDateString() }}">{{ $paymentToday->translatedFormat('M j, Y') }}</time><small>{{ __('Today') }}</small></li>
                                @endif
                                <li data-step="{{ $active ? 'upcoming' : 'ended' }}"><span><i class="bi bi-{{ $active ? 'diamond-fill' : 'exclamation-lg' }}" aria-hidden="true"></i></span><time datetime="{{ $coverage['end']->toDateString() }}">{{ $coverage['end']->translatedFormat('M j, Y') }}</time><small>{{ $active ? __('Renewal') : __('Period ended') }}</small></li>
                                @unless($active)
                                    <li data-step="ended"><span><i class="bi bi-dot" aria-hidden="true"></i></span><time datetime="{{ $paymentToday->toDateString() }}">{{ $paymentToday->translatedFormat('M j, Y') }}</time><small>{{ __('Today') }}</small></li>
                                @endunless
                            </ol>
                        </section>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
</section>
