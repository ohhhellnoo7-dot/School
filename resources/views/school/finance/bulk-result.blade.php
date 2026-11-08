@extends('school.layout')
@section('title', 'Bulk payment receipt')
@section('finance-title', 'Bulk payments recorded')
@section('content')
@include('school.finance._nav')
<section class="panel p-4 mb-4"><h2 class="h5">Bulk payment #{{ $run->id }}</h2><p>{{ $run->parents_count }} families &middot; {{ $run->students_count }} children &middot; {{ $run->months }} months</p>
@foreach($run->totals as $currency=>$amount)<p class="stat-value">{{ $amount }} {{ $currency }}</p>
@endforeach<p>Recorded on <strong>{{ $run->created_at->format('d F Y') }}</strong>. Next Pay date: <strong>{{ $run->next_on->format('d F Y') }}</strong>.</p><a class="btn btn-outline-primary" href="{{ route('finance.parents') }}">View parent payment status</a></section>
<section class="panel"><div class="panel-header"><h2>Invoices and receipts</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>Child</th><th>Program / session</th><th>Recorded payment</th><th>Invoice / receipt</th></tr></thead><tbody>
@foreach($allocations as $entry)<tr><td>{{ $entry->name }}</td><td>{{ $entry->program === 'Japan' ? 'Japanese' : $entry->program }} &middot; {{ $entry->session }}</td><td>{{ $entry->amount ?: '0.00' }} {{ $entry->currency }}</td><td><a href="{{ route('finance.invoice',$entry->fee_id) }}">Invoice</a>
@if($entry->payment_id) &middot; <a href="{{ route('finance.receipt',$entry->payment_id) }}">Receipt</a>
@else &middot; Already settled / no charge
@endif</td></tr>
@endforeach</tbody></table></div><div class="p-3">{{ $allocations->links() }}</div></section>
@endsection
