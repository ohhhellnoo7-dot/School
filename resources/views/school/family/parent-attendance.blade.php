@extends('school.layout')
@section('title', __('Attendance'))

@push('styles')
    <link href="{{ asset('css/parent-attendance.css') }}?v={{ filemtime(public_path('css/parent-attendance.css')) }}" rel="stylesheet">
@endpush

@section('content')
<div class="parent-attendance">
    <header class="pa-hero">
        <nav aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ $student ? route('students.family.show', $student) : route('students.index') }}">{{ __('My Child') }}</a>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
            <span aria-current="page">{{ __('Attendance') }}</span>
        </nav>
        <div class="pa-hero-copy">
            <span class="pa-hero-icon"><i class="bi bi-calendar-check-fill" aria-hidden="true"></i></span>
            <div><h1>{{ __('Attendance') }}</h1><p>{{ __('Track your child’s attendance and daily records.') }}</p></div>
        </div>
    </header>

    @if($student)
        @php
            $statuses = [
                'present' => ['Present', 'check-lg'],
                'absent' => ['Absent', 'x-lg'],
                'late' => ['Late', 'clock'],
                'excused' => ['Excused', 'shield-check'],
            ];
            $total = $counts->only(array_keys($statuses))->sum();
            $percent = fn ($status) => $total ? round(100 * $counts->get($status, 0) / $total, 1) : 0;
            $stopPresent = $percent('present');
            $stopAbsent = min(100, $stopPresent + $percent('absent'));
            $stopLate = min(100, $stopAbsent + $percent('late'));
            $class = $student->schoolClass ?? $classes->first();
        @endphp

        <div class="pa-overview">
            <section class="pa-child-card" aria-label="{{ __('Selected child') }}">
                @include('school.partials.avatar', ['person' => $student, 'size' => 76])
                <div>
                    <h2>{{ $student->name }}</h2>
                    <span class="pa-student-number">{{ $student->student_number }}</span>
                </div>
                <div class="pa-child-class">
                    @if($class)
                        <span><i class="bi bi-mortarboard" aria-hidden="true"></i> {{ collect([$class->name, $class->section])->filter()->join(' – ') }}</span>
                        @if($class->session)<span><i class="bi bi-calendar3" aria-hidden="true"></i> {{ __($class->session) }}</span>@endif
                    @else
                        <span>{{ __('No class recorded') }}</span>
                    @endif
                    @if($siblings->count() > 1)
                        <form action="{{ route('modules.index', 'attendance') }}" method="get" data-parent-attendance-filter>
                            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                            <select class="form-select form-select-sm" name="child" aria-label="{{ __('Switch child') }}">
                                @foreach($siblings as $sibling)
                                    <option value="{{ $sibling->id }}" @selected($sibling->is($student))>{{ $sibling->name }}</option>
                                @endforeach
                            </select>
                            <noscript><button class="btn btn-outline-secondary" type="submit">{{ __('Switch child') }}</button></noscript>
                        </form>
                    @endif
                </div>
            </section>
            @foreach($statuses as $status => [$label, $icon])
                <section class="pa-status-card" data-attendance-status="{{ $status }}">
                    <span class="pa-status-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span>
                    <div>
                        <h3>{{ __($label) }}</h3>
                        <strong>{{ trans_choice(':count Day|:count Days', $counts->get($status, 0)) }}</strong>
                        <small>{{ $percent($status) }}%</small>
                    </div>
                </section>
            @endforeach
        </div>

        <div class="pa-charts">
            <section class="pa-panel">
                <h2><i class="bi bi-bar-chart-line-fill" aria-hidden="true"></i> {{ __('Attendance Rate') }}</h2>
                <div class="pa-rate-layout">
                    <div class="pa-rate-ring" data-recorded="{{ $total > 0 ? 'yes' : 'no' }}"
                        style="--pa-present-stop: {{ $stopPresent }}%; --pa-absent-stop: {{ $stopAbsent }}%; --pa-late-stop: {{ $stopLate }}%">
                        <div>
                            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                            <strong>{{ $rate === null ? '—' : $rate.'%' }}</strong>
                            <span>{{ __('Attended') }}</span>
                        </div>
                    </div>
                    <dl class="pa-rate-legend">
                        @foreach($statuses as $status => [$label, $icon])
                            <div data-attendance-status="{{ $status }}">
                                <dt>{{ __($label) }}</dt>
                                <dd><strong>{{ $counts->get($status, 0) }}</strong> ({{ $percent($status) }}%)</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
                <p class="pa-rate-note">{{ __('The attendance rate includes present and late days.') }}</p>
            </section>
            <section class="pa-panel">
                <h2><i class="bi bi-calendar2-check" aria-hidden="true"></i> {{ __('Monthly Attendance') }}</h2>
                @if(collect($trend)->contains(fn ($point) => $point['rate'] !== null))
                    <div class="pa-chart">
                        <canvas data-parent-attendance-chart="{{ json_encode($trend) }}" aria-hidden="true"></canvas>
                    </div>
                    <dl class="visually-hidden" aria-label="{{ __('Monthly attendance rates') }}">
                        @foreach($trend as $point)
                            <dt>{{ $point['label'] }}</dt>
                            <dd>{{ $point['rate'] === null ? __('No records') : $point['rate'].'%' }}</dd>
                        @endforeach
                    </dl>
                @else
                    <p class="pa-chart-empty">{{ __('No attendance recorded in these months.') }}</p>
                @endif
            </section>
        </div>

        <section class="pa-panel pa-records" id="parent-attendance-records">
            <header>
                <h2><i class="bi bi-clipboard-data-fill" aria-hidden="true"></i> {{ __('Attendance Records') }}</h2>
                <form action="{{ route('students.family.show', $student) }}#parent-attendance-records" method="get" data-parent-attendance-filter>
                    <input type="hidden" name="tab" value="attendance">
                    <input class="form-control" type="month" name="month" value="{{ $month->format('Y-m') }}" aria-label="{{ __('Attendance month') }}" required>
                    <noscript><button type="submit" class="btn btn-outline-secondary">{{ __('Show month') }}</button></noscript>
                </form>
            </header>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Remark') }}</th></tr></thead>
                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td><i class="bi bi-calendar3" aria-hidden="true"></i> <time datetime="{{ $record->date->toDateString() }}">{{ $record->date->translatedFormat('M j, Y') }}</time></td>
                                <td><span class="pa-record-status" data-attendance-status="{{ $record->status }}"><i class="bi bi-{{ $statuses[$record->status][1] ?? 'info-circle' }}" aria-hidden="true"></i> {{ __(ucfirst($record->status)) }}</span></td>
                                <td>{{ $record->notes ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">{{ __('No attendance recorded this month.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <section class="pa-panel"><h2>{{ __('No children linked yet') }}</h2><p class="mb-0">{{ __('Contact the school office to link a student to your account.') }}</p></section>
    @endif
</div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/chart/chart.umd.min.js') }}" defer></script>
    <script src="{{ asset('js/parent-attendance.js') }}?v={{ filemtime(public_path('js/parent-attendance.js')) }}" defer></script>
@endpush
