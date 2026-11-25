<div class="modal fade d-print-none" id="outstanding-advances-modal" tabindex="-1" aria-labelledby="outstanding-advances-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="outstanding-advances-title"><i class="bi bi-wallet2 me-2" aria-hidden="true"></i>{{ __('Outstanding advances') }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            @fragment('teacher-outstanding-advances')
            <div class="modal-body" data-outstanding-advance-body>
                <p class="text-secondary">{{ __('Each row shows one advance with an unpaid balance. Repayments reflect recorded salary payments.') }}</p>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Employee') }}</th>
                                <th scope="col">{{ __('Gender') }}</th>
                                <th scope="col">{{ __('Borrowed on') }}</th>
                                <th scope="col">{{ __('Repayment term') }}</th>
                                <th scope="col" class="text-end">{{ __('Borrowed (USD)') }}</th>
                                <th scope="col" class="text-end">{{ __('Repaid via salary (USD)') }}</th>
                                <th scope="col" class="text-end">{{ __('Still owed (USD)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($outstandingAdvances->groupBy('employee_id') as $employeeAdvances)
                                @foreach($employeeAdvances as $advance)
                                    @php
                                        $borrowed = \App\Support\Money::cents($advance->amount);
                                        $repaid = $advance->advance_recovered_cents;
                                    @endphp
                                    <tr data-outstanding-advance="{{ $advance->id }}">
                                        <th scope="row">{{ $advance->employee?->name ?? __('Unavailable') }}</th>
                                        <td>{{ match (strtolower(trim($advance->employee?->gender ?? ''))) {
                                            'male' => __('Male'),
                                            'female' => __('Female'),
                                            'other' => __('Other'),
                                            default => '—',
                                        } }}</td>
                                        <td class="text-nowrap">{{ $advance->spent_on->translatedFormat('M j, Y') }}</td>
                                        <td class="text-nowrap">{{ trans_choice(':count month|:count months', $advance->repayment_months) }}</td>
                                        <td class="text-end text-nowrap">${{ \App\Support\Money::display($borrowed) }}</td>
                                        <td class="text-end text-nowrap">${{ \App\Support\Money::display($repaid) }}</td>
                                        <td class="text-end text-nowrap fw-bold">${{ \App\Support\Money::display($borrowed - $repaid) }}</td>
                                    </tr>
                                @endforeach
                                @php
                                    $totalBorrowed = $employeeAdvances->sum(fn ($advance) => \App\Support\Money::cents($advance->amount));
                                    $totalRepaid = $employeeAdvances->sum('advance_recovered_cents');
                                @endphp
                                <tr class="table-active fw-bold">
                                    <th scope="row" colspan="4">{{ __('Total for :employee', ['employee' => $employeeAdvances->first()->employee?->name ?? __('Unavailable')]) }}</th>
                                    <td class="text-end text-nowrap">${{ \App\Support\Money::display($totalBorrowed) }}</td>
                                    <td class="text-end text-nowrap">${{ \App\Support\Money::display($totalRepaid) }}</td>
                                    <td class="text-end text-nowrap">${{ \App\Support\Money::display($totalBorrowed - $totalRepaid) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-secondary py-4">{{ __('No employees have outstanding salary advances.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endfragment
        </div>
    </div>
</div>
