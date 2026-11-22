@php($feeGroups = $student->fees->groupBy('currency'))
@forelse($feeGroups as $currency => $fees)
    @php($money = fn ($amount) => ($currency === 'USD' ? '$' : '').\App\Support\Money::display($amount))
    <section class="student-profile-card" aria-labelledby="student-fees-{{ $loop->index }}">
        <div class="student-profile-card-title">
            <h2 id="student-fees-{{ $loop->index }}"><i class="bi bi-coin student-fee-icon" aria-hidden="true"></i> {{ __('School fees') }} – {{ $currency }}</h2>
            <span @class(['student-profile-chip', 'student-fee-cleared' => $fees->every(fn ($fee) => $fee->balanceCents() <= 0)])>
                <i class="bi bi-wallet2" aria-hidden="true"></i> {{ __('Balance') }} {{ $money($fees->sum(fn ($fee) => max(0, $fee->balanceCents()))) }}
            </span>
        </div>
        <div class="student-profile-table-wrap">
            <table class="student-profile-table student-fee-table" aria-labelledby="student-fees-{{ $loop->index }}">
                <thead>
                    <tr><th scope="col">{{ __('Description') }}</th><th scope="col">{{ __('Amount') }}</th><th scope="col">{{ __('Paid') }}</th><th scope="col">{{ __('Due') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Balance') }}</th></tr>
                </thead>
                <tbody>
                    @foreach($fees as $fee)
                        <tr>
                            <td>
                                <div class="student-fee-description">
                                    <i class="bi bi-file-earmark-text student-profile-glyph" aria-hidden="true"></i>
                                    <div><strong>{{ $fee->description }}</strong><a href="{{ route('fees.pay', $fee) }}">{{ __('Payment details / QR') }}</a></div>
                                </div>
                            </td>
                            <td>{{ $money(\App\Support\Money::cents($fee->amount)) }}</td>
                            <td>{{ $money(\App\Support\Money::cents($fee->paid)) }}</td>
                            <td>{{ $fee->due_date?->translatedFormat('M d, Y') }}</td>
                            <td>@include('school.partials.status', ['status' => $fee->payment_status])</td>
                            <td>{{ $money($fee->balanceCents()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@empty
    <section class="student-profile-card" aria-labelledby="student-fees-heading">
        <div class="student-profile-card-title">
            <h2 id="student-fees-heading"><i class="bi bi-coin student-fee-icon" aria-hidden="true"></i> {{ __('School fees') }}</h2>
        </div>
        <div class="student-profile-empty"><i class="bi bi-receipt" aria-hidden="true"></i> {{ __('No fees assigned.') }}</div>
    </section>
@endforelse
