<form class="parent-fees-invoice-filters" method="get" action="{{ route('finance.index') }}#invoice-list" data-invoice-filters>
    @foreach(request()->only(['search', 'program', 'grade', 'academic_year']) as $filter => $value)
        <input type="hidden" name="{{ $filter }}" value="{{ $value }}">
    @endforeach
    <label>
        <i class="bi bi-calendar3" aria-hidden="true"></i>
        <select class="form-select" name="duration" aria-label="{{ __('Paid period') }}">
            <option value="">{{ __('All Periods') }}</option>
            @foreach([1, 6, 12] as $months)
                <option value="{{ $months }}" @selected((string) request('duration') === (string) $months)>{{ trans_choice('Paid :count month|Paid :count months', $months) }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <select class="form-select" name="status" aria-label="{{ __('Invoice status') }}">
            <option value="">{{ __('All Statuses') }}</option>
            @foreach(['unpaid', 'partially_paid', 'paid', 'overdue', 'waived', 'refunded'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ __(ucwords(str_replace('_', ' ', $status))) }}</option>
            @endforeach
        </select>
    </label>
    <noscript><button type="submit" class="btn btn-outline-primary">{{ __('Filter invoices') }}</button></noscript>
</form>
