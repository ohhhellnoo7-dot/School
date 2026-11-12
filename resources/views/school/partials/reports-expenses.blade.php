<section class="panel p-4 mb-4" id="school-expenses">
    <h2 class="h4">School expenses &amp; daily money report</h2>
    <p class="text-secondary">Recorded payments minus refunds and expenses. Includes all academic years; currencies stay separate. These records are not a verified bank balance.</p>
    <div class="row g-3 mb-4">
    @forelse($cashReport['totals'] as $currency => $total)
        <div class="col-md-6"><div class="border rounded-3 p-3"><h3 class="h6">{{ $currency }} · {{ $cashReport['month']->format('F Y') }}</h3>
            <div class="d-flex justify-content-between"><span>Net receipts</span><strong>{{ \App\Support\Money::display($total['receipts'] - $total['refunds']) }}</strong></div>
            <div class="d-flex justify-content-between"><span>Expenses / money taken out</span><strong>{{ \App\Support\Money::display($total['expenses']) }}</strong></div>
            <div class="d-flex justify-content-between mt-2"><span>Remaining after expenses</span><strong>{{ \App\Support\Money::display($total['remaining']) }} {{ $currency }}</strong></div>
        </div></div>
    @empty<p>No receipts or expenses recorded for this month.</p>@endforelse
    </div>
    <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-3"><h3 class="h5 mb-0">Daily money report · {{ $cashReport['month']->format('F Y') }}</h3>
        <form method="get" class="d-flex gap-2"><input type="hidden" name="academic_year" value="{{ request('academic_year') }}"><label class="visually-hidden" for="money-report-date">Report date</label><input id="money-report-date" type="date" name="date" class="form-control" value="{{ $date }}"><button class="btn btn-outline-secondary">View day / month</button></form>
    </div>
    <p class="small text-secondary">Updated automatically from recorded transactions. Daily net = receipts − refunds − expenses. Use Print Report to print or save a daily report.</p>
    <div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Currency</th><th>Receipts</th><th>Refunds</th><th>Expenses</th><th>Daily net</th></tr></thead><tbody>
    @foreach($cashReport['days'] as $day => $currencies)
        @if($day <= today()->toDateString())
        @forelse($currencies as $currency => $amounts)
            <tr @if($day === $date) class="table-active" @endif><td>{{ $day }}</td><td>{{ $currency }}</td>@foreach(['receipts','refunds','expenses'] as $key)<td>{{ \App\Support\Money::display($amounts[$key]) }}</td>@endforeach<td>{{ \App\Support\Money::display($amounts['receipts']-$amounts['refunds']-$amounts['expenses']) }}</td></tr>
        @empty<tr><td>{{ $day }}</td><td colspan="5" class="text-secondary">No transactions</td></tr>@endforelse
        @endif
    @endforeach
    </tbody></table></div>
    <h3 class="h5 mt-4">Expenses on {{ $date }}</h3>
    <div class="table-responsive"><table class="table"><thead><tr><th>Amount</th><th>Note</th><th>Recorded by</th></tr></thead><tbody>
    @forelse($dailyExpenses as $expense)<tr><td>{{ \App\Support\Money::display(\App\Support\Money::cents($expense->amount)) }} {{ $expense->currency }}</td><td>{{ $expense->note ?: 'No note' }}</td><td>{{ $expense->recorder?->name }}</td></tr>
    @empty<tr><td colspan="3">No expenses recorded for this day.</td></tr>@endforelse
    </tbody></table></div>
</section>
