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

<div class="room-grid-heading">
    <div>
        <div class="lesson-picker">
            <h2><i class="bi bi-buildings room-section-icon" aria-hidden="true"></i> <button type="button" class="lesson-picker-toggle" data-lesson-toggle aria-expanded="false" aria-controls="lesson-filter-options">Physical classrooms <i class="bi bi-chevron-down" aria-hidden="true"></i></button></h2>
            <div class="lesson-picker-menu" id="lesson-filter-options">
                <div class="lesson-filters" role="group" aria-label="Filter by current lesson">
                    <button type="button" data-lesson-filter="all" aria-pressed="true">All rooms <span data-lesson-count="all"></span></button>
                    <button type="button" data-lesson-filter="studying" aria-pressed="false"><i class="bi bi-book" aria-hidden="true"></i> Studying <span data-lesson-count="studying"></span></button>
                    <button type="button" data-lesson-filter="free" aria-pressed="false"><i class="bi bi-cup-hot" aria-hidden="true"></i> Free period <span data-lesson-count="free"></span></button>
                </div>
            </div>
        </div>
        <p>{{ $availability->count() }} {{ \Illuminate\Support\Str::plural('room', $availability->count()) }} match the selected date, time, and filters.<span data-lesson-selection></span></p>
    </div>
    <span class="status-legend"><span class="legend-available">{{ __('Available') }}</span><span class="legend-busy">Busy</span><span class="legend-upcoming">Upcoming</span><span class="legend-out">Out of service</span></span>
</div>

