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
@if(!request()->attributes->get('finance_archive'))
    @include('school.finance._program-tuition')
@endif
<section class="panel mb-4" id="invoice-list"><div class="panel-header"><h2>{{ request()->attributes->get('finance_archive') ? 'Archived invoices' : 'Invoices & balances' }} <small>({{ $fees->total() }})</small></h2><a class="btn btn-outline-primary" href="{{ route('finance.export', request()->query() + ['archived' => request()->attributes->get('finance_archive') ? 1 : null]) }}">Export filtered invoices (CSV)</a></div><div class="panel-body"><form method="get" action="{{ url()->current() }}#invoice-list" class="finance-filters invoice-filters" data-invoice-filters>
<div><label class="form-label" for="search">{{ auth()->user()->role === 'administrator' ? 'Parent or student' : 'Student' }}</label><input class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="Name or student number" enterkeyhint="search"></div>
<div><label class="form-label" for="program">Program</label><select class="form-select" id="program" name="program"><option value="">All programs</option>@foreach(['Khmer', 'English', 'Chinese', 'Japan'] as $program)<option value="{{ $program }}" @selected(request('program') === $program)>{{ $program === 'Japan' ? 'Japanese' : $program }}</option>@endforeach</select></div>
<div><label class="form-label" for="grade">Grade</label><select class="form-select" id="grade" name="grade"><option value="">All grades</option>@foreach(range(1,12) as $grade)<option @selected((string) request('grade') === (string) $grade)>{{ $grade }}</option>@endforeach</select></div>
<div><label class="form-label" for="academic_year">Academic year</label><input class="form-control" id="academic_year" name="academic_year" value="{{ request('academic_year') }}" placeholder="2026-2027" pattern="[0-9]{4}-[0-9]{4}"></div>
<div><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option>@foreach(['unpaid', 'partially_paid', 'paid', 'overdue', 'waived', 'refunded'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
<div>
    <label class="form-label" for="invoice-duration">{{ __('Paid period') }}</label>
    <select class="form-select" id="invoice-duration" name="duration">
        <option value="">{{ __('All periods') }}</option>
        @foreach([1, 6, 12] as $duration)
            <option value="{{ $duration }}" @selected((string) request('duration') === (string) $duration)>{{ trans_choice('Paid :count month|Paid :count months', $duration) }}</option>
        @endforeach
        @if(auth()->user()->role === 'administrator')
            <option value="not_paid" @selected(request('duration') === 'not_paid')>{{ __('No payment recorded') }}</option>
        @endif
    </select>
</div>
<a class="btn btn-light invoice-filter-reset" href="{{ url()->current() }}#invoice-list">{{ __('Reset') }}</a>
<button type="submit" hidden>{{ __('Search invoices') }}</button>
</form><p class="form-text mb-0">Summary cards follow the search, program, grade and academic year. Status and paid period filter the invoice list.</p></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Invoice / student</th><th>Program / year</th><th>Net billed</th><th>Paid</th><th>Balance</th><th>Due date</th><th>Status</th><th>View</th></tr></thead><tbody>@forelse($fees as $fee)<tr><td><a href="{{ route('finance.invoice', $fee) }}">{{ $fee->display_number }}</a><br>{{ $fee->student->name }} <small>{{ $fee->student->student_number }}</small>
@if(auth()->user()->role === 'administrator' && $fee->student->parent?->role === 'parent')
    <small class="d-block text-secondary">{{ __('Parent: :name', ['name' => $fee->student->parent->name]) }}</small>
@endif
</td><td>{{ $fee->program === 'Japan' ? 'Japanese' : ($fee->program ?: 'Legacy / unclassified') }} {{ $fee->grade ? 'Grade '.$fee->grade : '' }}<br><small>{{ $fee->academic_year ?: 'Historical record' }}</small></td><td>{{ \App\Support\Money::display($fee->netCents()) }} {{ $fee->currency }}</td><td>{{ $fee->paid }} {{ $fee->currency }}</td><td><strong>{{ $fee->balance }} {{ $fee->currency }}</strong></td><td>{{ $fee->due_date->format('d M Y') }}</td><td><span class="status-badge {{ $fee->payment_status }}">{{ ucwords(str_replace('_',' ', $fee->payment_status)) }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('finance.invoice', $fee) }}" aria-label="View invoice {{ $fee->display_number }}" title="View invoice details"><i class="bi bi-eye" aria-hidden="true"></i></a></td></tr>@empty<tr><td colspan="8" class="p-4 text-center">@if(request()->attributes->get('finance_archive'))
No archived invoices match these filters. Newly paid invoices are in the current invoice list.
<a class="d-block mt-2" href="{{ route('finance.index') }}#invoice-list">View current invoices &amp; payments</a>
@else
No invoices match these filters. <a href="{{ route('finance.index') }}#invoice-list">Clear filters</a>
@endif
</td></tr>@endforelse</tbody></table></div><div class="p-3">{{ $fees->links() }}</div></section>
@endsection

@push('scripts')
    @if($summary)
        <script src="{{ asset('vendor/chart/chart.umd.min.js') }}" defer></script>
    @endif
    <script src="{{ asset('js/finance-overview.js') }}?v={{ filemtime(public_path('js/finance-overview.js')) }}" defer></script>
@endpush
