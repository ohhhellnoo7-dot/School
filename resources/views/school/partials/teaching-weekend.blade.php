<div class="teacher-weekend">
    <img @unless($preview ?? false) src="{{ asset(($sunday ?? false) ? 'images/teacher-weekend-sunday.png' : 'images/teacher-weekend.png') }}" @endunless alt="" decoding="async">
    <h3>{{ __("It's the Weekend!") }}</h3>
    @unless($preview ?? false)
        <p>{{ __('You have no teaching periods scheduled today.') }}</p>
    @endunless
    <span class="teacher-weekend-label"><i class="bi bi-calendar2-check" aria-hidden="true"></i> {{ __('Weekend Day') }}</span>
</div>
