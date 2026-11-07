@extends('school.layout')
@section('title', 'Parent payment status')
@section('finance-title', 'Parent payment status')
@section('content')
@include('school.finance._nav')
@php($labels = ['paid' => 'Fully paid', 'partially_paid' => 'Partly paid', 'unpaid' => 'Unpaid', 'waived' => 'Waived / no charge', 'no_invoices' => 'No invoices'])
<div class="row g-3 mb-4">@foreach($labels as $status => $label)<div class="col-sm-6 col-xl"><a class="stat-card d-block text-decoration-none h-100" href="{{ route('finance.parents', array_filter(['search' => request('search'), 'status' => $status])) }}" @if(request('status') === $status) aria-current="page" @endif><div class="stat-top">{{ $label }}</div><div class="stat-value">{{ $counts[$status] ?? 0 }}</div><small>Parent accounts</small></a></div>@endforeach</div>
<section class="panel"><div class="panel-header"><h2>Family fee balances</h2></div><div class="panel-body"><form method="get" class="finance-filters"><div><label class="form-label" for="parent-search">Parent, child or student number</label><input id="parent-search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Search families"></div><div><label class="form-label" for="family-status">Payment status</label><select id="family-status" name="status" class="form-select"><option value="">All parents</option>@foreach($labels as $status => $label)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $label }}</option>@endforeach</select></div><div class="d-flex align-items-end gap-2"><button class="btn btn-primary">Apply</button><a class="btn btn-light" href="{{ route('finance.parents') }}">Reset</a></div></form><p class="form-text mb-0">Status covers all issued fees for all linked children, across programs and years. It identifies the responsible family, not who physically made a payment. Fully paid means every invoice is settled; waived charges and families without invoices are shown separately. USD and KHR are never combined.</p>
@if($unlinked)<p class="form-text mt-2 mb-0">{{ $unlinked }} students have invoices but no linked parent account. <a href="{{ route('students.index') }}">Link their parent in Students</a> to include them here.</p>@endif</div>
<div class="table-responsive"><table class="table"><thead><tr><th>Parent / contact</th><th>Children / invoices</th><th>Status</th><th>Net billed</th><th>Net paid</th><th>Outstanding</th><th>Overdue</th><th>Next payment date</th><th>View</th></tr></thead><tbody>@forelse($families as $family)
<tr><td><strong>{{ $family['parent']->name }}</strong><br><small>{{ $family['parent']->email }}</small><br><small>{{ $family['parent']->phone }}</small></td><td>@forelse($family['parent']->children as $child)<div>{{ $child->name }} <small>{{ $child->student_number }}</small><details><summary>{{ $child->fees->count() }} invoices</summary>@foreach($child->fees as $fee)<div><a href="{{ route('finance.invoice', $fee) }}">{{ $fee->display_number }}</a><br><small>{{ $fee->program ?: 'Legacy fee' }} &middot; {{ $fee->balance }} {{ $fee->currency }} remaining</small></div>@endforeach</details></div>@empty<span class="form-text">No linked children</span>@endforelse</td><td><span class="status-badge {{ $family['status'] }}">{{ $labels[$family['status']] }}</span></td>
@foreach(['billed','paid','balance'] as $key)<td>@forelse($family['currencies'] as $currency => $totals)<div>{{ \App\Support\Money::display($totals[$key]) }} {{ $currency }}</div>@empty&mdash;@endforelse</td>@endforeach
<td>@forelse($family['currencies'] as $currency => $totals)<div>{{ \App\Support\Money::display($totals['overdue']) }} {{ $currency }} overdue</div>@empty&mdash;@endforelse</td>
@php($schedule = $paymentSchedules[$family['parent']->id])
<td>@if($schedule['due'])<div><strong>{{ \Carbon\Carbon::parse($schedule['due'])->format('d F Y') }}</strong><br><small>Unpaid invoice due{{ $schedule['due'] < today()->toDateString() ? ' (overdue)' : '' }}</small></div>@endif
@if($schedule['scheduled'])<div class="mt-2"><strong>{{ \Carbon\Carbon::parse($schedule['scheduled'])->format('d F Y') }}</strong><br><small>{{ $schedule['scheduled_source'] === 'school' ? 'School-set next payment' : 'Next scheduled tuition - invoice not issued' }}</small></div>@elseif(!$schedule['due'])<span class="form-text">No next payment date configured</span>@endif</td><td><button type="button" class="btn btn-sm btn-outline-primary" data-payment-detail="payment-detail-{{ $family['parent']->id }}" aria-label="View payment dates for {{ $family['parent']->name }}" title="View payment dates" aria-haspopup="dialog"><i class="bi bi-eye" aria-hidden="true"></i></button></td></tr>
@empty<tr><td colspan="9" class="p-4 text-center">No parents match these filters.</td></tr>@endforelse</tbody></table></div><div class="p-3">{{ $families->links() }}</div></section>
@foreach($families as $family)
@php($schedule = $paymentSchedules[$family['parent']->id])
<dialog id="payment-detail-{{ $family['parent']->id }}" class="family-payment-dialog" aria-labelledby="payment-title-{{ $family['parent']->id }}">
<div class="d-flex justify-content-between align-items-center gap-3"><h2 class="h5 mb-0" id="payment-title-{{ $family['parent']->id }}">Payment dates</h2><button type="button" class="btn btn-light" data-close-payment-detail aria-label="Close payment dates"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div>
<p class="mt-3"><strong>{{ $family['parent']->name }}</strong><br><span class="status-badge {{ $family['status'] }}">{{ $labels[$family['status']] }}</span></p>
<section class="mb-4"><h3 class="h6">When they paid</h3>
@forelse($schedule['history'] as $entry)<div class="mb-3"><strong>{{ \Carbon\Carbon::parse($entry['date'])->format('d F Y') }}</strong><br><span>{{ $entry['child'] }} &middot; {{ $entry['amount'] }} {{ $entry['currency'] }}</span><br><a href="{{ route('finance.invoice', $entry['fee_id']) }}">View invoice</a>@if($entry['legacy'])<small class="d-block form-text">Last payment date recorded on the historical fee. Amount is its opening paid total, not a separate transaction.</small>@endif</div>@empty<p class="form-text">No recorded payment date available.</p>@endforelse
</section>
@foreach(['due' => 'Next unpaid invoice', 'scheduled' => 'When they pay next'] as $key => $label)
<section class="mb-4"><h3 class="h6">{{ $label }}</h3>@if($schedule[$key])
@php($paymentDate = \Carbon\Carbon::parse($schedule[$key]))
<p><strong>{{ $paymentDate->format('l, d F Y') }}</strong></p><dl class="family-payment-date"><div><dt>Day</dt><dd>{{ $paymentDate->format('d') }}</dd></div><div><dt>Month</dt><dd>{{ $paymentDate->format('F') }}</dd></div><div><dt>Year</dt><dd>{{ $paymentDate->format('Y') }}</dd></div></dl>
<p class="form-text">{{ $key === 'scheduled' ? ($schedule['scheduled_source'] === 'school' ? 'Next payment date set by the school.' : 'Based on the approved tuition schedule. This invoice has not been issued yet.') : ($paymentDate->lt(today()) ? 'This unpaid invoice is overdue.' : 'Payment is due on this date.') }}</p>
@else<p class="form-text">{{ $key === 'due' ? 'No unpaid invoices.' : 'No next payment date configured.' }}</p>@endif</section>
@endforeach
<section class="mb-4"><h3 class="h6">Set next payment date</h3><p class="form-text">For older fees without a billing schedule, enter the agreed next date. This saves a reminder without creating a charge.</p><form method="post" action="{{ route('finance.parents.next-payment', $family['parent']) }}">@csrf<label class="form-label" for="next-payment-{{ $family['parent']->id }}">Next payment (day, month, year)</label><input id="next-payment-{{ $family['parent']->id }}" class="form-control mb-2" type="date" name="next_payment_on" min="{{ today()->toDateString() }}" value="{{ $schedule['manual_date'] }}"><button class="btn btn-primary" type="submit">Save next date</button><small class="form-text d-block">Leave blank and save to clear the school-set reminder.</small></form></section>
<section><h3 class="h6">Academic years with all issued invoices fully paid</h3>@forelse($schedule['paid_years'] as $year)<span class="status-badge paid me-2">{{ $year }}</span>@empty<p class="form-text">No fully paid academic year recorded. Historical invoices without an academic year cannot confirm year coverage.</p>@endforelse<p class="form-text mt-2">This covers issued invoices only. Future months may still need to be billed.</p></section>
</dialog>
@endforeach
@endsection
@push('scripts')
<script>
(() => {
    document.querySelectorAll('[data-payment-detail]').forEach(button => button.addEventListener('click', () => {
        document.getElementById(button.dataset.paymentDetail).showModal();
    }));
    document.querySelectorAll('[data-close-payment-detail]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
})();
</script>
@endpush
