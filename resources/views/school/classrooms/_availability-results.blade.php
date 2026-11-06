@php
    $subjectLabel = static fn ($name) => preg_replace('/^(?:Japanese|Japan|English|Chinese|Khmer)\s+(?=\S)/iu', '', (string) $name);
@endphp
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
                    <button type="button" data-lesson-filter="incomplete" aria-pressed="false"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Incomplete timetable <span data-lesson-count="incomplete"></span></button>
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
            if ($status !== 'out_of_service' && data_get($entry, 'activity') === 'Incomplete timetable') $lessonState = 'incomplete';
            $icons = ['available' => 'check-circle-fill', 'busy' => 'record-circle-fill', 'upcoming' => 'clock-fill', 'out_of_service' => 'tools'];
            $programDetails = collect(data_get($entry, 'cardProgramDetails', data_get($entry, 'programDetails', [])));
            $activeProgram = $lesson?->schoolClass?->program ?? $current?->classGroup?->program;
            $activeSession = $lesson?->schoolClass?->session ?? $current?->classSession?->session;
            $selectedProgram = $programDetails->has($activeProgram) ? $activeProgram : ($programDetails->has(request('program')) ? request('program') : ($programDetails->has($group?->program) ? $group->program : $programDetails->keys()->first()));
            $selectedSession = collect(data_get($programDetails->get($selectedProgram), 'sessions', []))->first();
            $activeOverviewProgram = collect(data_get($programDetails->get($activeProgram), 'sessions', []))->contains($activeSession) ? $activeProgram : '';
        @endphp
        <article class="classroom-card room-status-{{ $status }}" data-room-card="{{ $room->id }}" data-active-program="{{ $activeProgram }}" data-room-context="{{ $activeProgram }}|{{ $activeSession }}" data-lesson-state="{{ $lessonState }}">
            <div class="room-card-visual" aria-hidden="true"><img src="{{ asset('images/classroom-card.png') }}" alt="" loading="lazy" width="1672" height="941"><span class="room-visual-label"><i class="bi bi-{{ $icons[$status] ?? 'circle-fill' }}"></i> {{ str_replace('_', ' ', ucfirst($status)) }}</span></div>
            <div class="room-card-head">
                <span class="room-icon"><i class="bi bi-door-open" aria-hidden="true"></i></span>
                <span class="room-heading"><small>{{ $room->buildingRecord ? $room->buildingRecord->name.' / ' : '' }}{{ __('Room') }}</small><strong>{{ $room->room_number }}</strong>@if($room->name && $room->name !== $room->room_number)<span>{{ $room->name }}</span>@endif</span>
                <span class="room-status"><i class="bi bi-{{ $icons[$status] ?? 'circle-fill' }}" aria-hidden="true"></i> {{ str_replace('_', ' ', ucfirst($status)) }}</span>
            </div>
            <div class="room-card-body">
                @foreach($programDetails as $sessionProgram => $sessionDetails)
                    <div data-room-session-panel="{{ $sessionProgram }}" @if($sessionProgram !== $selectedProgram) hidden @endif>
                        <span class="small"><i class="bi bi-clock" aria-hidden="true"></i> {{ __('Session') }}</span>
                        <div class="room-program-tabs room-session-buttons" role="group" aria-label="{{ $sessionProgram }} sessions in room {{ $room->room_number }}">
                            @foreach(collect(['Morning', 'Afternoon', 'Evening'])->intersect(data_get($entry, 'programDetails.'.$sessionProgram.'.sessions', [])) as $sessionOption)
                                <button type="button" data-room-session="{{ $sessionOption }}" data-session-program="{{ $sessionProgram }}" aria-pressed="{{ $sessionDetails['sessions']->contains($sessionOption) ? 'true' : 'false' }}">{{ $sessionOption }}</button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                <div data-room-program-overview="{{ $activeOverviewProgram }}" @if($selectedProgram !== $activeOverviewProgram) hidden @endif>
                @if(in_array($lessonState, ['studying', 'free', 'incomplete'], true))
                    <span class="lesson-state lesson-state-{{ $lessonState }}">{{ match ($lessonState) { 'studying' => 'Studying now', 'incomplete' => 'Incomplete timetable', default => 'Free period' } }}</span>
                    @if($lessonState === 'incomplete')<p class="text-warning-emphasis small">An academic lesson is missing. This is not a planned student free period.</p>@endif
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
                @if($lesson?->subject)<p class="small mb-0"><strong>{{ __('Subject') }}:</strong> {{ $subjectLabel($lesson->subject->name) }}</p>@endif
                </div>
                @foreach($programDetails as $previewProgram => $previewDetails)
                    @if($previewProgram !== $activeOverviewProgram)
                    <div data-room-program-overview="{{ $previewProgram }}" @if($previewProgram !== $selectedProgram) hidden @endif>
                        <span class="lesson-state">Not active at the selected time</span>
                        <div class="booking-context"><small>Program classes</small><strong>{{ collect($previewDetails['classes'])->join(', ') ?: $previewProgram }}</strong><span class="program-badge program-{{ strtolower($previewProgram) }}">{{ $previewProgram }}</span></div>
                        <dl class="room-facts"><div><dt><i class="bi bi-clock" aria-hidden="true"></i> {{ __('Session') }}</dt><dd>{{ collect($previewDetails['session_times'])->join(', ') ?: collect($previewDetails['sessions'])->join(', ') }}</dd></div></dl>
                        @php($previewLesson = $previewDetails['display_lesson'])
                        @if($previewLesson?->subject?->name)
                            <p class="small mb-0"><strong>{{ __('Subject') }}:</strong> {{ $subjectLabel($previewLesson->subject->name) }}<br><span>{{ $previewLesson->day }} · {{ substr($previewLesson->starts_at, 0, 5) }}–{{ substr($previewLesson->ends_at, 0, 5) }}</span></p>
                        @endif
                    </div>
                    @endif
                @endforeach
                @foreach($programDetails as $teacherProgram => $teacherDetails)
                    @php($showCurrentTeacher = $teacherProgram === $activeProgram && $teacherDetails['sessions']->contains($activeSession) && $lessonState === 'studying' && $lesson?->teacher)
                    <div class="room-current-teacher" @if($showCurrentTeacher) data-current-teacher @endif data-room-teacher-panel="{{ $teacherProgram }}" @if($teacherProgram !== $selectedProgram) hidden @endif>
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                        <div>
                            @if($showCurrentTeacher)
                                <small>{{ substr((string) $lesson->starts_at, 0, 5) }}&ndash;{{ substr((string) $lesson->ends_at, 0, 5) }} · {{ __('Teacher') }}</small><strong>{{ $lesson->teacher->name }}</strong>
                            @else
                                <small>{{ $teacherDetails['sessions']->join(', ') }} · Teachers and subjects</small>
                                <ul class="room-card-teachers" data-teacher-rotation>
                                    @forelse($teacherDetails['teacher_assignments'] as $assignment)
                                        <li data-teacher-slide @if(!$loop->first) hidden @endif><small>{{ $assignment['subjects']->map($subjectLabel)->unique()->join(', ') ?: 'Booking supervisor' }}</small><strong>{{ $assignment['name'] }}</strong></li>
                                    @empty
                                        <li>{{ __('Teacher unassigned') }}</li>
                                    @endforelse
                                </ul>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="room-program-section">
                <div class="room-program-tabs" role="group" aria-label="Programs in room {{ $room->room_number }}">
                    @forelse($programDetails as $programName => $details)
                        <button type="button" data-room-program="{{ $programName }}" aria-controls="room-{{ $room->id }}-program-{{ $loop->index }}" aria-pressed="{{ $programName === $selectedProgram ? 'true' : 'false' }}">{{ $programName }}</button>
                    @empty No programs assigned yet @endforelse
                </div>
                @foreach($programDetails as $programName => $details)
                    <div id="room-{{ $room->id }}-program-{{ $loop->index }}" data-room-program-panel="{{ $programName }}" data-program-session="{{ collect($details['sessions'])->first() }}" data-enrolled-count="{{ $details['students'] }}" @if($programName !== $selectedProgram) hidden @endif>
                        <div class="room-enrollment-summary">
                            <div><strong>{{ $details['students'] }}</strong><span>enrolled students</span></div>
                            <span class="room-seat-limit">{{ $room->capacity }} seats</span>
                        </div>
                        <div class="room-session-tags">@foreach($details['sessions'] as $sessionName)<span><i class="bi bi-clock" aria-hidden="true"></i> {{ $sessionName }}</span>@endforeach</div>
                    </div>
                @endforeach
            </div>
            <div class="room-card-footer"><span>Room details</span><a aria-label="View details for room {{ $room->room_number }}" href="{{ route('classrooms.show', ['classroom' => $room, 'date' => $moment->format('Y-m-d'), 'time' => $moment->format('H:i'), 'academic_year' => request('academic_year'), 'program' => $selectedProgram, 'session' => $selectedSession, 'live' => request()->boolean('live') || (!request()->filled('date') && !request()->filled('time')) ? 1 : 0]) }}">View <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
        </article>
    @empty
        <div class="empty-state classroom-empty"><i class="bi bi-door-closed" aria-hidden="true"></i>No physical classrooms match these filters.</div>
    @endforelse
</section>
<p class="empty-state" data-lesson-empty hidden>No classrooms match this lesson filter at the selected time.</p>
