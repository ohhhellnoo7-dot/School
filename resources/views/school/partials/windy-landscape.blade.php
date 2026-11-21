<div class="windy-landscape windy-landscape--{{ $placement }}" aria-hidden="true">
    @foreach(['distant', 'middle', 'near'] as $position)
        <span class="windy-turbine windy-turbine--{{ $position }}">
            <span class="windy-turbine-rotor"><i></i><i></i><i></i></span>
        </span>
    @endforeach
    <span class="windy-hero-wind-streak"></span><span class="windy-hero-wind-streak"></span><span class="windy-hero-wind-streak"></span>
</div>
