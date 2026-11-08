@extends('school.layout')
@section('title', 'Edit program tuition')
@section('finance-title', 'Edit program tuition')
@section('content')
@include('school.finance._nav')
<section class="panel mb-4"><div class="panel-header"><h2>Choose program and grades / levels</h2></div><div class="panel-body"><form method="get" action="{{ route('finance.tuition.edit') }}" class="d-flex gap-2 flex-wrap"><div class="flex-grow-1"><label class="form-label" for="tuition-program">Program</label><select class="form-select" id="tuition-program" name="program">@foreach(['Khmer','English','Chinese','Japan'] as $name)<option value="{{ $name }}" @selected($program === $name)>{{ $name === 'Japan' ? 'Japanese' : $name }}</option>@endforeach</select></div><button class="btn btn-outline-primary align-self-end">Change program</button></form></div></section>
<form method="post" action="{{ route('finance.tuition.save') }}" data-tuition-editor>
@csrf<input type="hidden" name="package_months" value="6"><input type="hidden" name="program" value="{{ $program }}"><input type="hidden" name="academic_year" value="{{ $source->academic_year }}"><input type="hidden" name="currency" value="{{ $source->currency }}"><input type="hidden" name="billable_months" value="{{ $source->billable_months }}"><input type="hidden" name="starts_on" value="{{ $source->starts_on->format('Y-m-d') }}"><input type="hidden" name="due_day" value="{{ $source->due_day }}">
<section class="panel p-4 mb-4"><h2 class="h5">{{ $program === 'Japan' ? 'Japanese' : $program }} grades / levels</h2><p class="form-text">{{ $source->academic_year }} &middot; {{ $source->currency }} &middot; {{ $source->billable_months }} billable months. Select every grade or level that should use these prices.</p><fieldset><legend class="visually-hidden">Grades to update</legend><div class="tuition-grade-picker">@foreach($grades as $grade)<label><input class="form-check-input" type="checkbox" name="grades[]" value="{{ $grade }}" @checked(in_array($grade, old('grades', $selected)))> {{ $grade }} @if($editedGrades->contains($grade))<small class="tuition-saved-badge">Saved</small>@endif</label><input type="hidden" name="versions[{{ $grade }}]" value="{{ $versions[$grade] ?? 0 }}">@endforeach</div></fieldset><button class="btn btn-sm btn-light mt-3" type="button" data-select-tuition-grades>Select all grades</button><p class="form-text mt-3 mb-0" data-selected-tuition-grades aria-live="polite"></p>
<div class="tuition-saved-prices mt-4"><h3 class="h6">Saved grade prices &middot; {{ $program === 'Japan' ? 'Japanese' : $program }}</h3><p class="form-text">Previously edited grades for {{ $source->academic_year }}. Only the selected program is shown.</p>
@if($savedRates->isNotEmpty())<div class="table-responsive"><table class="table mb-0"><thead><tr><th>Grade / level</th><th>Session</th><th>1 month</th><th>6 months</th><th>1 year</th><th>Last saved</th><th></th></tr></thead><tbody>
@foreach($savedRates as $savedRate)
@foreach($savedRate->ratesForSessions($sessions) as $savedPrice)
<tr><td><strong>{{ $savedRate->grade }}</strong> <small class="tuition-saved-badge">Saved</small></td><td>{{ implode(' / ', $savedPrice['sessions']) }}</td><td>{{ $savedPrice['monthly'] }} {{ $savedRate->currency }}</td><td>{{ $savedPrice['six_month'] }} {{ $savedRate->currency }}</td><td>{{ $savedPrice['annual'] }} {{ $savedRate->currency }}<br><small>{{ $savedRate->billable_months }} months</small></td><td>{{ $savedRate->updated_at->format('d M Y, H:i') }}</td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('finance.tuition.edit', ['program'=>$program, 'structure'=>$savedRate->id, 'grades'=>[$savedRate->grade]]) }}">Edit grade {{ $savedRate->grade }}</a></td></tr>
@endforeach
@endforeach
</tbody></table></div>@else<p class="form-text mb-0">No grades have been edited for this program yet.</p>@endif
</div></section>
<section class="panel mb-4"><div class="panel-header"><div><h2>Session prices</h2><p>Set a monthly price and an optional discounted six-month package.</p></div></div><div class="panel-body">@if(in_array('Morning', $sessions) && in_array('Afternoon', $sessions))
<label class="d-flex gap-2 mb-4"><input type="hidden" name="same_day" value="0"><input class="form-check-input" type="checkbox" name="same_day" value="1" data-same-day @checked(old('same_day', $source->monthlyForSession('Morning') === $source->monthlyForSession('Afternoon') && $source->quarterlyForSession('Morning') === $source->quarterlyForSession('Afternoon'))) > Use the same price for Morning and Afternoon</label>
@endif
<div class="tuition-price-grid" style="--tuition-session-count: {{ max(1, count($sessions)) }}">@foreach($sessions as $session)
@php($icon = ['Morning'=>'sunrise','Afternoon'=>'sun','Evening'=>'moon-stars'][$session])<section class="tuition-session-card"><h3 class="h6"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i> {{ $session }}</h3><label class="form-label" for="{{ $session }}-monthly">1 month ({{ $source->currency }})</label><input class="form-control mb-3" id="{{ $session }}-monthly" data-session="{{ $session }}" data-price-kind="monthly" name="prices[{{ $session }}][monthly]" type="number" step="0.01" min="0" value="{{ old('prices.'.$session.'.monthly', $source->monthlyForSession($session)) }}" required><label class="form-label" for="{{ $session }}-quarterly">6-month package ({{ $source->currency }})</label><input class="form-control" id="{{ $session }}-quarterly" data-session="{{ $session }}" data-price-kind="quarterly" type="number" step="0.01" min="0" value="{{ old('prices.'.$session.'.quarterly', ($source->quarterlyForSession($session) === null ? null : $source->tuitionForMonths(6, $session))) }}" placeholder="Monthly price x 6"><input type="hidden" id="{{ $session }}-quarterly-value" name="prices[{{ $session }}][quarterly]" value="{{ old('prices.'.$session.'.quarterly', ($source->quarterlyForSession($session) === null ? null : $source->tuitionForMonths(6, $session))) }}"><small class="form-text d-block">Auto-calculated as monthly price x 6. Enter a discounted price, or clear it to return to automatic.</small><label class="form-label mt-3" for="{{ $session }}-annual">1 year ({{ $source->billable_months }} billable months, {{ $source->currency }})</label><input class="form-control" id="{{ $session }}-annual" type="text" readonly value="{{ $source->tuitionForMonths($source->billable_months, $session) }} {{ $source->currency }}" aria-live="polite"><small class="form-text">Calculated automatically from the monthly and package prices.</small></section>@endforeach</div>
<p class="form-text mt-3">Example: 25 USD for 1 month and 120 USD for 6 months gives a 30 USD package saving. Each available session can have its own price. Prices apply to the student's selected session.</p></div></section>
<section class="panel mb-4"><div class="panel-header"><h2>Automatic tuition calculation</h2></div><div class="table-responsive"><table class="table"><thead><tr><th>Session</th><th>1 month</th><th>6 months</th><th>Package saving</th><th>{{ $source->billable_months }}-month tuition</th></tr></thead><tbody>@foreach($sessions as $session)<tr data-tuition-preview="{{ $session }}"><td>{{ $session }}</td><td data-monthly></td><td data-quarterly></td><td data-saving></td><td data-annual></td></tr>@endforeach</tbody></table></div><p class="form-text px-4">Annual and semester invoices use complete six-month packages plus the monthly price for remaining months. Monthly installment invoices use the monthly rate. Existing discounts and one-time charges remain separate.</p></section>
<div class="panel p-4">@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif<p class="form-text">Save &amp; activate creates a new approved fee version for each selected grade. Existing invoices and payment history keep their original amounts. No new student charges are created by saving prices.</p><div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Save &amp; activate prices</button><a class="btn btn-outline-secondary" href="{{ route('finance.index') }}">Cancel</a></div></div>
</form>
@endsection
@push('scripts')
<script>
(() => {
    const form = document.querySelector('[data-tuition-editor]');
    const scrollKey = 'tuition-editor-scroll:' + @json($program);
    form.addEventListener('submit', () => { try { sessionStorage.setItem(scrollKey, String(window.scrollY)); } catch {} });
    try {
        const previousScroll = sessionStorage.getItem(scrollKey);
        if (previousScroll !== null) {
            sessionStorage.removeItem(scrollKey);
            requestAnimationFrame(() => window.scrollTo({top: Number(previousScroll), behavior: 'instant'}));
        }
    } catch {}
    const same = form.querySelector('[data-same-day]');
    const sessions = @json($sessions);
    const months = {{ (int) $source->billable_months }};
    const currency = @json($source->currency);
    const cents = value => { const [whole, fraction = ''] = (value || '0').split('.'); return Number(whole) * 100 + Number(fraction.padEnd(2, '0').slice(0, 2)); };
    const money = value => `${Math.trunc(value / 100)}.${String(value % 100).padStart(2,'0')} ${currency}`;
    const automatic = new Map(sessions.map(session => {
        const stored = document.getElementById(`${session}-quarterly-value`).value;
        return [session, stored === '' || cents(stored) === cents(document.getElementById(`${session}-monthly`).value) * 6];
    }));
    const showSelectedGrades = () => {
        const selected = [...form.querySelectorAll('[name="grades[]"]:checked')].map(input => input.value);
        form.querySelector('[data-selected-tuition-grades]').textContent = selected.length ? 'Selected for this save: ' + selected.join(', ') : 'Select grades above to edit their prices.';
    };
    const update = () => {
        showSelectedGrades();
        ['monthly','quarterly'].forEach(kind => { const morning = document.getElementById(`Morning-${kind}`); const afternoon = document.getElementById(`Afternoon-${kind}`); if (!morning || !afternoon || !same) return; afternoon.readOnly = same.checked; if (same.checked) afternoon.value = morning.value; });
        sessions.forEach(session => {
            const monthly = cents(document.getElementById(`${session}-monthly`).value);
            const input = document.getElementById(`${session}-quarterly`);
            if (session === 'Afternoon' && same?.checked && automatic.has('Morning')) automatic.set(session, automatic.get('Morning'));
            if (automatic.get(session)) input.value = (monthly * 6 / 100).toFixed(2);
            document.getElementById(`${session}-quarterly-value`).value = automatic.get(session) ? '' : input.value;
            const quarterly = cents(input.value);
            const row = form.querySelector(`[data-tuition-preview="${session}"]`);
            row.querySelector('[data-monthly]').textContent = money(monthly);
            row.querySelector('[data-quarterly]').textContent = money(quarterly);
            row.querySelector('[data-saving]').textContent = money(Math.max(0, monthly * 6 - quarterly));
            const annual = money(Math.trunc(months / 6) * quarterly + months % 6 * monthly);
            row.querySelector('[data-annual]').textContent = annual;
            document.getElementById(`${session}-annual`).value = annual;
        });
    };
    form.addEventListener('input', event => {
        if (event.target.dataset.priceKind === 'quarterly') automatic.set(event.target.dataset.session, event.target.value === '');
        update();
    });
    form.querySelector('[data-select-tuition-grades]').addEventListener('click', () => { form.querySelectorAll('[name="grades[]"]').forEach(checkbox => checkbox.checked = true); showSelectedGrades(); });
    update();
})();
</script>
@endpush
