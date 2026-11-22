@extends('school.layout')
@section('title', __('Invoice Details').' · '.$fee->display_number)

@push('styles')
    <link href="{{ asset('css/student-workspace.css') }}?v={{ filemtime(public_path('css/student-workspace.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    $balance = $fee->balance;
    $settled = $fee->balanceCents() <= 0;
    $due = $fee->due_date?->translatedFormat('d M Y');
    $program = $fee->program === 'Japan' ? __('Japanese') : ($fee->program ?: __('Legacy fee'));
    $plan = $fee->payment_plan ? __(ucfirst($fee->payment_plan).' Plan') : __('Original invoice');
    $net = \App\Support\Money::decimal($fee->netCents());
@endphp

<div class="student-timetable student-invoice">
    <nav class="si-breadcrumb" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ route('dashboard') }}" aria-label="{{ __('Dashboard') }}"><i class="bi bi-house-door" aria-hidden="true"></i></a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('dashboard', ['section' => 'fees']) }}">{{ __('My Fees') }}</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">{{ __('Invoice Details') }}</span>
    </nav>
    <header class="student-timetable-hero si-hero">
        <span class="st-calendar-icon"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></span>
        <div>
            <h1>{{ __('Invoice Details') }}</h1>
            <p>{{ __('View your tuition invoice, payment status, and balance.') }}</p>
        </div>
    </header>

    <div class="si-overview">
        <section class="si-card si-balance">
            <span class="si-icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
            <div>
                <h2>{{ __('Remaining Balance') }}</h2>
                <strong>{{ $balance }} <small>{{ $fee->currency }}</small></strong>
                <p>{{ $settled ? __('This invoice is fully settled.') : __('Payment due :date', ['date' => $due]) }}</p>
            </div>
        </section>
        <section class="si-card">
            <span class="si-icon"><i class="bi bi-calendar2-check" aria-hidden="true"></i></span>
            <div>
                <h2>{{ __('Next Payment Due') }}</h2>
                <strong>{{ $nextPaymentDate?->translatedFormat('d F Y') ?? __('No date scheduled') }}</strong>
                @if($nextPaymentDate)
                    <p>{{ __('Next tuition payment') }} &ndash; {{ $nextPaymentDate->translatedFormat('F Y') }}</p>
                @endif
            </div>
        </section>
        <section class="si-card si-status" data-settled="{{ $settled ? 'true' : 'false' }}">
            <span class="si-icon"><i class="bi bi-{{ $settled ? 'check-circle-fill' : 'clock-history' }}" aria-hidden="true"></i></span>
            <div>
                <h2>{{ __('Payment Status') }}</h2>
                <strong class="si-badge">{{ __(ucwords(str_replace('_', ' ', $fee->payment_status))) }}</strong>
                <p>{{ __('Invoice due: :date', ['date' => $due]) }}</p>
            </div>
        </section>
    </div>

    <dl class="si-card si-info">
        <div>
            <span class="si-icon si-avatar">
                @include('school.partials.avatar', [
                    'person' => $fee->student,
                    'photoUrl' => auth()->user()->profile_photo_path ? route('profile.photo') : $fee->student->profile_photo_url,
                    'size' => 64,
                ])
            </span>
            <div><dt>{{ __('Billed To') }}</dt><dd>{{ $fee->student->name }}</dd><span>{{ $fee->student->student_number }}</span></div>
        </div>
        <div>
            <span class="si-icon"><i class="bi bi-mortarboard" aria-hidden="true"></i></span>
            <div><dt>{{ __('Program & Session') }}</dt><dd>{{ $program }} {{ $fee->grade ? __('Grade :grade', ['grade' => $fee->grade]) : '' }}</dd>
                <span>{{ collect([$fee->session, $fee->academic_year])->filter()->join(' · ') }}</span></div>
        </div>
        <div>
            <span class="si-icon"><i class="bi bi-layers" aria-hidden="true"></i></span>
            <div><dt>{{ __('Billing Plan') }}</dt><dd>{{ $plan }}</dd><span>{{ __('Invoice due :date', ['date' => $due]) }}</span></div>
        </div>
    </dl>

    <div class="si-details">
        <section class="si-card si-panel">
            <h2><i class="bi bi-receipt" aria-hidden="true"></i>{{ __('Invoice Items') }} <small>{{ $fee->display_number }}</small></h2>
            <table class="si-items">
                <thead><tr><th scope="col">{{ __('Description') }}</th><th scope="col">{{ __('Amount') }} ({{ $fee->currency }})</th></tr></thead>
                <tbody>
                    @forelse($fee->items as $item)
                        <tr><td><i class="bi bi-mortarboard" aria-hidden="true"></i>{{ $item->description }}</td><td>{{ $item->amount }}</td></tr>
                    @empty
                        <tr><td><i class="bi bi-mortarboard" aria-hidden="true"></i>{{ $fee->description }}</td><td>{{ $fee->amount }}</td></tr>
                    @endforelse
                    @foreach($fee->adjustments as $adjustment)
                        <tr>
                            <td>{{ __(ucfirst($adjustment->kind)) }} &middot; {{ $adjustment->reason }}</td>
                            <td>{{ in_array($adjustment->kind, ['discount', 'waiver', 'credit']) ? '−' : '' }}{{ $adjustment->amount }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="si-total"><span>{{ __('Total Amount') }}</span><strong>{{ $net }} {{ $fee->currency }}</strong></div>
        </section>

        <section class="si-card si-panel">
            <h2><i class="bi bi-credit-card" aria-hidden="true"></i>{{ __('Payment Summary') }}</h2>
            <dl class="si-summary">
                <div><dt>{{ __('Original Invoice') }}</dt><dd>{{ $fee->amount }} {{ $fee->currency }}</dd></div>
                <div><dt>{{ __('Net Billed') }}</dt><dd>{{ $net }} {{ $fee->currency }}</dd></div>
                <div><dt>{{ __('Net Paid') }}</dt><dd>{{ $fee->paid }} {{ $fee->currency }}</dd></div>
            </dl>
            <div class="si-total si-remaining" data-settled="{{ $settled ? 'true' : 'false' }}"><span>{{ __('Remaining Balance') }}</span><strong>{{ $balance }} {{ $fee->currency }}</strong></div>
            @if(!$settled && $fee->currency === 'USD')
                <a class="btn btn-primary mt-3" href="{{ route('fees.pay', $fee) }}">{{ __('Pay invoice') }}</a>
            @endif
        </section>
    </div>

    @if($fee->payments->isNotEmpty())
        <details class="si-card si-panel">
            <summary>{{ __('Payment History') }}</summary>
            <ul class="student-workspace-list">
                @foreach($fee->payments as $payment)
                    <li><strong>{{ $payment->paid_on->translatedFormat('d M Y') }}</strong>
                        <span>{{ $payment->amount }} {{ $fee->currency }}</span>
                        <a href="{{ route('finance.receipt', $payment) }}">{{ $payment->receipt_number }}</a></li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
@endsection
