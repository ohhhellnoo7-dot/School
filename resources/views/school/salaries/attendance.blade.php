@extends('school.layout')
@section('title', 'Teacher attendance')
@section('content')
<div class="page-heading"><div><div class="eyebrow">EMPLOYEE SALARY</div><h1>Teacher attendance</h1><p>Record a whole day or one session. Attendance updates the teacher’s salary deductions.</p></div><a class="btn btn-outline-primary" href="{{ route('employee-salaries.index', ['month' => substr($date, 0, 7)]) }}">Back to payroll</a></div>
<section class="panel">
    <form class="salary-filters" method="get" data-salary-search><div><label class="form-label" for="salary-date">Attendance date</label><input id="salary-date" class="form-control" type="date" name="date" value="{{ $date }}" max="{{ today()->toDateString() }}" required></div><div><label class="form-label" for="salary-search">Search teacher by name</label><input id="salary-search" class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Teacher name or employee ID" autocomplete="off"></div></form>
    @php($rates = app(\App\Services\EmployeeSalaryRates::class)->amounts())
    <div class="px-4 pb-3 small text-secondary">
        For teachers working both daytime sessions, one absence costs ${{ \App\Support\Money::decimal($rates['absence_one']) }} and two cost ${{ \App\Support\Money::decimal($rates['absence_two']) }}.
        Missing the only assigned session costs ${{ \App\Support\Money::decimal($rates['absence_single_session']) }}.
        Late costs ${{ \App\Support\Money::decimal($rates['late']) }} per day. Excused costs ${{ \App\Support\Money::decimal($rates['excused']) }} per day after the first free excuse each month.
    </div>
    <div id="salary-search-results" aria-live="polite"><div class="table-responsive"><table class="table salary-table"><thead><tr><th>Teacher</th><th>Morning</th><th>Afternoon</th><th>Evening</th><th>Record attendance</th></tr></thead><tbody>
    @forelse($teachers as $teacher)
        @php($marks = $overrides->get($teacher->id, collect())->keyBy('session'))
        @php($scanMarks = $scans->get($teacher->id, collect())->keyBy('session'))
        <tr><td><div class="payroll-person">
            @include('school.partials.avatar', ['person' => $teacher, 'avatarClass' => 'payroll-avatar', 'teacherIcon' => true])
            @php($dailyMark = $daily->get($teacher->id))
            <div><strong>{{ $teacher->name }}</strong><small>{{ $teacher->profile_number }}</small><a class="small" href="{{ route('employee-salaries.show', ['teacher' => $teacher, 'month' => substr($date, 0, 7)]) }}#salary-attendance">Monthly history</a>
                @if($dailyMark?->status === 'excused' && $dailyMark->notes)<small class="d-block text-break">{{ $dailyMark->notes }}</small>@endif
            </div>
        </div></td>
        @foreach(['Morning', 'Afternoon', 'Evening'] as $session)
            @php($status = $marks->get($session)?->status ?? $marks->get('All')?->status ?? $daily->get($teacher->id)?->status ?? $scanMarks->get($session)?->status)
            <td>@if($status)<span class="status-badge {{ $status }}">{{ ucfirst($status) }}</span>@else<span class="text-secondary">Not recorded</span>@endif</td>
        @endforeach
        <td><form method="post" action="{{ route('employee-salaries.attendance.store', $teacher) }}" class="salary-attendance-row">@csrf<input type="hidden" name="date" value="{{ $date }}"><select name="session" class="form-select form-select-sm" aria-label="Session for {{ $teacher->name }}">@foreach(['All' => 'All sessions', 'Morning' => 'Morning', 'Afternoon' => 'Afternoon', 'Evening' => 'Evening'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><select name="status" class="form-select form-select-sm" aria-label="Status for {{ $teacher->name }}" required><option value="">Choose status</option>@foreach(['present', 'absent', 'late', 'excused'] as $status)<option value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select><button class="btn btn-sm btn-primary">Save</button></form></td></tr>
    @empty<tr><td colspan="5" class="p-4 text-center">No teachers found.</td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $teachers->links() }}</div></div>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/employee-salaries.js') }}?v={{ filemtime(public_path('js/employee-salaries.js')) }}" defer></script>@endpush
