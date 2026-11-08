<x-scholar-hero tag="div" class="page-heading finance-hero"><div><div class="eyebrow">SCHOOL FINANCE</div><h1>@yield('finance-title', 'School Fees & Payments')</h1><p>Clear balances. Separate programs. Every payment accounted for.</p></div><div class="d-flex gap-2 flex-wrap"><a class="btn btn-outline-primary" href="{{ route('finance.index') }}#invoice-list">Invoices & payments</a>
@if(auth()->user()->role === 'administrator')
@php($bulkState = app(\App\Services\BulkTuitionPaymentService::class)->availability())
<div class="dropdown"><button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-bulk-pay
@disabled($bulkState['locked'])
@if($bulkState['locked']) data-unlock-at="{{ $bulkState['next_on']->toIso8601String() }}" title="Next payment: {{ $bulkState['next_on']->format('d F Y') }}"
@endif><i class="bi bi-wallet2" aria-hidden="true"></i> Pay</button><ul class="dropdown-menu">
@foreach([1,6,12] as $months)<li><a class="dropdown-item" data-quick-pay href="{{ route('finance.bulk.preview',['months'=>$months]) }}">Pay {{ $months }} {{ $months === 1 ? 'month' : 'months' }} &middot; all families</a></li>
@endforeach</ul>
@if($bulkState['locked'])<small class="d-block mt-1" data-bulk-next><a href="{{ route('finance.bulk.preview') }}">Next: {{ $bulkState['next_on']->format('d M Y') }}</a></small>
@endif</div>
<a class="btn btn-outline-secondary" href="{{ route('finance.archive') }}">Archived fees</a><a class="btn btn-outline-primary" href="{{ route('finance.parents') }}">Parent payment status</a><a class="btn btn-outline-primary" href="{{ route('finance.billing') }}">Issue invoices</a><a class="btn btn-primary" href="{{ route('finance.settings') }}">Fee settings</a>
@endif</div></x-scholar-hero>

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
            button.disabled = false;
            button.removeAttribute('title');
            button.removeAttribute('data-unlock-at');
            button.closest('.dropdown').querySelector('[data-bulk-next]')?.remove();
        }
    });
    window.addEventListener('focus', unlock);
    setInterval(unlock, 60000);
    unlock();
})();
</script>
@endpush
@endonce
