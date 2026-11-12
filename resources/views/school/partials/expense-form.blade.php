    <h3 class="h5">Record money taken out</h3>
    <form method="post" action="{{ route('reports.expenses.store') }}" class="row g-3 mb-4">
        @csrf
        <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
        <div class="col-sm-3"><label for="expense-amount" class="form-label">Amount</label><input id="expense-amount" name="amount" type="number" min="0.01" step="0.01" class="form-control" placeholder="10.00" value="{{ old('amount') }}" required></div>
        <div class="col-sm-3"><label for="expense-currency" class="form-label">Currency</label><select id="expense-currency" name="currency" class="form-select">@foreach(['USD','KHR'] as $currency)<option @selected(old('currency', $currency) === $currency)>{{ $currency }}</option>@endforeach</select></div>
        <div class="col-sm-6"><label for="expense-date" class="form-label">Expense date</label><input id="expense-date" name="spent_on" type="date" max="{{ today()->toDateString() }}" value="{{ old('spent_on', $date) }}" class="form-control" required></div>
        <div class="col-12"><label for="expense-note" class="form-label">Reason / note (optional)</label><input id="expense-note" name="note" maxlength="1000" class="form-control" value="{{ old('note') }}" placeholder="For example: Buy a new chair for students"></div>
        <div class="col-12"><button class="btn btn-primary">Record expense</button><a class="btn btn-outline-secondary ms-2" href="{{ route('reports') }}">Cancel</a><small class="d-block text-secondary mt-2">Records an expense only; does not transfer money or change students' paid fees.</small></div>
    </form>
