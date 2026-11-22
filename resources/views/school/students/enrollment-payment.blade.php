@extends('school.layout')
@section('title', 'Enrollment payment')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/enrollment-payment.css') }}?v={{ filemtime(public_path('css/enrollment-payment.css')) }}">
@endpush

@section('content')
<div class="enrollment-payment">
    <header class="enrollment-hero">
        <img class="enrollment-hero-art" src="{{ asset('images/enrollment-payment-hero.png') }}" alt="">
        <div class="enrollment-heading">
            <div class="enrollment-status"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ __('Enrollment complete') }}</div>
            <h1>{{ __('Choose how to pay') }}</h1>
            <p>{{ $tuition->isNotEmpty() ? __('Select your payment method to complete the first month of tuition.') : __('Enter the enrollment charge and choose a payment method.') }}</p>
        </div>
        <div class="enrollment-student-row">
            <div class="enrollment-student">
                @include('school.partials.avatar', ['person' => $student, 'size' => 62])
                <div><strong>{{ $student->name }}</strong><small>{{ $student->student_number }}</small></div>
            </div>
            @if($classes->isNotEmpty())
                <ul class="enrollment-programs" aria-label="{{ __('Enrolled programs and grades') }}">
                    @foreach($classes as $class)
                        <li>
                            <i class="bi {{ $loop->first ? 'bi-mortarboard' : 'bi-book' }}" aria-hidden="true"></i>
                            {{ collect([$class->program, trim($class->name.' '.$class->section), $class->session])->filter()->join(' · ') }}
                        </li>
                    @endforeach
                </ul>
            @endif
            <a class="enrollment-profile" href="{{ route('students.show', $student) }}"><i class="bi bi-person" aria-hidden="true"></i> {{ __('Student profile') }}</a>
        </div>
    </header>

    @error('finance')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    @if($problem && ! $errors->has('finance'))
        <div class="alert alert-warning" role="alert">{{ $problem }}</div>
    @endif

    <form class="enrollment-payment-grid" method="post" action="{{ route('students.enrollment-payment.store', $student) }}" @unless($problem) data-enrollment-checkout @endunless>
        @csrf
        <section class="enrollment-card" aria-labelledby="enrollment-tuition-title">
            <div class="enrollment-card-heading enrollment-tuition-heading">
                <span class="enrollment-icon enrollment-icon-fee"><i class="bi bi-coin" aria-hidden="true"></i></span>
                <div>
                    <h2 id="enrollment-tuition-title">{{ $tuition->isNotEmpty() ? __('First month of tuition') : __('Enrollment fee') }}</h2>
                    @if($tuition->isNotEmpty())
                        <p>{{ $student->enrollment_date->translatedFormat('F Y') }}</p>
                    @endif
                </div>
            </div>

            @if($tuition->isNotEmpty())
                <table class="enrollment-tuition" aria-labelledby="enrollment-tuition-title">
                    <tbody>
                        @foreach($tuition as $row)
                            @php($alternate = $loop->even)
                            @foreach($row['items'] as $item)
                                <tr @class(['enrollment-tuition-alternate' => $alternate, 'enrollment-tuition-extra' => ! $loop->first])>
                                    <th scope="row">
                                        @if($loop->first)
                                            <span class="enrollment-row-label"><i class="bi {{ $alternate ? 'bi-book' : 'bi-mortarboard' }}" aria-hidden="true"></i>{{ $row['label'] }}</span>
                                        @else
                                            {{ $item['description'] }}
                                        @endif
                                    </th>
                                    <td>{{ $row['rate']->currency === 'USD' ? '$' : '' }}{{ $item['amount'] }} {{ $row['rate']->currency }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    @if(! $problem && $tuition->count() > 1)
                        <tfoot>
                            <tr>
                                <th scope="row">{{ __('Combined tuition') }}</th>
                                <td>{{ $currency === 'USD' ? '$' : '' }}{{ $total }} {{ $currency }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
                @if(! $problem && $tuition->count() === 1)
                    <p class="enrollment-total">{{ __('Total') }} {{ $total }} {{ $currency }}</p>
                @endif
            @else
                <p class="enrollment-help">{{ __('No approved tuition rate matches the enrollment month.') }}</p>
                <label class="form-label" for="amount-usd">{{ __('Enrollment amount (USD)') }}</label>
                <input class="form-control mb-3 @error('amount_usd') is-invalid @enderror" id="amount-usd" name="amount_usd" type="number" min="0.01" max="999999" step="0.01" value="{{ old('amount_usd') }}" required>
                @error('amount_usd')<div class="invalid-feedback mb-3">{{ $message }}</div>@enderror
                <label class="form-label" for="payment-currency">{{ __('Pay in') }}</label>
                <select class="form-select mb-2" id="payment-currency" name="currency">
                    <option value="USD" @selected(old('currency') === 'USD')>{{ __('USD — US dollars') }}</option>
                    <option value="KHR" @selected(old('currency') === 'KHR')>{{ __('KHR — Cambodian riel') }}</option>
                </select>
                <p class="enrollment-help">{{ __('1 USD = 4,100 KHR. The selected currency will be saved on the invoice.') }}</p>
                <p class="enrollment-total" data-payment-total aria-live="polite">{{ __('Enter an amount to see the total.') }}</p>
            @endif
        </section>

        @unless($problem)
            <section class="enrollment-card enrollment-method-card" aria-labelledby="enrollment-method-title">
                <div class="enrollment-card-heading">
                    <span class="enrollment-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
                    <div>
                        <h2 id="enrollment-method-title">{{ __('Payment method') }}</h2>
                        <p>{{ __('Choose your preferred payment option.') }}</p>
                    </div>
                </div>
                @include('school.partials.payment-method-options', ['method' => old('method', 'cash')])
                @error('method')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
                <button type="submit" class="enrollment-continue" data-continue-payment data-pending-label="{{ __('Preparing payment…') }}">
                    <i class="bi bi-credit-card" aria-hidden="true"></i>
                    <span data-payment-button-label>{{ __('Continue to payment') }}</span>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            </section>
        @endunless
    </form>

    @unless($problem)
        <ul class="enrollment-payment-notes">
            <li>
                <span class="enrollment-icon enrollment-icon-confirmation"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                <div><strong>{{ __('Payment confirmation') }}</strong><small>{{ __('Review and confirm the payment on the next page.') }}</small></div>
            </li>
            <li>
                <span class="enrollment-icon"><i class="bi bi-receipt" aria-hidden="true"></i></span>
                <div><strong>{{ __('Official receipt') }}</strong><small>{{ __('A digital receipt is available after payment is confirmed.') }}</small></div>
            </li>
        </ul>
    @endunless
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/enrollment-checkout.js') }}?v={{ filemtime(public_path('js/enrollment-checkout.js')) }}"></script>
@endpush
