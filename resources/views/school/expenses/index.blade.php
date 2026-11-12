@extends('school.layout')
@section('title', 'Money taken out')
@section('content')
<div class="page-heading"><div><a href="{{ route('reports') }}" class="text-decoration-none"><i class="bi bi-arrow-left" aria-hidden="true"></i> Reports</a><h1>Money taken out</h1><p>Expense details for {{ $month->format('F Y') }} &middot; {{ $currency }} &middot; All academic years</p></div><a class="btn btn-primary" href="{{ route('reports.expenses.create', ['currency' => $currency]) }}"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add expense &amp; reason</a></div>
<section class="panel p-4 mb-4">
    <form method="get" class="row g-3 align-items-end">
        <div class="col-sm-5"><label for="expense-month" class="form-label">Month</label><input id="expense-month" type="month" name="month" class="form-control" value="{{ $month->format('Y-m') }}" required></div>
        <div class="col-sm-4"><label for="expense-currency" class="form-label">Currency</label><select id="expense-currency" name="currency" class="form-select">@foreach(['USD','KHR'] as $code)<option @selected($currency === $code)>{{ $code }}</option>@endforeach</select></div>
        <div class="col-sm-3"><button class="btn btn-primary">View expenses</button></div>
    </form>
    <div class="row g-3 mt-2">
        @foreach(['receipts' => 'Net receipts', 'expenses' => 'Money taken out', 'remaining' => 'Money remaining'] as $key => $label)
        <div class="col-md-4"><div class="border rounded-3 p-3"><span class="text-secondary">{{ $label }}</span><div class="h4 mt-2 mb-0">{{ \App\Support\Money::display($key === 'receipts' ? $total['receipts'] - $total['refunds'] : $total[$key]) }} {{ $currency }}</div></div></div>
        @endforeach
    </div>
</section>
<section class="panel">
    <div class="panel-header"><h2>Expense history</h2><span>{{ $expenses->total() }} records</span></div>
    <div class="table-responsive"><table class="table"><thead><tr><th>Date spent</th><th>Amount</th><th>Reason / note</th><th>Recorded by</th><th>Recorded at</th></tr></thead><tbody>
        @forelse($expenses as $expense)
        <tr><td class="text-nowrap">{{ $expense->spent_on->format('d M Y') }}</td><td class="text-nowrap">{{ \App\Support\Money::display(\App\Support\Money::cents($expense->amount)) }} {{ $expense->currency }}</td><td style="min-width:200px;white-space:pre-wrap;overflow-wrap:anywhere">{{ $expense->note ?: 'No reason provided' }}</td><td>{{ $expense->recorder?->name ?? 'Unavailable' }}</td><td class="text-nowrap">{{ $expense->created_at->format('d M Y, H:i') }}</td></tr>
        @empty<tr><td colspan="5" class="text-center p-4 text-secondary">No expenses recorded for this month and currency.</td></tr>@endforelse
    </tbody></table></div>
    <div class="p-3">{{ $expenses->links() }}</div>
</section>
@endsection
