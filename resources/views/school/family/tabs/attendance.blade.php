<form class="family-filter" action="{{ $profile }}" method="get" data-family-navigation>
    <input type="hidden" name="tab" value="attendance">
    <div><label class="form-label" for="family-month">{{ __('Attendance month') }}</label><input class="form-control" id="family-month" type="month" name="month" value="{{ $month->format('Y-m') }}" required></div>
    <button class="btn family-button" type="submit">{{ __('Show data') }}</button>
</form>
<section class="mt-4">
    <h3 id="family-attendance-report">{{ __('Attendance report') }} &middot; {{ $month->translatedFormat('F Y') }}</h3>
    @include('school.family.tabs.attendance-report')
</section>
