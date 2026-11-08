@extends('school.layout')
@section('title', 'School Fees & Payments')
@section('finance-title', request()->attributes->get('finance_archive') ? 'Archived invoices' : 'School Fees & Payments')
@section('content')
@include('school.finance._nav')
@if(request()->attributes->get('finance_archive'))
<div class="alert alert-info"><p>Archived invoices are preserved history only. Newly paid invoices appear in Invoices &amp; payments.</p><a class="btn btn-primary" href="{{ route('finance.index') }}#invoice-list">View current invoices &amp; payments</a></div>

@endif

@if($studentSummaries->isNotEmpty())<div class="finance-summary-grid mb-4">@foreach($studentSummaries as $studentSummary)<section class="panel p-4"><h2 class="h5">{{ $studentSummary['student']->name }}</h2><p class="form-text">{{ $studentSummary['student']->student_number }}</p>@foreach($studentSummary['currencies'] as $currency => $balance)<p>Remaining balance: <strong>{{ \App\Support\Money::display($balance['balance']) }} {{ $currency }}</strong></p><p>Next unpaid due date: <strong>{{ $balance['next_due'] ?: 'No unpaid invoices' }}</strong></p>@endforeach</section>@endforeach</div>@endif
@if(!request()->attributes->get('finance_archive') || $fees->isNotEmpty())
@include('school.finance._summary')
@endif
<section class="panel mb-4" id="invoice-list"><div class="panel-header"><h2>{{ request()->attributes->get('finance_archive') ? 'Archived invoices' : 'Invoices & balances' }} <small>({{ $fees->total() }})</small></h2><a class="btn btn-outline-primary" href="{{ route('finance.export', request()->query() + ['archived' => request()->attributes->get('finance_archive') ? 1 : null]) }}">Export filtered invoices (CSV)</a></div><div class="panel-body"><form method="get" class="finance-filters">
<div><label class="form-label" for="search">Student name or number</label><input class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="Search students"></div>
<div><label class="form-label" for="program">Program</label><select class="form-select" id="program" name="program"><option value="">All programs & legacy</option>@foreach(['Khmer', 'English', 'Chinese', 'Japan'] as $program)<option value="{{ $program }}" @selected(request('program') === $program)>{{ $program === 'Japan' ? 'Japanese' : $program }}</option>@endforeach</select></div>
<div><label class="form-label" for="grade">Grade</label><select class="form-select" id="grade" name="grade"><option value="">All grades</option>@foreach(range(1,12) as $grade)<option @selected((string) request('grade') === (string) $grade)>{{ $grade }}</option>@endforeach</select></div>
<div><label class="form-label" for="academic_year">Academic year</label><input class="form-control" id="academic_year" name="academic_year" value="{{ request('academic_year') }}" placeholder="2026-2027" pattern="[0-9]{4}-[0-9]{4}"></div>
<div><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option>@foreach(['unpaid', 'partially_paid', 'paid', 'overdue', 'waived', 'refunded'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
<div class="d-flex gap-2 align-items-end"><button class="btn btn-primary">Apply</button><a class="btn btn-light" href="{{ route('finance.index') }}#invoice-list">Reset</a></div>
</form><p class="form-text mb-0">Summary cards follow student, program, grade and year filters. Status filters the invoice table.</p></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Invoice / student</th><th>Program / year</th><th>Net billed</th><th>Paid</th><th>Balance</th><th>Due date</th><th>Status</th><th>View</th></tr></thead><tbody>@forelse($fees as $fee)<tr><td><a href="{{ route('finance.invoice', $fee) }}">{{ $fee->display_number }}</a><br>{{ $fee->student->name }} <small>{{ $fee->student->student_number }}</small></td><td>{{ $fee->program === 'Japan' ? 'Japanese' : ($fee->program ?: 'Legacy / unclassified') }} {{ $fee->grade ? 'Grade '.$fee->grade : '' }}<br><small>{{ $fee->academic_year ?: 'Historical record' }}</small></td><td>{{ \App\Support\Money::display($fee->netCents()) }} {{ $fee->currency }}</td><td>{{ $fee->paid }} {{ $fee->currency }}</td><td><strong>{{ $fee->balance }} {{ $fee->currency }}</strong></td><td>{{ $fee->due_date->format('d M Y') }}</td><td><span class="status-badge {{ $fee->payment_status }}">{{ ucwords(str_replace('_',' ', $fee->payment_status)) }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('finance.invoice', $fee) }}" aria-label="View invoice {{ $fee->display_number }}" title="View invoice details"><i class="bi bi-eye" aria-hidden="true"></i></a></td></tr>@empty<tr><td colspan="8" class="p-4 text-center">@if(request()->attributes->get('finance_archive'))
No archived invoices match these filters. Newly paid invoices are in the current invoice list.
<a class="d-block mt-2" href="{{ route('finance.index') }}#invoice-list">View current invoices &amp; payments</a>
@else
No invoices match these filters. <a href="{{ route('finance.index') }}#invoice-list">Clear filters</a>
@endif
</td></tr>@endforelse</tbody></table></div><div class="p-3">{{ $fees->links() }}</div></section>
@if(!request()->attributes->get('finance_archive'))
<section class="panel mb-4"><div class="panel-header"><h2>Current program tuition</h2>@if(auth()->user()->role === 'administrator')<a class="btn btn-outline-primary" href="{{ route('finance.tuition.edit') }}"><i class="bi bi-pencil-square" aria-hidden="true"></i> Edit tuition</a>@endif</div><div class="table-responsive"><table class="table"><thead><tr><th>Program</th><th>Grades / levels</th><th>Session</th><th>Monthly tuition</th><th>6 months</th><th>Annual tuition</th><th>Billable months / year</th><th>Actions</th></tr></thead><tbody>@forelse($programRates as $rates)
@php($rate = $rates->first())
@foreach($rate->ratesForSessions(\App\Models\Program::sessionMap()[$rate->program] ?? ['Morning','Afternoon','Evening']) as $price)
<tr><td>{{ $rate->program === 'Japan' ? 'Japanese' : $rate->program }}</td><td>{{ $rates->pluck('grade')->unique()->sort()->join(', ') }}</td><td>{{ implode(' / ', $price['sessions']) }}</td><td>{{ $price['monthly'] }} {{ $rate->currency }}</td><td>{{ $price['six_month'] }} {{ $rate->currency }} @if($price['package'])<small class="d-block">Package price</small>@endif</td><td>{{ $price['annual'] }} {{ $rate->currency }}</td><td>{{ $rate->billable_months }} months &middot; {{ $rate->academic_year }}</td><td>@if(auth()->user()->role === 'administrator')<a class="btn btn-sm btn-outline-primary" href="{{ route('finance.tuition.edit', ['structure' => $rate->id, 'grades' => $rates->pluck('grade')->all()]) }}"><i class="bi bi-pencil" aria-hidden="true"></i> Edit</a>@else&mdash;@endif</td></tr>
@endforeach
@empty<tr><td colspan="8" class="p-4">No approved program tuition configured yet. @if(auth()->user()->role === 'administrator')<a href="{{ route('finance.settings') }}">Review program fee settings</a>.@endif</td></tr>@endforelse</tbody></table></div><p class="form-text px-4">Approved tuition prices. Discounts and additional charges are shown on individual invoices. Historical archived fees are excluded.</p></section>
@endif
@endsection
