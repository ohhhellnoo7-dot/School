<section id="dashboard-khmer-calendar" class="khmer-calendar {{ $calendarClass ?? 'border-top p-3' }}" aria-labelledby="khmer-calendar-title" lang="{{ app()->getLocale() }}" data-holiday-labels="{{ json_encode(__('khmer-calendar.holidays')) }}">
    <div class="khmer-calendar-header">
        <div class="khmer-calendar-heading">
            <span class="khmer-calendar-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span>
            <div>
                <h3 id="khmer-calendar-title">{{ __('khmer-calendar.title') }}</h3>
                <p>{{ __('khmer-calendar.subtitle') }}</p>
            </div>
        </div>
        <div class="khmer-calendar-controls" data-calendar-controls hidden>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-calendar-action="previous" aria-label="{{ __('khmer-calendar.previous') }}"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-calendar-action="today"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ __('khmer-calendar.today') }}</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-calendar-action="next" aria-label="{{ __('khmer-calendar.next') }}"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="khmer-calendar-layout" data-calendar-layout hidden>
        <div class="khmer-calendar-month">
            <table class="khmer-calendar-grid">
                <caption data-calendar-month></caption>
                <thead><tr>
                    @foreach(__('khmer-calendar.weekdays') as $weekday)
                        <th scope="col">{{ $weekday }}</th>
                    @endforeach
                </tr></thead>
                <tbody></tbody>
            </table>
            <div class="khmer-calendar-legend">
                    <span><i class="bi bi-calendar-event" aria-hidden="true"></i> {{ __('khmer-calendar.today') }}</span>
                    <span class="khmer-calendar-holiday-key"><i class="bi bi-flag-fill" aria-hidden="true"></i> {{ __('khmer-calendar.holiday') }}</span>
            </div>
        </div>
        <div class="khmer-calendar-sidebar">
            <div class="khmer-calendar-selection" aria-live="polite" aria-atomic="true">
                    <h4><i class="bi bi-calendar-check" aria-hidden="true"></i> {{ __('khmer-calendar.selected') }}</h4>
                    <p class="khmer-calendar-solar" data-calendar-solar></p>
                    <div class="khmer-calendar-detail">
                        <i class="bi bi-moon-stars" aria-hidden="true"></i>
                        <p><span>{{ __('khmer-calendar.lunar') }}</span> <span data-calendar-date lang="km"></span></p>
                    </div>
                    <div class="khmer-calendar-detail khmer-calendar-holiday-key" data-selected-holiday hidden>
                        <i class="bi bi-flag-fill" aria-hidden="true"></i>
                        <p></p>
                    </div>
            </div>
            <div class="khmer-calendar-holidays" data-calendar-holidays hidden>
                    <h4><i class="bi bi-flag" aria-hidden="true"></i> {{ __('khmer-calendar.public_holidays') }}</h4>
                    <ul></ul>
                    <p class="mb-0" data-holiday-coverage hidden>{{ __('khmer-calendar.coverage') }}</p>
            </div>
        </div>
    </div>
    <p class="small mb-0" data-calendar-fallback>{{ __('khmer-calendar.fallback') }}</p>
</section>