<section class="classroom-grid" aria-label="Physical classroom status">
    @forelse($availability as $entry)
        @php
            $room = data_get($entry, 'classroom');
            $status = data_get($entry, 'status', 'available');
            $current = data_get($entry, 'current');
            $next = data_get($entry, 'next');
            $displayBooking = $current ?: $next;
            $group = $displayBooking?->classGroup;
            $session = $displayBooking?->classSession;
            $lesson = data_get($entry, 'currentLesson');
            $lessonState = $status === 'out_of_service' ? 'unavailable' : ($lesson ? ($lesson->isRelaxation() ? 'relaxation' : 'studying') : ($current && data_get($entry, 'activity') === 'Free Period' ? 'free' : 'none'));
            $icons = ['available' => 'check-circle-fill', 'busy' => 'record-circle-fill', 'upcoming' => 'clock-fill', 'out_of_service' => 'tools'];
            $programDetails = collect(data_get($entry, 'programDetails', []));
            $selectedProgram = $programDetails->has(request('program')) ? request('program') : $programDetails->keys()->first();
        @endphp
        <article class="classroom-card room-status-{{ $status }}" data-room-card="{{ $room->id }}" data-lesson-state="{{ $lessonState }}">
            <div class="room-card-visual" aria-hidden="true"><img src="{{ asset('images/classroom-interior.svg') }}" alt="" loading="lazy" width="640" height="220"><span class="room-visual-label"><i class="bi bi-{{ $icons[$status] ?? 'circle-fill' }}"></i> {{ str_replace('_', ' ', ucfirst($status)) }}</span></div>
            <div class="room-card-head">
                <span class="room-icon"><i class="bi bi-door-open" aria-hidden="true"></i></span>
                <span class="room-heading"><small>{{ $room->buildingRecord ? $room->buildingRecord->name.' / ' : '' }}{{ __('Room') }}</small><strong>{{ $room->room_number }}</strong>@if($room->name && $room->name !== $room->room_number)<span>{{ $room->name }}</span>@endif</span>
                <span class="room-status"><i class="bi bi-{{ $icons[$status] ?? 'circle-fill' }}" aria-hidden="true"></i> {{ str_replace('_', ' ', ucfirst($status)) }}</span>
            </div>
            <div class="room-card-body">
                @if(in_array($lessonState, ['studying', 'free'], true))
                    <span class="lesson-state lesson-state-{{ $lessonState }}">{{ $lessonState === 'studying' ? 'Studying now' : 'Free period' }}</span>
                @endif
                @if($status === 'out_of_service')
                    <div class="room-empty-message"><strong>Temporarily unavailable</strong><span>{{ $room->notes ?: 'This room is marked out of service.' }}</span></div>
                @elseif($displayBooking)
                    <div class="booking-context"><small>{{ $current ? 'Current class' : 'Next scheduled class' }}</small><strong>{{ $group?->display_name ?? 'Scheduled class' }}</strong><span class="program-badge program-{{ strtolower($group?->program ?? '') }}">{{ $group?->program ?? 'Program not set' }}</span></div>
                    <dl class="room-facts">
                        <div><dt><i class="bi bi-clock" aria-hidden="true"></i> {{ __('Session') }}</dt><dd>{{ $session?->session ?? '—' }} · {{ substr((string) $displayBooking->starts_at, 0, 5) }}–{{ substr((string) $displayBooking->ends_at, 0, 5) }}</dd></div>
                    </dl>
                @elseif(data_get($entry, 'currentLesson'))
                    <div class="booking-context"><small>Lesson currently in progress</small><strong>{{ data_get($entry, 'currentLesson.schoolClass.label') }}</strong></div>
                @elseif(data_get($entry, 'occupancy') === 'Occupied')
                    <div class="room-empty-message"><strong>Room reserved</strong><span>{{ data_get($entry, 'activity') }}</span></div>
                @else
                    <div class="room-empty-message"><strong>Ready to use</strong><span>No class occupies this room at the selected time.</span></div>
                @endif
                @if($lessonState === 'studying' && $lesson?->teacher)
                    <div class="room-current-teacher" data-current-teacher>
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                        <span><small>{{ substr((string) $lesson->starts_at, 0, 5) }}&ndash;{{ substr((string) $lesson->ends_at, 0, 5) }} · {{ __('Teacher') }}</small><strong>{{ $lesson->teacher->name }}</strong></span>
                    </div>
                @endif
            </div>
            <div class="room-program-section">
                <div class="room-program-tabs" role="group" aria-label="Programs in room {{ $room->room_number }}">
                    @forelse($programDetails as $programName => $details)
                        <button type="button" data-room-program="{{ $programName }}" aria-controls="room-{{ $room->id }}-program-{{ $loop->index }}" aria-pressed="{{ $programName === $selectedProgram ? 'true' : 'false' }}">{{ $programName }}</button>
                    @empty No programs assigned yet @endforelse
                </div>
                @foreach($programDetails as $programName => $details)
                    <div id="room-{{ $room->id }}-program-{{ $loop->index }}" data-room-program-panel="{{ $programName }}" data-enrolled-count="{{ $details['students'] }}" @if($programName !== $selectedProgram) hidden @endif>
                        <div class="room-enrollment-summary">
                            <div><strong>{{ $details['students'] }}</strong><span>enrolled students</span></div>
                            <span class="room-seat-limit">{{ $room->capacity }} seats</span>
                        </div>
                        <div class="room-session-tags">@foreach($details['sessions'] as $sessionName)<span><i class="bi bi-clock" aria-hidden="true"></i> {{ $sessionName }}</span>@endforeach</div>
                    </div>
                @endforeach
            </div>
            <div class="room-card-footer"><span>Room details</span><a aria-label="View details for room {{ $room->room_number }}" href="{{ route('classrooms.show', ['classroom' => $room, 'date' => $moment->format('Y-m-d'), 'time' => $moment->format('H:i'), 'academic_year' => request('academic_year'), 'live' => request()->boolean('live') || (!request()->filled('date') && !request()->filled('time')) ? 1 : 0]) }}">View <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
        </article>
    @empty
        <div class="empty-state classroom-empty"><i class="bi bi-door-closed" aria-hidden="true"></i>No physical classrooms match these filters.</div>
    @endforelse
</section>
<p class="empty-state" data-lesson-empty hidden>No classrooms match this lesson filter at the selected time.</p>
