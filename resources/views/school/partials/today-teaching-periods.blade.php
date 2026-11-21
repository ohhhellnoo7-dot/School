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
                        <tr>
                            <th scope="row">
                                {{ __('Period :number', ['number' => $loop->iteration]) }}
                                <small class="d-block fw-normal text-secondary text-nowrap">{{ $start }}–{{ substr($period->ends_at, 0, 5) }}</small>
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
