@extends('school.layout')
@section('title', 'Pay all families')
@section('finance-title', 'Pay all families')
@section('content')
@include('school.finance._nav')
@if($plan['cycle']['locked'])<section class="panel p-4 mb-4"><h2 class="h5"><i class="bi bi-lock" aria-hidden="true"></i> Payments already recorded</h2><p>Pay is locked until <strong>{{ $plan['cycle']['next_on']->format('d F Y') }}</strong>. A different duration cannot bypass this date.</p><a class="btn btn-outline-primary" href="{{ route('finance.bulk.show', $plan['cycle']['last']) }}">View last bulk payment</a></section>
@else
<section class="panel p-4 mb-4"><h2 class="h5">Step 1: Choose how many months to pay</h2><div class="d-flex gap-2 flex-wrap">
@foreach([1,6,12] as $months)<a class="btn {{ $plan['months'] === $months ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('finance.bulk.preview',['months'=>$months]) }}"
@if($plan['months'] === $months) aria-current="page"
@endif>{{ $months }} {{ $months === 1 ? 'month' : 'months' }}</a>
@endforeach</div><p class="mt-3"><strong>Preview only ? no payment has been saved yet.</strong> Review the total, then confirm below.</p><a class="btn btn-primary mb-3" href="#confirm-payment">Continue to payment confirmation</a><p class="form-text mt-3 mb-0">Includes active students linked to parents and all their enrolled programs. Identical sessions in one class-group enrollment are charged once. This records payments received by the school; it does not charge parents' bank accounts.</p></section>
<div class="row g-3 mb-4"><div class="col-sm-4"><div class="stat-card"><div class="stat-top">Families / children</div><div class="stat-value">{{ $plan['parents_count'] }} / {{ $plan['students_count'] }}</div></div></div><div class="col-sm-4"><div class="stat-card"><div class="stat-top">Tuition months</div><strong>{{ $plan['cycle']['starts_on']->format('F Y') }}
@if($plan['months'] > 1) &ndash; {{ $plan['cycle']['starts_on']->startOfMonth()->addMonthsNoOverflow($plan['months']-1)->format('F Y') }}
@endif</strong></div></div><div class="col-sm-4"><div class="stat-card"><div class="stat-top">Pay unlocks again</div><strong>{{ $plan['cycle']['next_date']->format('d F Y') }}</strong></div></div></div>
<section class="panel p-4 mb-4"><h2 class="h5">Payment total</h2>
@forelse($plan['totals'] as $currency=>$amount)<p class="stat-value">{{ \App\Support\Money::display($amount) }} <small>{{ $currency }}</small></p>
@empty<p>No tuition amount is available.</p>
@endforelse<p class="form-text mb-0">Uses approved session prices, package discounts, scholarships and one-time charges. Previously paid months are not charged twice. Unrelated older debts are not included in this period.</p></section>
@if($plan['errors'])<section class="panel p-4 mb-4"><h2 class="h5">Review these items before paying</h2><p>No payments will be recorded until all enrollment and pricing issues are resolved.</p><details open><summary>{{ count($plan['errors']) }} items need attention</summary><ul class="mt-3">
@foreach($plan['errors'] as $error)<li>{{ $error }}</li>
@endforeach</ul></details><a class="btn btn-outline-primary" href="{{ route('finance.tuition.edit') }}">Edit tuition prices</a></section>
@endif
@include('school.finance._bulk-confirm')
<section class="panel mb-4"><div class="panel-header"><h2>Children & tuition breakdown</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>Parent / child</th><th>Program / grade</th><th>Session</th><th>Year</th><th>Amount to record</th></tr></thead><tbody>
@forelse($plan['rows'] as $row)<tr><td><strong>{{ $row['parent'] }}</strong><br>{{ $row['student'] }}</td><td>{{ $row['program'] === 'Japan' ? 'Japanese' : $row['program'] }} / {{ $row['grade'] }}</td><td>{{ $row['session'] }}</td><td>{{ $row['year'] }}<small class="d-block">{{ $row['existing'] ? 'Existing invoice' : 'New tuition invoice' }}</small></td><td>{{ $row['amount'] }} {{ $row['currency'] }}</td></tr>
@empty<tr><td colspan="5" class="p-4">No eligible tuition invoices found.</td></tr>
@endforelse</tbody></table></div></section>

@endif
@endsection
@push('scripts')<script>document.querySelector('[data-bulk-confirm]')?.addEventListener('submit', event => { const button = event.target.querySelector('button[type="submit"]'); button.disabled = true; button.textContent = 'Recording payments...'; });</script>
@endpush
