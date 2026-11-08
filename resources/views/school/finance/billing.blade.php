@extends('school.layout')
@section('title', 'Issue invoices')
@section('finance-title', 'Bill a student enrollment')
@section('content')
@include('school.finance._nav')
<section class="panel p-4 mb-4"><h2 class="h5">Find student</h2><form method="get" class="d-flex gap-2"><label class="visually-hidden" for="student-search">Student name or number</label><input id="student-search" class="form-control" name="search" value="{{ request('search') }}" placeholder="Student name or number"><button class="btn btn-primary">Search</button></form><p class="form-text">Showing up to 50 matches. Choose a student to view their enrolled programs.</p><div class="finance-student-results">@foreach($students as $item)<a class="btn btn-sm {{ $student?->id === $item->id ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('finance.billing', ['student' => $item->id, 'search' => request('search')]) }}">{{ $item->name }} &middot; {{ $item->student_number }}</a>@endforeach</div></section>
@if($student)<section class="panel p-4"><h2 class="h5">{{ $student->name }} &middot; {{ $student->student_number }}</h2>
@php($unreviewed = $student->fees()->whereNull('invoice_number')->where('legacy_reviewed', false)->get())
@if($unreviewed->isNotEmpty())<div class="alert alert-warning">Review historical fees before issuing new tuition. This prevents charging previously billed months again.<ul>@foreach($unreviewed as $previous)<li><a href="{{ route('finance.invoice', $previous) }}">{{ $previous->display_number }}: {{ $previous->description }} ({{ $previous->amount }} {{ $previous->currency }})</a></li>@endforeach</ul></div>@endif<form method="post" action="{{ route('finance.issue') }}">@csrf<input type="hidden" name="student_id" value="{{ $student->id }}"><div class="form-section-grid">
<div><label class="form-label" for="enrollment">Program enrollment</label><select class="form-select" id="enrollment" name="school_class_id" required><option value="">Choose an enrolled class</option>@foreach($student->studyClasses as $class)<option value="{{ $class->id }}" data-session="{{ $class->session }}">{{ $class->program === 'Japan' ? 'Japanese' : $class->program }} &middot; {{ $class->classGroup?->display_name ?? $class->name }} {{ $class->section }} &middot; {{ $class->session }}</option>@endforeach</select></div>
<div><label class="form-label" for="structure">Approved fee configuration</label><select class="form-select" id="structure" name="fee_structure_id" required><option value="">Choose the matching program, grade and year</option>@foreach($structures as $structure)<option value="{{ $structure->id }}" data-monthly="{{ $structure->monthly_tuition }}" data-package-months="{{ $structure->packageMonths() }}" data-quarterly="{{ $structure->quarterly_tuition }}" data-prices='@json($structure->session_prices)' data-months="{{ $structure->billable_months }}" data-semester="{{ $structure->semester_months }}" data-discount="{{ $structure->discount }}" data-currency="{{ $structure->currency }}">#{{ $structure->id }} {{ $structure->program }} Grade {{ $structure->grade }} &middot; {{ $structure->academic_year }} &middot; {{ $structure->session_prices ? 'Session-based prices' : $structure->monthly_tuition.' '.$structure->currency.'/month' }} ({{ $structure->billable_months }} months)</option>@endforeach</select></div>
<div><label class="form-label" for="plan">Billing plan</label><select class="form-select" id="plan" name="plan"><option value="monthly">Monthly</option><option value="package">6-month package</option><option value="semester">Semester</option><option value="annual">Annual (remaining months)</option><option value="installments">Installments (separate monthly invoices)</option></select></div>
<div><label class="form-label" for="start-month">Start at billable month number</label><input class="form-control" id="start-month" name="start_month" type="number" min="1" max="12" value="1" required></div>
<div><label class="form-label" for="count">Number of monthly installments</label><input class="form-control" id="count" name="count" type="number" min="1" max="12" value="12" required></div>
</div><div id="billing-tuition-quote" class="alert alert-info mt-3" aria-live="polite" hidden></div><p class="form-text">Month 1 is the fee configuration's first billable month. Annual billing includes only remaining unbilled months from the selected start. Previously issued monthly or semester invoices are retained; already billed months are never charged again. Morning and afternoon in the same class group share one program enrollment. Session prices follow the selected class. Six-month packages apply to complete consecutive unbilled blocks; monthly installments use the monthly rate. Each invoice can receive partial payments.</p><button class="btn btn-primary">Issue invoices</button></form></section>@endif
@endsection

@push('scripts')
<script>
(() => {
    const enrollment = document.getElementById('enrollment');
    if (!enrollment) return;
    const structure = document.getElementById('structure');
    const plan = document.getElementById('plan');
    const start = document.getElementById('start-month');
    const count = document.getElementById('count');
    const quote = document.getElementById('billing-tuition-quote');
    const cents = value => { const [whole, fraction = ''] = String(value || '0').split('.'); return Number(whole) * 100 + Number(fraction.padEnd(2, '0').slice(0, 2)); };
    const money = value => `${Math.trunc(value / 100)}.${String(value % 100).padStart(2, '0')}`;
    const update = () => {
        count.closest('div').hidden = plan.value !== 'installments';
        quote.hidden = !enrollment.value || !structure.value;
        if (quote.hidden) return;
        const option = structure.selectedOptions[0];
        const session = enrollment.selectedOptions[0].dataset.session;
        const prices = JSON.parse(option.dataset.prices || 'null') || {};
        const row = prices[session];
        const monthly = cents(row?.monthly ?? option.dataset.monthly);
        const packageValue = row && Object.hasOwn(row, 'quarterly') ? row.quarterly : option.dataset.quarterly;
        const duration = Number(option.dataset.packageMonths || 3);
        const quarterly = packageValue == null || packageValue === '' ? monthly * duration : cents(packageValue);
        const units = plan.value === 'annual' ? Number(option.dataset.months) - Number(start.value) + 1 : plan.value === 'package' ? 6 : plan.value === 'semester' ? Number(option.dataset.semester) : plan.value === 'monthly' ? 1 : Number(count.value);
        start.max = option.dataset.months;
        if (units <= 0 || Number(start.value) < 1 || Number(start.value) + units - 1 > Number(option.dataset.months)) { quote.textContent = 'Choose billing months within this fee calendar.'; return; }
        const tuition = (['monthly','installments'].includes(plan.value) ? monthly * units : Math.trunc(units / duration) * quarterly + units % duration * monthly) - cents(option.dataset.discount) * units;
        quote.textContent = `${session || 'Selected session'}: ${money(monthly)} ${option.dataset.currency}/month; ${money(Math.trunc(6 / duration) * quarterly + 6 % duration * monthly)} ${option.dataset.currency} for 6 months. Tuition estimate: ${money(tuition)} ${option.dataset.currency}. Extra charges and previously billed months are checked when issuing the invoice.`;
    };
    [enrollment, structure, plan, start, count].forEach(input => input.addEventListener('input', update));
    update();
})();
</script>
@endpush
