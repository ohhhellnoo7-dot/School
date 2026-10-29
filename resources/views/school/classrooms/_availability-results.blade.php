<section class="availability-stats" aria-label="Classroom availability summary">
    @foreach([
        ['total', 'Total classrooms', 'building', 'stat-total'],
        ['busy', 'Currently busy', 'person-video3', 'stat-busy'],
        ['available', 'Currently available', 'check-circle', 'stat-available'],
        ['upcoming', 'Upcoming classes', 'clock-history', 'stat-upcoming'],
    ] as [$key, $label, $icon, $tone])
        <article class="availability-stat {{ $tone }}"><span class="availability-stat-icon"><i class="bi bi-{{ $icon }}" aria-hidden="true"></i></span><span><small>{{ $label }}</small><strong>{{ $stats[$key] ?? 0 }}</strong></span></article>
    @endforeach
</section>

<div class="room-grid-heading"><div><h2>Physical classrooms</h2><p>{{ $availability->count() }} {{ \Illuminate\Support\Str::plural('room', $availability->count()) }} match the selected date, time, and filters.</p></div><span class="status-legend"><span class="legend-available">Available</span><span class="legend-busy">Busy</span><span class="legend-upcoming">Upcoming</span><span class="legend-out">Out of service</span></span></div>

<section class="classroom-grid" aria-label="Physical classroom status">
    @forelse($availability as $entry)
        @php
            $room = data_get($entry, 'classroom');
            $status = data_get($entry, 'status', 'available');
            $current = data_get($entry, 'current');
            $next = data_get($entry, 'next');
            $displayBooking = $current ?: $next;
            $group = $displayBooking?->classGroup;
            $teacher = $displayBooking?->teacher ?: $group?->teacher;
            $session = $displayBooking?->classSession;
            $icons = ['available' => 'check-circle-fill', 'busy' => 'record-circle-fill', 'upcoming' => 'clock-fill', 'out_of_service' => 'tools'];
        @endphp
        <article class="classroom-card room-status-{{ $status }}">
            <div class="room-card-head">
                <span class="room-icon"><i class="bi bi-door-open" aria-hidden="true"></i></span>
                <span class="room-heading"><small>Room</small><strong>{{ $room->room_number }}</strong>@if($room->name && $room->name !== $room->room_number)<span>{{ $room->name }}</span>@endif</span>
                <span class="room-status"><i class="bi bi-{{ $icons[$status] ?? 'circle-fill' }}" aria-hidden="true"></i> {{ str_replace('_', ' ', ucfirst($status)) }}</span>
            </div>
            <div class="room-card-body">
                @if($status === 'out_of_service')
                    <div class="room-empty-message"><strong>Temporarily unavailable</strong><span>{{ $room->notes ?: 'This room is marked out of service.' }}</span></div>
                @elseif($displayBooking)
                    <div class="booking-context"><small>{{ $current ? 'Current class' : 'Next scheduled class' }}</small><strong>{{ $group?->display_name ?? 'Scheduled class' }}</strong><span class="program-badge program-{{ strtolower($group?->program ?? '') }}">{{ $group?->program ?? 'Program not set' }}</span></div>
                    <dl class="room-facts">
                        <div><dt><i class="bi bi-clock" aria-hidden="true"></i> Session</dt><dd>{{ $session?->session ?? '—' }} · {{ substr((string) $displayBooking->starts_at, 0, 5) }}–{{ substr((string) $displayBooking->ends_at, 0, 5) }}</dd></div>
                        <div><dt><i class="bi bi-person" aria-hidden="true"></i> Teacher</dt><dd>{{ $teacher?->name ?? 'Unassigned' }}</dd></div>
                    </dl>
                @else
                    <div class="room-empty-message"><strong>Ready to use</strong><span>No class occupies this room at the selected time.</span></div>
                @endif
            </div>
            <div class="room-card-footer"><span><i class="bi bi-people" aria-hidden="true"></i> Capacity {{ $room->capacity }}</span><a href="{{ route('classrooms.show', ['classroom' => $room, 'date' => $moment->format('Y-m-d'), 'time' => $moment->format('H:i')]) }}">View details <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
        </article>
    @empty
        <div class="empty-state classroom-empty"><i class="bi bi-door-closed" aria-hidden="true"></i>No physical classrooms match these filters.</div>
    @endforelse
</section>
