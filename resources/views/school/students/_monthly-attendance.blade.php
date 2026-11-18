<section class="panel mb-4" aria-labelledby="attendance-heading">
    <div class="panel-header d-flex flex-wrap gap-2">
        <h2 id="attendance-heading" tabindex="-1">{{ __('Attendance history') }}</h2>
        <span class="small text-secondary">
            @if($attendanceRate !== null)
                {{ __('Attendance') }} {{ $attendanceRate }}% <span>({{ __('present + late / all records') }})</span>
            @else
                {{ __('No attendance recorded this month.') }}
            @endif
        </span>
    </div>
    <nav class="px-3 py-2 d-flex align-items-center gap-2" aria-label="{{ __('Attendance month') }}">
        @if($month->year > 0 || $month->month > 1)
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('students.show', ['student' => $student, 'month' => $month->subMonth()->format('Y-m')]) }}" data-attendance-month="previous" aria-label="{{ __('Previous month') }}"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
        @endif
        <strong>{{ $month->translatedFormat('F Y') }}</strong>
        @if($month->lessThan($currentMonth))
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('students.show', ['student' => $student, 'month' => $month->addMonth()->format('Y-m')]) }}" data-attendance-month="next" aria-label="{{ __('Next month') }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        @else
            <button type="button" class="btn btn-sm btn-outline-secondary" disabled aria-label="{{ __('Next month') }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        @endif
    </nav>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Status') }}</th><th>{{ __('Notes') }}</th></tr></thead>
            <tbody>@include('school.students._attendance-history-rows')</tbody>
        </table>
    </div>
</section>
