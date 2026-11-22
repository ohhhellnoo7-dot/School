@php
    $tabs = [
        'finance.index' => ['Invoices & payments', 'receipt', 'View invoice balances, payments, and receipts.'],
        'finance.archive' => ['Archived fees', 'archive', 'Look up archived invoices and payment history.'],
        'finance.parents' => ['Parent payment status', 'person-check', 'Review each family’s balances and next payment dates.'],
    ];
    $currentTab = match (true) {
        request()->routeIs('finance.bulk.*') => 'pay',
        request()->routeIs('finance.tuition.*') => 'finance.settings',
        request()->routeIs('finance.parents.*') => 'finance.parents',
        request()->routeIs('finance.invoice', 'finance.receipt') => 'finance.index',
        default => request()->route()?->getName(),
    };
    $note = match ($currentTab) {
        'pay' => 'Review and record tuition payments for all families.',
        'finance.billing' => 'Create invoices using approved fee settings.',
        'finance.settings' => 'Review tuition rates, approve revisions, and see which versions they replaced.',
        default => $tabs[$currentTab][2] ?? null,
    };
    $isFinanceAdmin = auth()->user()->role === 'administrator';
    $bulkState = $isFinanceAdmin ? app(\App\Services\BulkTuitionPaymentService::class)->availability() : null;
@endphp

<div class="page-heading finance-hero">
    <div class="finance-hero-copy">
        @if($currentTab === 'finance.parents')
            <span class="finance-hero-wallet"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
        @endif
        <div class="eyebrow">SCHOOL FINANCE</div>
        <h1>@yield('finance-title', 'School Fees & Payments')</h1>
        @if($note)
            <p>{{ __($note) }}</p>
        @endif
    </div>
    <nav class="finance-hero-nav" aria-label="{{ __('School finance') }}">
        @foreach($tabs as $route => [$label, $icon])
            @continue(! $loop->first && ! $isFinanceAdmin)
            <a class="btn btn-outline-primary" href="{{ route($route) }}{{ $loop->first ? '#invoice-list' : '' }}"
                @if($currentTab === $route) aria-current="page" @endif>
                <i class="bi bi-{{ $icon }}" aria-hidden="true"></i> {{ __($label) }}
            </a>
            @if($loop->first && $bulkState)
                <div class="dropdown">
                    <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bulk-pay
                        @if($currentTab === 'pay') aria-current="page" @endif
                        @if($bulkState['locked']) data-unlock-at="{{ $bulkState['next_on']->toIso8601String() }}" @endif>
                        <i class="bi bi-credit-card" aria-hidden="true"></i> {{ __('Pay') }}
                    </button>
                    <ul class="dropdown-menu">
                        @if($bulkState['locked'])
                            <li data-bulk-next><span class="dropdown-item-text">{{ __('Bulk payments reopen on :date.', ['date' => $bulkState['next_on']->translatedFormat('d M Y')]) }}</span></li>
                        @endif
                        <li><a class="dropdown-item" href="{{ route('finance.bulk.preview') }}">{{ __('View bulk payment details') }}</a></li>
                        @foreach([1, 6, 12] as $months)
                            <li data-bulk-option @if($bulkState['locked']) hidden @endif>
                                <a class="dropdown-item" data-quick-pay href="{{ route('finance.bulk.preview', ['months' => $months]) }}">{{ trans_choice('Pay :count month|Pay :count months', $months) }} &middot; {{ __('all families') }}</a>
                            </li>
                        @endforeach
                    </ul>
                    @if($bulkState['locked'])
                        <small class="d-block mt-1" data-bulk-next><a href="{{ route('finance.bulk.preview') }}">{{ __('Next: :date', ['date' => $bulkState['next_on']->translatedFormat('d M Y')]) }}</a></small>
                    @endif
                </div>
            @endif
        @endforeach
    </nav>
</div>

@once
@push('scripts')
<script>
(() => {
    let paying = false;
    document.querySelectorAll('[data-quick-pay]').forEach(link => link.addEventListener('click', async event => {
        event.preventDefault();
        if (paying) return;
        paying = true;
        const original = link.textContent;
        link.textContent = 'Loading payment total...';
        try {
            const response = await fetch(link.href, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Unable to load payment details. Please try again.');
            const plan = await response.json();
            if (plan.locked || plan.errors.length) {
                alert(plan.locked ? 'Payment is locked. Open Pay to view the next eligible date.' : plan.errors.join('\n'));
                return;
            }
            const message = `Record ${plan.payload.months}-month payments for ${plan.families} families and ${plan.children} children?\n\nTotal: ${plan.totals.join(' + ')}\n\nConfirm only if the school received these payments. This creates paid invoices. Next Pay date: ${plan.next_date}.`;
            if (!window.confirm(message)) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = @json(route('finance.bulk.pay'));
            const values = {...plan.payload, method: 'other', confirmed: '1', _token: document.querySelector('meta[name="csrf-token"]').content};
            Object.entries(values).forEach(([name, value]) => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = name; input.value = value; form.append(input);
            });
            document.body.append(form);
            form.submit();
        } catch (error) {
            alert(error.message);
        } finally {
            paying = false;
            link.textContent = original;
        }
    }));
    const unlock = () => document.querySelectorAll('[data-bulk-pay][data-unlock-at]').forEach(button => {
        if (Date.now() >= Date.parse(button.dataset.unlockAt)) {
            button.removeAttribute('title');
            button.removeAttribute('data-unlock-at');
            const dropdown = button.closest('.dropdown');
            dropdown.querySelectorAll('[data-bulk-next]').forEach(note => note.remove());
            dropdown.querySelectorAll('[data-bulk-option]').forEach(option => { option.hidden = false; });
        }
    });
    window.addEventListener('focus', unlock);
    setInterval(unlock, 60000);
    unlock();
})();
</script>
@endpush
@endonce
