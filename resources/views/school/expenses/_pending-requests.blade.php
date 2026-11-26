@if($advanceRequests->isNotEmpty())
    <section class="border rounded p-3 mb-4" aria-labelledby="teacher-advance-requests-title">
        <h3 class="h6" id="teacher-advance-requests-title">{{ __('Pending money requests') }}</h3>
        <ul class="list-group list-group-flush">
            @foreach($advanceRequests as $advanceRequest)
                <li class="list-group-item px-0 d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <strong>{{ $advanceRequest->salary_month ? __('Early salary') : __('Salary advance') }}</strong>
                        <div>{{ __(':teacher requested :amount', [
                            'teacher' => $advanceRequest->teacher?->name ?? __('Unavailable teacher'),
                            'amount' => '$'.\App\Support\Money::display($advanceRequest->amount_cents),
                        ]) }}</div>
                        @if($advanceRequest->salary_month)
                            <small class="text-secondary">{{ __('Scheduled payday :date', ['date' => $advanceRequest->salary_month->translatedFormat('j F Y')]) }}</small>
                            @if($advanceRequest->approved_at)<span class="badge text-bg-success">{{ __('Approved · awaiting payout') }}</span>@endif
                        @else
                            @include('school.expenses._repayment-plan', ['amountCents' => $advanceRequest->amount_cents, 'months' => $advanceRequest->repayment_months])
                        @endif
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post" action="{{ route('reports.expenses.advance-requests.reject', $advanceRequest) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('Reject') }}</button>
                        </form>
                        <form method="post" action="{{ route($advanceRequest->salary_month && !$advanceRequest->approved_at ? 'reports.expenses.advance-requests.approve' : 'reports.expenses.advance-requests.confirm', $advanceRequest) }}">
                            @csrf
                            <button class="btn btn-sm btn-primary" type="submit" @disabled(!$advanceRequest->teacher || $advanceRequest->salary_month?->lte(today()))>{{ $advanceRequest->salary_month ? ($advanceRequest->approved_at ? __('Record payout') : __('Approve')) : __('Approve and record payout') }}</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
        <p class="small text-secondary mb-0 mt-2">{{ __('Record payouts after handing the money to the employee.') }}</p>
    </section>
@endif
