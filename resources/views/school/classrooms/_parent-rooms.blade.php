@forelse($cards as $card)
    @php
        ['child' => $child, 'class' => $class, 'room' => $entry] = $card;
        $room = $entry['classroom'];
        [$label, $icon] = match ($entry['status']) {
            'busy' => ['In use', 'people-fill'],
            'upcoming' => ['Upcoming class', 'clock'],
            'out_of_service' => ['Out of service', 'tools'],
            default => ['Available', 'check-circle-fill'],
        };
    @endphp
    <article class="parent-classroom-card" data-room-status="{{ $entry['status'] }}">
        <div class="parent-classroom-photo">
            <img src="{{ asset('images/classroom-card.png') }}" alt="" width="1672" height="941">
            <span class="parent-classroom-status"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i> {{ __($label) }}</span>
        </div>
        <div class="parent-classroom-copy">
            <div class="parent-classroom-heading">
                <div class="parent-classroom-room">
                    <span class="parent-classroom-symbol"><i class="bi bi-door-open" aria-hidden="true"></i></span>
                    <div><h2>{{ $room->room_number }}</h2><p>{{ $room->name }}</p><small>{{ $child->name }} · {{ $class->name }} {{ $class->section }}</small></div>
                </div>
                <div class="parent-classroom-program"><span class="parent-classroom-symbol"><i class="bi bi-mortarboard" aria-hidden="true"></i></span><div>{{ __('Program') }}<strong>{{ $class->program === 'Japan' ? __('Japanese') : $class->program }}</strong></div></div>
            </div>
            <dl class="parent-classroom-facts">
                <div><span class="parent-classroom-symbol"><i class="bi bi-door-open" aria-hidden="true"></i></span><dt>{{ __('Room status') }}</dt><dd><span class="parent-classroom-status">{{ __($label) }}</span></dd></div>
                <div><span class="parent-classroom-symbol"><i class="bi bi-people" aria-hidden="true"></i></span><dt>{{ __('Total students') }}</dt><dd>{{ $class->enrolled_count }}</dd></div>
                <div><span class="parent-classroom-symbol"><i class="bi bi-calendar3" aria-hidden="true"></i></span><dt>{{ __('Class session') }}</dt><dd>{{ $class->session ? __($class->session) : '—' }}</dd></div>
            </dl>
        </div>
    </article>
@empty
    <p class="panel p-4 mb-0">{{ __('No classroom has been assigned to your linked children yet.') }}</p>
@endforelse
