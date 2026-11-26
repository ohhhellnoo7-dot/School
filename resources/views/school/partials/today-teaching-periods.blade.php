<section class="panel h-100" aria-labelledby="today-teaching-periods-title">
    <div class="panel-header">
        <div>
            <h2 id="today-teaching-periods-title">{{ __('Today’s teaching schedule') }}</h2>
            <p>{{ today()->translatedFormat('l, F j, Y') }}</p>
        </div>
        <a class="text-link" href="{{ route('teachers.schedule', auth()->id()) }}">{{ __('Full schedule') }}</a>
    </div>
    @if($todayTeachingPeriods->isEmpty())
        <p class="empty-state p-3 mb-0">{{ __('You have no teaching periods scheduled today.') }}</p>
    @else
        @php
            $schoolTime = now()->format('H:i:s');
            $withSeconds = fn ($time) => strlen($time) === 5 ? $time.':00' : $time;
            $currentPeriods = $todayTeachingPeriods->filter(fn ($period) => $withSeconds($period->starts_at) <= $schoolTime && $schoolTime < $withSeconds($period->ends_at));
            $nextPeriod = $todayTeachingPeriods->first(fn ($period) => $withSeconds($period->starts_at) > $schoolTime);
            $featuredPeriods = $currentPeriods->isNotEmpty() ? $currentPeriods : collect([$nextPeriod])->filter();
        @endphp
        <div class="teaching-schedule-note" aria-live="polite">
            @forelse($featuredPeriods as $featured)
                @php
                    $featuredClass = $featured->schoolClass ?? $featured->subject?->schoolClass;
                    $featuredRoom = $featured->classroom?->room_number ?: $featured->room;
                @endphp
                <div>
                    <strong>{{ $currentPeriods->isNotEmpty() ? __('Teaching now') : __('Up next') }}</strong>
                    <span>· {{ substr($featured->starts_at, 0, 5) }}–{{ substr($featured->ends_at, 0, 5) }}</span>
                    @if($featuredClass)<span>· {{ trim($featuredClass->name.' '.$featuredClass->section) }}</span>@endif
                    @if($featured->subject)<span>· {{ $featured->subject->name }}</span>@endif
                    @if($featuredRoom)<span>· {{ __('Room :room', ['room' => $featuredRoom]) }}</span>@endif
                </div>
            @empty
                <strong>{{ __('No more teaching periods today.') }}</strong>
            @endforelse
        </div>
        <div class="table-responsive" role="region" aria-labelledby="today-teaching-periods-title" tabindex="0">
            <table class="table mb-0">
                <thead><tr>
                    <th scope="col">{{ __('Period') }}</th>
                    <th scope="col">{{ __('Class and subject') }}</th>
                    <th scope="col">{{ __('Classroom and building') }}</th>
                </tr></thead>
                <tbody>
                    @php
                        $previousSession = null;
                    @endphp
                    @foreach($todayTeachingPeriods as $period)
                        @php
                            $class = $period->schoolClass ?? $period->subject?->schoolClass;
                            $room = $period->classroom;
                            $building = $room?->buildingRecord?->name ?: $room?->building;
                            $start = substr($period->starts_at, 0, 5);
                            $session = match (true) {
                                $start < '12:00' => 'Morning',
                                $start >= '17:00' => 'Evening',
                                $start >= '16:30' && $class?->session === 'Evening' => 'Evening',
                                default => 'Afternoon',
                            };
                        @endphp
                        @if($session !== $previousSession)
                            <tr>
                                <th colspan="3" class="bg-body-tertiary fw-semibold">
                                    <span class="me-1" aria-hidden="true">{{ match ($session) { 'Morning' => '🌅', 'Afternoon' => '☀️', 'Evening' => '🌙' } }}</span>{{ __($session) }}
                                </th>
                            </tr>
                            @php
                                $previousSession = $session;
                            @endphp
                        @endif
                        <tr @class([
                            'teaching-period-current' => $currentPeriods->contains($period),
                            'teaching-period-next' => $nextPeriod?->id === $period->id,
                        ])>
                            <th scope="row">
                                {{ __('Period :number', ['number' => $loop->iteration]) }}
                                <small class="d-block fw-normal text-secondary text-nowrap">{{ $start }}–{{ substr($period->ends_at, 0, 5) }}</small>
                                @if($currentPeriods->contains($period))
                                    <span class="badge text-bg-primary mt-1">{{ __('Now') }}</span>
                                @elseif($nextPeriod?->id === $period->id)
                                    <span class="badge text-bg-light mt-1">{{ __('Next') }}</span>
                                @endif
                            </th>
                            <td>
                                <strong>{{ $class ? trim($class->name.' '.$class->section) : __('Class not assigned') }}</strong>
                                <small class="d-block text-secondary">{{ $period->subject?->name }}</small>
                            </td>
                            <td>
                                <strong>{{ $room?->room_number ?: ($period->room ?: __('Room not assigned')) }}</strong>
                                <small class="d-block text-secondary">{{ $building ?: __('Building not assigned') }}</small>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
