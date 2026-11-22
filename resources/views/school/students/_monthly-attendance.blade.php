<section class="student-profile-card" aria-labelledby="attendance-heading">
    <div class="student-profile-card-title">
        <h2 id="attendance-heading" tabindex="-1"><i class="bi bi-calendar2-check" aria-hidden="true"></i> {{ __('Attendance history') }}</h2>
        <span class="student-profile-chip">
            @if($attendanceRate !== null)
                {{ __('Attendance') }} {{ $attendanceRate }}% <span>({{ __('present + late / all records') }})</span>
            @else
                {{ __('No attendance recorded this month.') }}
            @endif
        </span>
    </div>
    <nav class="student-attendance-month" aria-label="{{ __('Attendance month') }}">
        @if($month->year > 0 || $month->month > 1)
            <a class="student-profile-control" href="{{ route('students.show', ['student' => $student, 'month' => $month->subMonth()->format('Y-m')]) }}" data-attendance-month="previous" aria-label="{{ __('Previous month') }}"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
        @endif
        <strong class="student-profile-chip"><i class="bi bi-calendar3" aria-hidden="true"></i> {{ $month->translatedFormat('F Y') }}</strong>
        @if($month->lessThan($currentMonth))
            <a class="student-profile-control" href="{{ route('students.show', ['student' => $student, 'month' => $month->addMonth()->format('Y-m')]) }}" data-attendance-month="next" aria-label="{{ __('Next month') }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
        @else
            <button type="button" class="student-profile-control" disabled aria-label="{{ __('Next month') }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        @endif
    </nav>
    @if($student->attendances->isEmpty())
        <div class="student-profile-empty"><i class="bi bi-calendar-x" aria-hidden="true"></i> {{ __('No attendance recorded yet.') }}</div>
    @else
        <div class="student-profile-table-wrap">
            <table class="student-profile-table" aria-labelledby="attendance-heading">
                <thead><tr><th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col">{{ __('Notes') }}</th></tr></thead>
                <tbody>@include('school.students._attendance-history-rows')</tbody>
            </table>
        </div>
    @endif
</section>
