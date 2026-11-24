<header class="parent-fees-hero">
    <img src="{{ asset('images/parent-fees-wallet.png') }}" alt="" width="160" height="160">
    <div>
        <span class="parent-fees-eyebrow">{{ __('School Finance') }}</span>
        <h1>{{ __('School Fees & Payments') }}</h1>
        <p>{{ __('View invoices, track payments, and check balances.') }}</p>
        <a class="btn btn-outline-primary" href="#invoice-list"><i class="bi bi-receipt" aria-hidden="true"></i> {{ __('Invoices & payments') }}</a>
    </div>
</header>

@if($studentSummaries->isNotEmpty())
    <div class="parent-fees-children">
        @foreach($studentSummaries as $entry)
            @php
                $child = $entry['student'];
                $class = $child->schoolClass;
            @endphp
            <section class="parent-fees-child" aria-label="{{ __('Fee balance for :name', ['name' => $child->name]) }}">
                <div class="parent-fees-child-profile">
                    @include('school.partials.avatar', ['person' => $child, 'size' => 88])
                    <div>
                        <h2>{{ $child->name }}</h2>
                        <div class="parent-fees-child-pills">
                            <span>{{ $child->student_number }}</span>
                            <span data-state="{{ $child->status }}"><i class="bi bi-{{ $child->status === 'active' ? 'check-circle-fill' : 'info-circle' }}" aria-hidden="true"></i> {{ __(ucfirst($child->status)) }}</span>
                        </div>
                    </div>
                </div>
                <div class="parent-fees-child-class">
                    @if($class)
                        <span><i class="bi bi-mortarboard" aria-hidden="true"></i><strong>{{ collect([$class->name, $class->section])->filter()->join(' – ') }}</strong></span>
                        <span><i class="bi bi-book" aria-hidden="true"></i>{{ __('Program') }} <strong>{{ __($class->program === 'Japan' ? 'Japanese' : $class->program) }}</strong></span>
                        <span><i class="bi bi-calendar3" aria-hidden="true"></i>{{ __('Session') }} <strong>{{ __($class->session) }}</strong></span>
                    @else
                        <span>{{ __('No class recorded') }}</span>
                    @endif
                </div>
                <div class="parent-fees-child-balances">
                    @foreach($entry['currencies'] as $currency => $balance)
                        <div>
                            <i class="bi bi-database-fill" aria-hidden="true"></i>
                            <div><span>{{ __('Remaining balance') }}</span><strong data-settled="{{ $balance['balance'] <= 0 ? 'yes' : 'no' }}">{{ \App\Support\Money::display($balance['balance']) }} <small>{{ $currency }}</small></strong></div>
                        </div>
                        <div>
                            <i class="bi bi-calendar-check" aria-hidden="true"></i>
                            <div><span>{{ __('Next unpaid due date') }}</span>
                                @if($balance['next_due'])
                                    <time datetime="{{ $balance['next_due'] }}">{{ \Illuminate\Support\Carbon::parse($balance['next_due'])->translatedFormat('M j, Y') }}</time>
                                @else
                                    <span class="parent-fees-no-unpaid"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ __('No unpaid invoices') }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
