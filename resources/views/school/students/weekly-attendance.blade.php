<section class="attendance-overview student-week-panel" aria-labelledby="student-week-heading">
    <div class="attendance-overview-heading"><h2 id="student-week-heading"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> {{ __('Weekly Attendance Trend') }}</h2></div>
    <div class="student-week-chart" data-student-week data-selected-date="{{ $attendanceDate }}" data-empty-text="{{ __('No attendance recorded this week.') }}">
        <svg viewBox="0 0 420 150" role="img" aria-labelledby="student-week-heading" preserveAspectRatio="none"></svg>
        <div class="student-week-days">
            @foreach($attendanceWeek as $day)
                <span data-week-day="{{ $day['date']->toDateString() }}" data-rate="{{ $day['rate'] }}">{{ $day['date']->translatedFormat('D') }}</span>
            @endforeach
        </div>
    </div>
</section>
