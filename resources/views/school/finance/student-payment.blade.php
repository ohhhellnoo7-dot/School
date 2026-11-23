@extends('school.layout')
@section('title', __('Student payment'))
@section('finance-title', __('School Fees'))
@section('content')
@include('school.finance._nav', ['compactStudentPayment' => true])

<section class="panel student-payment-search-panel">
    <h2><i class="bi bi-people" aria-hidden="true"></i> {{ __('Find a Student') }}</h2>
    <form action="{{ route('finance.student-payment') }}" method="get" class="student-payment-search-form" data-student-payment-search>
        <div><i class="bi bi-search" aria-hidden="true"></i><input class="form-control" type="search" name="search" value="{{ $search }}" maxlength="100" aria-label="{{ __('Student name or ID') }}" placeholder="{{ __('Start typing a student name or ID…') }}" autocomplete="off"></div>
        <button class="btn btn-primary"><i class="bi bi-search me-1" aria-hidden="true"></i>{{ __('Search') }}</button>
    </form>
    <p class="small text-secondary mb-2" data-student-payment-status role="status"></p>
    @include('school.finance._student-payment-results')
</section>

@if($student)
<section class="panel student-payment-workspace" id="student-payment-quote">
    @if($invoices->isNotEmpty())
        @include('school.finance._student-payment-invoices')
    @endif
    <p class="small text-secondary mb-2" data-payment-preview-status role="status"></p>
    @fragment('student-payment-preview')
    <div data-payment-preview>
    <div class="student-payment-workspace-head">
      <div class="student-payment-profile">
        @include('school.partials.avatar', ['person' => $student, 'size' => 68])
        <div><h2>{{ $student->name }} <small>{{ $student->student_number }}</small></h2>
        @if($student->schoolClass)<span class="student-payment-level"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i> {{ $student->schoolClass->name }} {{ $student->schoolClass->section }} @if($level = \App\Services\StudentTuitionPayment::level($student->schoolClass)) · {{ __('Level') }} {{ $level }} @endif</span>@endif
        </div>
      </div>
      <div class="student-payment-periods">
    <nav class="d-flex flex-wrap gap-2" aria-label="{{ __('Additional tuition months') }}">
        @foreach([1, 6, 12] as $duration)
            <a href="{{ route('finance.student-payment', ['student' => $student->id, 'months' => $duration, 'select_programs' => 1, 'enrollments' => $selection]) }}#student-payment-quote" data-payment-duration="{{ $duration }}" class="btn {{ $months === $duration ? 'btn-primary' : 'btn-outline-primary' }}" @if($months === $duration) aria-current="true" @endif><i class="bi bi-calendar-event me-1" aria-hidden="true"></i> {{ trans_choice(':count month|:count months', $duration) }}</a>
        @endforeach
    </nav>
    <p><i class="bi bi-info-circle me-1" aria-hidden="true"></i> {{ __('Tick the programs to include in this payment. Paid months are skipped.') }}</p>
      </div>
    </div>
    @if($choices->isNotEmpty())
        @php
            $quotedRows = collect($quote['rows'] ?? [])->keyBy('enrollment');
            $tasksByClass = collect($quote['tasks'] ?? [])->groupBy('class.id');
            $existingByEnrollment = collect($quote['existing'] ?? [])->groupBy('enrollment_key');
        @endphp
        <form method="get" action="{{ route('finance.student-payment') }}#student-payment-quote" data-payment-selection>
        <input type="hidden" name="student" value="{{ $student->id }}">
        <input type="hidden" name="months" value="{{ $months }}">
        <input type="hidden" name="select_programs" value="1">
        <div class="table-responsive student-payment-program-table">
            <table class="table mb-0">
                <thead><tr><th scope="col"><input type="checkbox" class="form-check-input" data-payment-select-all aria-label="{{ __('Select all programs') }}" @checked(count($selection) === $choices->count())></th><th scope="col">{{ __('Program') }}</th><th scope="col">{{ __('Class / level') }}</th><th scope="col">{{ __('Session') }}</th><th scope="col">{{ __('Months covered') }}</th><th scope="col" class="text-end">{{ __('Amount') }}</th></tr></thead>
                <tbody>
                    @foreach($choices as $key => $class)
                        @php
                            $amounts = [];
                            foreach ($tasksByClass->get($class->id, []) as $task) {
                                $currency = $task['rate']->currency;
                                $amounts[$currency] = ($amounts[$currency] ?? 0) + $task['amount'];
                            }
                            foreach ($existingByEnrollment->get($key, []) as $invoice) {
                                $amounts[$invoice->currency] = ($amounts[$invoice->currency] ?? 0) + $invoice->balanceCents();
                            }
                        @endphp
                        <tr>
                            <td><input type="checkbox" class="form-check-input" name="enrollments[]" value="{{ $key }}" aria-label="{{ __('Include :program :class :session', ['program' => $class->program, 'class' => $class->name, 'session' => $class->session]) }}" @checked(in_array($key, $selection, true))></td>
                            <td><i class="bi bi-book me-2" aria-hidden="true"></i><strong>{{ $class->program }}</strong></td>
                            <td>{{ $class->name }} {{ $class->section }} · {{ __('Level') }} {{ \App\Services\StudentTuitionPayment::level($class) }}</td>
                            <td><span class="student-payment-session" data-session="{{ strtolower($class->session ?? '') }}"><i class="bi bi-{{ $class->session === 'Evening' ? 'moon-stars' : 'sun' }}" aria-hidden="true"></i> {{ $class->session }}</span></td>
                            <td><i class="bi bi-calendar-event me-1" aria-hidden="true"></i> {{ collect($quotedRows->get($key)['dates'] ?? [])->map(fn ($date) => \Illuminate\Support\Carbon::createFromFormat('!Y-m', $date)->translatedFormat('M Y'))->join(', ') ?: '—' }}</td>
                            <td class="text-end student-payment-row-amount">{{ collect($amounts)->map(fn ($amount, $currency) => \App\Support\Money::display($amount).' '.$currency)->join(' + ') ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <noscript><button class="btn btn-outline-primary mb-3">{{ __('Update payment preview') }}</button></noscript>
        </form>
    @endif
    @if($rateIssue)
        @include('school.finance._student-tuition-warning')
    @elseif($problem)
        <div class="alert alert-warning mb-0">{{ $problem }}</div>
    @elseif($quote)
        @if($existingByEnrollment->has(''))
        <ul class="list-unstyled small mb-2" aria-label="{{ __('Combined invoices') }}">
            @foreach($existingByEnrollment->get('') as $invoice)
                <li><a href="{{ route('finance.invoice', $invoice) }}">{{ $invoice->display_number }}</a> · {{ __('Existing balance') }} · {{ \App\Support\Money::display($invoice->balanceCents()) }} {{ $invoice->currency }}</li>
            @endforeach
        </ul>
        @endif
        <div class="student-payment-collection-total">
            <span><i class="bi bi-database-fill" aria-hidden="true"></i></span>
            <div>{{ __('Total to Collect') }}<strong>{{ collect($quote['totals'])->map(fn ($amount, $currency) => \App\Support\Money::display($amount).' '.$currency)->join(' + ') }}</strong></div>
            <img src="{{ asset('images/student-fees-banner.png') }}" alt="">
        </div>
        <form method="post" action="{{ route('finance.student-payment.store', $student) }}" class="student-payment-record-form" data-payment-record>
            @csrf
            <input type="hidden" name="months" value="{{ $months }}">
            <input type="hidden" name="fingerprint" value="{{ $quote['fingerprint'] }}">
            @foreach($selection as $key)
                <input type="hidden" name="enrollments[]" value="{{ $key }}">
            @endforeach
            <div><label class="form-label" for="tuition-method"><i class="bi bi-credit-card me-1" aria-hidden="true"></i>{{ __('Payment Method') }}</label><select required class="form-select" id="tuition-method" name="method">@foreach(['cash' => 'Cash', 'qr' => 'QR', 'bank_transfer' => 'Bank transfer', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(old('method') === $value)>{{ __($label) }}</option>@endforeach</select></div>
            <div><label class="form-label" for="tuition-reference"><i class="bi bi-tag me-1" aria-hidden="true"></i>{{ __('Reference (Optional)') }}</label><input class="form-control" id="tuition-reference" name="reference" maxlength="120" value="{{ old('reference') }}" placeholder="{{ __('Receipt number or note') }}"></div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ __('Collect Payment') }}</button>
            <div class="form-check student-payment-record-confirm"><input type="checkbox" class="form-check-input" name="confirmed" value="1" id="tuition-confirmed" required><label class="form-check-label" for="tuition-confirmed">{{ __('I confirm the school received the amount shown.') }}</label></div>
            <p class="text-danger mt-2 mb-0" data-payment-record-error role="alert" hidden></p>
        </form>
    @endif
    </div>
    @endfragment
</section>
<dialog class="student-payment-loading" data-payment-loading aria-labelledby="student-payment-loading-title">
    <div class="student-payment-loading-art" aria-hidden="true">
        <span><i class="bi bi-receipt-cutoff"></i></span>
        <i class="bi bi-stars student-payment-loading-star"></i>
    </div>
    <h2 id="student-payment-loading-title">{{ __('Recording your payment') }}</h2>
    <p role="status">{{ __('Preparing the tuition invoice and receipt…') }}</p>
    <div class="student-payment-loading-track" aria-hidden="true"><span></span></div>
</dialog>
@endif
@endsection

@push('scripts')
<script src="{{ asset('js/student-payment-search.js') }}?v={{ filemtime(public_path('js/student-payment-search.js')) }}" defer></script>
@endpush
